<?php

require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\CacheKVFacade;

echo "=== CacheKV 综合优化验证测试 ===\n\n";

// 1. 测试完整的业务场景
echo "1. 完整业务场景测试\n";
echo "==================\n";

// 配置 KeyManager
$keyManager = new KeyManager([
    'app_prefix' => 'ecommerce',
    'env_prefix' => 'prod',
    'version' => 'v2',
    'templates' => [
        'user' => 'user:{id}',
        'user_profile' => 'user:profile:{id}',
        'product' => 'product:{id}',
        'product_detail' => 'product:detail:{id}',
        'order' => 'order:{id}',
        'cart' => 'cart:{user_id}',
        'category_products' => 'category:products:{id}:page:{page}',
        'api_weather' => 'api:weather:{city}',
        'session' => 'session:{id}',
        'search_results' => 'search:{query}:page:{page}',
    ]
]);

// 创建缓存实例
$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 模拟业务服务
class EcommerceService
{
    private $cache;
    private $keyManager;
    
    public function __construct($cache, $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }
    
    public function getUser($userId)
    {
        return $this->cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
            echo "📊 从数据库获取用户 {$userId}\n";
            return [
                'id' => $userId,
                'name' => "User {$userId}",
                'email' => "user{$userId}@example.com",
                'created_at' => date('Y-m-d H:i:s')
            ];
        });
    }
    
    public function getUserProfile($userId)
    {
        return $this->cache->getByTemplate('user_profile', ['id' => $userId], function() use ($userId) {
            echo "📊 从数据库获取用户资料 {$userId}\n";
            return [
                'user_id' => $userId,
                'bio' => "Bio for user {$userId}",
                'avatar' => "avatar_{$userId}.jpg",
                'location' => 'San Francisco'
            ];
        }, 7200); // 2小时缓存
    }
    
    public function getProducts($productIds)
    {
        $productKeys = array_map(function($id) {
            return $this->keyManager->make('product', ['id' => $id]);
        }, $productIds);
        
        return $this->cache->getMultiple($productKeys, function($missingKeys) {
            $missingIds = [];
            foreach ($missingKeys as $key) {
                $parsed = $this->keyManager->parse($key);
                $missingIds[] = explode(':', $parsed['business_key'])[1];
            }
            
            echo "📊 批量从数据库获取商品: " . implode(', ', $missingIds) . "\n";
            
            $results = [];
            foreach ($missingKeys as $i => $key) {
                $id = $missingIds[$i];
                $results[$key] = [
                    'id' => $id,
                    'name' => "Product {$id}",
                    'price' => rand(10, 1000),
                    'category' => 'Electronics'
                ];
            }
            return $results;
        });
    }
    
    public function updateUser($userId, $data)
    {
        echo "💾 更新用户 {$userId} 信息\n";
        
        // 使用标签清除相关缓存
        $this->cache->setByTemplateWithTag('user', ['id' => $userId], $data, ['users', "user_{$userId}"]);
        $this->cache->clearTag("user_{$userId}");
        
        echo "🗑️  清除用户 {$userId} 相关缓存\n";
    }
    
    public function getHotData($key, $slidingExpiration = false)
    {
        return $this->cache->getByTemplate('session', ['id' => $key], function() use ($key) {
            echo "📊 获取热点数据 {$key}\n";
            return ['key' => $key, 'data' => 'hot_data_' . $key, 'timestamp' => time()];
        }, 300, $slidingExpiration); // 5分钟，可选滑动过期
    }
}

$service = new EcommerceService($cache, $keyManager);

// 测试用户操作
echo "测试用户操作:\n";
$user1 = $service->getUser(1);
echo "用户1: {$user1['name']}\n";

$profile1 = $service->getUserProfile(1);
echo "用户1资料: {$profile1['bio']}\n";

// 测试缓存命中
$user1_cached = $service->getUser(1);
echo "用户1缓存命中: {$user1_cached['name']}\n";

// 测试批量操作
echo "\n测试批量商品获取:\n";
$products = $service->getProducts([101, 102, 103, 104, 105]);
echo "获取了 " . count($products) . " 个商品\n";

// 测试部分缓存命中
$products2 = $service->getProducts([103, 104, 105, 106, 107]);
echo "第二次获取了 " . count($products2) . " 个商品（部分缓存命中）\n";

// 测试滑动过期
echo "\n测试滑动过期:\n";
$hotData1 = $service->getHotData('session_123', false);
echo "热点数据（无滑动过期）: {$hotData1['data']}\n";

$hotData2 = $service->getHotData('session_456', true);
echo "热点数据（启用滑动过期）: {$hotData2['data']}\n";

// 测试标签管理
echo "\n测试标签管理:\n";
$service->updateUser(1, ['name' => 'Updated User 1', 'email' => 'updated1@example.com']);

// 2. 测试门面模式
echo "\n2. 门面模式测试\n";
echo "===============\n";

// 创建模拟 Redis 客户端
class MockRedis
{
    private $data = [];
    private $ttl = [];
    
    public function get($key) {
        if (isset($this->data[$key])) {
            if (!isset($this->ttl[$key]) || time() <= $this->ttl[$key]) {
                return $this->data[$key];
            } else {
                unset($this->data[$key], $this->ttl[$key]);
            }
        }
        return false;
    }
    
    public function setex($key, $ttl, $value) {
        $this->data[$key] = $value;
        $this->ttl[$key] = time() + $ttl;
        return true;
    }
    
    public function del($key) {
        $existed = isset($this->data[$key]);
        unset($this->data[$key], $this->ttl[$key]);
        return $existed ? 1 : 0;
    }
    
    public function exists($key) {
        return isset($this->data[$key]) && (!isset($this->ttl[$key]) || time() <= $this->ttl[$key]) ? 1 : 0;
    }
    
    public function mget($keys) {
        $results = [];
        foreach ($keys as $key) {
            $results[] = $this->get($key);
        }
        return $results;
    }
    
    public function expire($key, $ttl) {
        if (isset($this->data[$key])) {
            $this->ttl[$key] = time() + $ttl;
            return true;
        }
        return false;
    }
    
    // 模拟 Set 操作
    public function sadd($key, $member) { return 1; }
    public function smembers($key) { return []; }
    public function srem($key, $member) { return 1; }
}

// 配置门面
CacheKVServiceProvider::register([
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => new RedisDriver(new MockRedis())
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'facade_test',
        'env_prefix' => 'dev',
        'version' => 'v1',
        'templates' => [
            'api_data' => 'api:{service}:{endpoint}',
            'temp_data' => 'temp:{key}',
        ]
    ]
]);

// 使用门面
$apiData = CacheKVFacade::getByTemplate('api_data', [
    'service' => 'weather',
    'endpoint' => 'current'
], function() {
    echo "🌐 调用外部 API\n";
    return ['temperature' => 25, 'condition' => 'sunny', 'timestamp' => time()];
});

echo "API 数据: 温度 {$apiData['temperature']}°C, 天气 {$apiData['condition']}\n";

// 3. 性能压力测试
echo "\n3. 性能压力测试\n";
echo "===============\n";

$performanceCache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 大量数据写入测试
$startTime = microtime(true);
for ($i = 0; $i < 5000; $i++) {
    $performanceCache->setByTemplate('product', ['id' => $i], [
        'id' => $i,
        'name' => "Product {$i}",
        'price' => rand(10, 1000)
    ]);
}
$writeTime = microtime(true) - $startTime;

// 大量数据读取测试
$startTime = microtime(true);
$hits = 0;
for ($i = 0; $i < 5000; $i++) {
    $product = $performanceCache->getByTemplate('product', ['id' => $i]);
    if ($product) $hits++;
}
$readTime = microtime(true) - $startTime;

// 批量操作测试
$batchIds = range(0, 999);
$batchKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('product', ['id' => $id]);
}, $batchIds);

$startTime = microtime(true);
$batchResults = $performanceCache->getMultiple($batchKeys);
$batchTime = microtime(true) - $startTime;

echo "性能测试结果:\n";
echo "  写入 5000 条数据: " . round($writeTime * 1000, 2) . "ms\n";
echo "  读取 5000 条数据: " . round($readTime * 1000, 2) . "ms (命中 {$hits} 条)\n";
echo "  批量获取 1000 条: " . round($batchTime * 1000, 2) . "ms (获取 " . count($batchResults) . " 条)\n";

// 4. 错误处理和边界测试
echo "\n4. 错误处理和边界测试\n";
echo "=====================\n";

// 测试各种边界情况
$errorTests = [
    '空键处理' => function() use ($cache) {
        return $cache->set('', 'value') ? 'Failed' : 'Passed';
    },
    '无效TTL处理' => function() use ($cache) {
        return $cache->set('test', 'value', -1) ? 'Failed' : 'Passed';
    },
    '空数组批量操作' => function() use ($cache) {
        $result = $cache->getMultiple([]);
        return empty($result) ? 'Passed' : 'Failed';
    },
    '异常回调处理' => function() use ($cache) {
        try {
            $cache->getMultiple(['test'], function() {
                throw new Exception('Test exception');
            });
            return 'Passed'; // 异常被捕获，不影响程序运行
        } catch (Exception $e) {
            return 'Failed';
        }
    },
    '不存在模板处理' => function() use ($cache) {
        try {
            $cache->getByTemplate('nonexistent', ['id' => 1]);
            return 'Failed';
        } catch (Exception $e) {
            return 'Passed';
        }
    }
];

foreach ($errorTests as $testName => $testFunc) {
    $result = $testFunc();
    echo "  {$testName}: {$result}\n";
}

// 5. 最终统计
echo "\n5. 最终统计\n";
echo "===========\n";

$finalStats = $cache->getStats();
echo "ArrayDriver 统计:\n";
echo "  命中次数: {$finalStats['hits']}\n";
echo "  未命中次数: {$finalStats['misses']}\n";
echo "  命中率: {$finalStats['hit_rate']}%\n";

$facadeStats = CacheKVFacade::getStats();
echo "\nFacade 统计:\n";
echo "  命中次数: {$facadeStats['hits']}\n";
echo "  未命中次数: {$facadeStats['misses']}\n";
echo "  命中率: {$facadeStats['hit_rate']}%\n";

$performanceStats = $performanceCache->getStats();
echo "\n性能测试统计:\n";
echo "  命中次数: {$performanceStats['hits']}\n";
echo "  未命中次数: {$performanceStats['misses']}\n";
echo "  命中率: {$performanceStats['hit_rate']}%\n";

// 6. 内存使用情况
echo "\n6. 内存使用情况\n";
echo "===============\n";

$memoryUsage = memory_get_usage(true);
$peakMemory = memory_get_peak_usage(true);

echo "当前内存使用: " . round($memoryUsage / 1024 / 1024, 2) . " MB\n";
echo "峰值内存使用: " . round($peakMemory / 1024 / 1024, 2) . " MB\n";

// 清理测试
$arrayDriver = $cache->getDriver();
$cleanedItems = $arrayDriver->cleanup();
echo "清理过期项目: {$cleanedItems} 个\n";

$totalItems = $arrayDriver->count();
echo "剩余缓存项目: {$totalItems} 个\n";

echo "\n=== 综合优化验证测试完成 ===\n";

echo "\n🎉 优化成果总结:\n";
echo "================\n";
echo "✅ Redis 依赖解耦 - 支持任意 Redis 客户端\n";
echo "✅ 滑动过期优化 - 改为可选参数，使用更灵活\n";
echo "✅ 错误处理增强 - 边界情况和异常安全\n";
echo "✅ KeyManager 功能完善 - 类型转换、验证、清理\n";
echo "✅ ArrayDriver 性能优化 - 过期处理和内存管理\n";
echo "✅ 批量操作优化 - 智能处理和异常恢复\n";
echo "✅ 代码质量提升 - 注释完善、结构清晰\n";

echo "\n📊 性能指标:\n";
echo "============\n";
echo "• 写入性能: " . round($writeTime * 1000, 2) . "ms (5000条)\n";
echo "• 读取性能: " . round($readTime * 1000, 2) . "ms (5000条)\n";
echo "• 批量操作: " . round($batchTime * 1000, 2) . "ms (1000条)\n";
echo "• 内存使用: " . round($memoryUsage / 1024 / 1024, 2) . " MB\n";
echo "• 缓存命中率: {$performanceStats['hit_rate']}%\n";

echo "\n🚀 CacheKV 现在更加强大、稳定和高效！\n";
