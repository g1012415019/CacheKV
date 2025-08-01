<?php

require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\CacheKVFacade;

echo "=== Redis 依赖注入测试 ===\n\n";

// 1. 测试 ArrayDriver（无需外部依赖）
echo "1. 测试 ArrayDriver\n";
echo "==================\n";

$keyManager = new KeyManager([
    'app_prefix' => 'test',
    'env_prefix' => 'dev',
    'version' => 'v1'
]);

$arrayCache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

$testData = $arrayCache->getByTemplate('user', ['id' => 1], function() {
    return ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com'];
});

echo "ArrayDriver 测试成功: " . json_encode($testData) . "\n\n";

// 2. 测试 RedisDriver 错误处理（无 Redis 实例）
echo "2. 测试 RedisDriver 错误处理\n";
echo "============================\n";

try {
    $redisDriver = new RedisDriver();
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 正确抛出异常: " . $e->getMessage() . "\n";
}

try {
    $redisDriver = new RedisDriver(null);
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 正确抛出异常: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 模拟 Redis 客户端测试
echo "3. 模拟 Redis 客户端测试\n";
echo "========================\n";

// 创建一个模拟的 Redis 客户端
class MockRedisClient
{
    private $data = [];
    private $ttl = [];
    
    public function get($key)
    {
        if (isset($this->data[$key])) {
            // 检查是否过期
            if (isset($this->ttl[$key]) && time() > $this->ttl[$key]) {
                unset($this->data[$key], $this->ttl[$key]);
                return false;
            }
            return $this->data[$key];
        }
        return false;
    }
    
    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return true;
    }
    
    public function setex($key, $ttl, $value)
    {
        $this->data[$key] = $value;
        $this->ttl[$key] = time() + $ttl;
        return true;
    }
    
    public function del($key)
    {
        if (isset($this->data[$key])) {
            unset($this->data[$key], $this->ttl[$key]);
            return 1;
        }
        return 0;
    }
    
    public function exists($key)
    {
        if (isset($this->data[$key])) {
            // 检查是否过期
            if (isset($this->ttl[$key]) && time() > $this->ttl[$key]) {
                unset($this->data[$key], $this->ttl[$key]);
                return 0;
            }
            return 1;
        }
        return 0;
    }
    
    public function mget($keys)
    {
        $results = [];
        foreach ($keys as $key) {
            $results[] = $this->get($key);
        }
        return $results;
    }
    
    public function expire($key, $ttl)
    {
        if (isset($this->data[$key])) {
            $this->ttl[$key] = time() + $ttl;
            return true;
        }
        return false;
    }
    
    // 模拟 Set 操作（用于标签功能）
    public function sadd($key, $member)
    {
        if (!isset($this->data[$key])) {
            $this->data[$key] = [];
        }
        if (!is_array($this->data[$key])) {
            $this->data[$key] = [];
        }
        if (!in_array($member, $this->data[$key])) {
            $this->data[$key][] = $member;
        }
        return 1;
    }
    
    public function smembers($key)
    {
        return isset($this->data[$key]) && is_array($this->data[$key]) ? $this->data[$key] : [];
    }
    
    public function srem($key, $member)
    {
        if (isset($this->data[$key]) && is_array($this->data[$key])) {
            $index = array_search($member, $this->data[$key]);
            if ($index !== false) {
                unset($this->data[$key][$index]);
                $this->data[$key] = array_values($this->data[$key]);
                return 1;
            }
        }
        return 0;
    }
}

// 测试使用模拟 Redis 客户端
$mockRedis = new MockRedisClient();

try {
    $redisDriver = new RedisDriver($mockRedis);
    echo "✅ RedisDriver 创建成功\n";
    
    $redisCache = new CacheKV($redisDriver, 3600, $keyManager);
    
    // 测试基本操作
    $userData = $redisCache->getByTemplate('user', ['id' => 2], function() {
        return ['id' => 2, 'name' => 'Redis User', 'email' => 'redis@example.com'];
    });
    
    echo "✅ Redis 缓存操作成功: " . json_encode($userData) . "\n";
    
    // 测试缓存命中
    $cachedUser = $redisCache->getByTemplate('user', ['id' => 2], function() {
        return ['id' => 2, 'name' => 'Should not be called'];
    });
    
    if ($cachedUser['name'] === 'Redis User') {
        echo "✅ Redis 缓存命中测试成功\n";
    } else {
        echo "❌ Redis 缓存命中测试失败\n";
    }
    
} catch (Exception $e) {
    echo "❌ RedisDriver 测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. 测试服务提供者的驱动实例注入
echo "4. 测试服务提供者驱动实例注入\n";
echo "==============================\n";

try {
    // 使用驱动实例
    CacheKVServiceProvider::register([
        'default' => 'mock_redis',
        'stores' => [
            'mock_redis' => [
                'driver' => new RedisDriver($mockRedis)  // 直接传入驱动实例
            ]
        ],
        'key_manager' => [
            'app_prefix' => 'facade_test',
            'env_prefix' => 'dev',
            'version' => 'v1'
        ]
    ]);
    
    $facadeData = CacheKVFacade::getByTemplate('user', ['id' => 3], function() {
        return ['id' => 3, 'name' => 'Facade User', 'email' => 'facade@example.com'];
    });
    
    echo "✅ 门面驱动实例注入测试成功: " . json_encode($facadeData) . "\n";
    
} catch (Exception $e) {
    echo "❌ 门面驱动实例注入测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. 测试统计功能
echo "5. 测试统计功能\n";
echo "===============\n";

$stats = $redisCache->getStats();
echo "缓存统计:\n";
echo "  命中次数: {$stats['hits']}\n";
echo "  未命中次数: {$stats['misses']}\n";
echo "  命中率: {$stats['hit_rate']}%\n";

echo "\n=== Redis 依赖注入测试完成 ===\n";
echo "\n✅ 主要改进:\n";
echo "  - 移除了对 predis/predis 的硬依赖\n";
echo "  - Redis 实例通过构造函数注入\n";
echo "  - 支持任何 Redis 客户端（Predis、PhpRedis 等）\n";
echo "  - 增强了错误处理和验证\n";
echo "  - 保持了向后兼容性\n";
echo "\n💡 使用建议:\n";
echo "  - 开发环境：使用 ArrayDriver\n";
echo "  - 生产环境：注入实际的 Redis 客户端到 RedisDriver\n";
echo "  - 测试环境：可以注入模拟的 Redis 客户端\n";
