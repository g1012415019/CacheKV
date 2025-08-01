# 缓存穿透预防策略

## 场景描述

缓存穿透是指查询一个不存在的数据，由于缓存中没有该数据，每次请求都会直接访问数据库。如果有大量这样的请求，会对数据库造成巨大压力，甚至可能被恶意攻击利用。

## 缓存穿透的危害

### ❌ 典型的穿透场景
```php
// 危险：查询不存在的用户ID
function getUser($userId) {
    $cacheKey = "user:{$userId}";
    
    // 缓存中没有
    if (!$cache->has($cacheKey)) {
        // 每次都查询数据库
        $user = $database->query("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if ($user) {
            $cache->set($cacheKey, $user, 3600);
            return $user;
        }
        
        // 问题：不存在的数据没有缓存，下次还会查询数据库
        return null;
    }
    
    return $cache->get($cacheKey);
}

// 恶意攻击：大量查询不存在的ID
for ($i = 999999; $i < 1000100; $i++) {
    getUser($i); // 每次都会查询数据库！
}
```

### 问题分析
- **数据库压力**：大量无效查询直接打到数据库
- **性能下降**：系统响应时间急剧增加
- **资源浪费**：CPU、内存、网络资源被无效请求消耗
- **安全风险**：可能被恶意攻击者利用进行 DDoS

## CacheKV 的自动防穿透机制

### ✅ 内置的空值缓存
```php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

$keyManager = new KeyManager([
    'app_prefix' => 'antipen',
    'env_prefix' => 'prod',
    'version' => 'v1'
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// CacheKV 自动处理空值缓存！
$user = $cache->getByTemplate('user', ['id' => 999999], function() {
    // 查询数据库
    $user = getUserFromDatabase(999999);
    
    // 即使返回 null，CacheKV 也会缓存这个结果
    return $user; // null
});

// 第二次查询相同的不存在ID，直接从缓存返回 null，不会查询数据库
$user2 = $cache->getByTemplate('user', ['id' => 999999], function() {
    echo "这不会被执行！\n"; // 缓存命中，不会执行回调
    return null;
});
```

## 完整防穿透实现示例

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== 缓存穿透预防策略 ===\n\n";

// 1. 系统配置
$keyManager = new KeyManager([
    'app_prefix' => 'secure',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // 用户相关
        'user' => 'user:{id}',
        'user_profile' => 'user:profile:{id}',
        'user_email' => 'user:email:{email_hash}',
        
        // 商品相关
        'product' => 'product:{id}',
        'product_sku' => 'product:sku:{sku}',
        
        // 内容相关
        'post' => 'post:{id}',
        'post_slug' => 'post:slug:{slug_hash}',
        
        // 安全相关
        'login_attempt' => 'security:login:{ip_hash}',
        'rate_limit' => 'security:rate:{key_hash}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 2. 模拟数据库操作
$existingUsers = [1, 2, 3, 5, 8, 13, 21]; // 模拟存在的用户ID
$existingProducts = [101, 102, 105, 110, 115]; // 模拟存在的商品ID

function getUserFromDatabase($userId) {
    global $existingUsers;
    
    echo "📊 数据库查询用户: {$userId}\n";
    // 模拟数据库查询延迟
    usleep(100000); // 0.1秒
    
    if (in_array($userId, $existingUsers)) {
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com",
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // 返回 null 表示用户不存在
    return null;
}

function getProductFromDatabase($productId) {
    global $existingProducts;
    
    echo "📊 数据库查询商品: {$productId}\n";
    usleep(150000); // 0.15秒
    
    if (in_array($productId, $existingProducts)) {
        return [
            'id' => $productId,
            'name' => "Product {$productId}",
            'price' => rand(10, 1000) + 0.99,
            'stock' => rand(0, 100)
        ];
    }
    
    return null;
}

function getPostBySlug($slug) {
    $existingSlugs = ['hello-world', 'getting-started', 'advanced-tips'];
    
    echo "📊 数据库查询文章: {$slug}\n";
    usleep(120000); // 0.12秒
    
    if (in_array($slug, $existingSlugs)) {
        return [
            'id' => array_search($slug, $existingSlugs) + 1,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'content' => "Content for {$slug}",
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    return null;
}

// 3. 防穿透服务类
class AntiPenetrationService
{
    private $cache;
    private $keyManager;
    private $dbQueryCount = 0;
    
    public function __construct($cache, $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }
    
    /**
     * 安全的用户查询
     */
    public function getUser($userId)
    {
        return $this->cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
            $this->dbQueryCount++;
            return getUserFromDatabase($userId);
        });
    }
    
    /**
     * 安全的商品查询
     */
    public function getProduct($productId)
    {
        return $this->cache->getByTemplate('product', ['id' => $productId], function() use ($productId) {
            $this->dbQueryCount++;
            return getProductFromDatabase($productId);
        });
    }
    
    /**
     * 通过邮箱查询用户（使用哈希避免敏感信息泄露）
     */
    public function getUserByEmail($email)
    {
        $emailHash = md5(strtolower($email));
        
        return $this->cache->getByTemplate('user_email', ['email_hash' => $emailHash], function() use ($email) {
            echo "📊 数据库查询邮箱: {$email}\n";
            $this->dbQueryCount++;
            
            // 模拟邮箱查询
            $validEmails = ['user1@example.com', 'user2@example.com', 'admin@example.com'];
            
            if (in_array($email, $validEmails)) {
                return [
                    'id' => array_search($email, $validEmails) + 1,
                    'email' => $email,
                    'name' => 'User ' . (array_search($email, $validEmails) + 1)
                ];
            }
            
            return null;
        });
    }
    
    /**
     * 通过 slug 查询文章（使用哈希处理长 slug）
     */
    public function getPostBySlug($slug)
    {
        $slugHash = md5($slug);
        
        return $this->cache->getByTemplate('post_slug', ['slug_hash' => $slugHash], function() use ($slug) {
            $this->dbQueryCount++;
            return getPostBySlug($slug);
        });
    }
    
    /**
     * 批量查询（自动处理存在和不存在的混合情况）
     */
    public function getBatchUsers($userIds)
    {
        $startTime = microtime(true);
        
        $userKeys = array_map(function($id) {
            return $this->keyManager->make('user', ['id' => $id]);
        }, $userIds);
        
        $users = $this->cache->getMultiple($userKeys, function($missingKeys) {
            $missingIds = array_map(function($key) {
                $parsed = $this->keyManager->parse($key);
                return explode(':', $parsed['business_key'])[1];
            }, $missingKeys);
            
            echo "📊 批量数据库查询用户: " . implode(', ', $missingIds) . "\n";
            
            $results = [];
            foreach ($missingKeys as $i => $key) {
                $userId = $missingIds[$i];
                $this->dbQueryCount++;
                $results[$key] = getUserFromDatabase($userId);
            }
            
            return $results;
        });
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        echo "⏱️  批量查询 " . count($userIds) . " 个用户耗时: {$duration}ms\n";
        
        return $users;
    }
    
    /**
     * 模拟恶意攻击场景
     */
    public function simulateAttack($attackType = 'user', $count = 10)
    {
        echo "\n🚨 模拟 {$attackType} 穿透攻击 ({$count} 次请求)\n";
        $startTime = microtime(true);
        $initialDbCount = $this->dbQueryCount;
        
        switch ($attackType) {
            case 'user':
                // 攻击不存在的用户ID
                for ($i = 10000; $i < 10000 + $count; $i++) {
                    $user = $this->getUser($i);
                    if ($user === null) {
                        // 攻击者期望这里会一直查询数据库
                    }
                }
                break;
                
            case 'product':
                // 攻击不存在的商品ID
                for ($i = 20000; $i < 20000 + $count; $i++) {
                    $product = $this->getProduct($i);
                }
                break;
                
            case 'email':
                // 攻击不存在的邮箱
                for ($i = 0; $i < $count; $i++) {
                    $email = "fake{$i}@nonexistent.com";
                    $user = $this->getUserByEmail($email);
                }
                break;
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $dbQueries = $this->dbQueryCount - $initialDbCount;
        
        echo "攻击结果:\n";
        echo "  - 总请求数: {$count}\n";
        echo "  - 数据库查询次数: {$dbQueries}\n";
        echo "  - 防护效果: " . ($dbQueries < $count ? "✅ 有效" : "❌ 无效") . "\n";
        echo "  - 总耗时: {$duration}ms\n";
        echo "  - 平均响应时间: " . round($duration / $count, 2) . "ms\n";
    }
    
    /**
     * 获取数据库查询统计
     */
    public function getDbQueryCount()
    {
        return $this->dbQueryCount;
    }
    
    /**
     * 重置统计
     */
    public function resetStats()
    {
        $this->dbQueryCount = 0;
    }
}

// 4. 实际使用演示
echo "1. 初始化防穿透服务\n";
echo "====================\n";
$antiPenService = new AntiPenetrationService($cache, $keyManager);

echo "\n2. 正常查询场景\n";
echo "===============\n";

// 查询存在的用户
echo "查询存在的用户:\n";
$user1 = $antiPenService->getUser(1);
echo "用户1: " . ($user1 ? json_encode($user1) : 'null') . "\n";

$user2 = $antiPenService->getUser(2);
echo "用户2: " . ($user2 ? json_encode($user2) : 'null') . "\n";

// 查询不存在的用户
echo "\n查询不存在的用户:\n";
$user999 = $antiPenService->getUser(999);
echo "用户999: " . ($user999 ? json_encode($user999) : 'null') . "\n";

echo "\n3. 缓存命中测试\n";
echo "===============\n";

echo "再次查询相同用户（应该从缓存获取）:\n";
$initialDbCount = $antiPenService->getDbQueryCount();

// 这些查询应该都从缓存获取，不会增加数据库查询次数
$user1_cached = $antiPenService->getUser(1);
$user999_cached = $antiPenService->getUser(999); // 即使是 null 也被缓存了

$finalDbCount = $antiPenService->getDbQueryCount();
echo "缓存测试前数据库查询次数: {$initialDbCount}\n";
echo "缓存测试后数据库查询次数: {$finalDbCount}\n";
echo "缓存效果: " . ($finalDbCount == $initialDbCount ? "✅ 完全命中" : "❌ 有穿透") . "\n";

echo "\n4. 邮箱查询测试\n";
echo "===============\n";

$validEmail = $antiPenService->getUserByEmail('user1@example.com');
echo "有效邮箱查询: " . ($validEmail ? json_encode($validEmail) : 'null') . "\n";

$invalidEmail = $antiPenService->getUserByEmail('fake@nonexistent.com');
echo "无效邮箱查询: " . ($invalidEmail ? json_encode($invalidEmail) : 'null') . "\n";

echo "\n5. 批量查询测试\n";
echo "===============\n";

// 混合存在和不存在的ID
$mixedIds = [1, 2, 999, 1000, 3, 1001];
$batchUsers = $antiPenService->getBatchUsers($mixedIds);
echo "批量查询结果: " . count($batchUsers) . " 个结果\n";

foreach ($mixedIds as $id) {
    $key = $keyManager->make('user', ['id' => $id]);
    $exists = isset($batchUsers[$key]) && $batchUsers[$key] !== null;
    echo "  - 用户 {$id}: " . ($exists ? "存在" : "不存在") . "\n";
}

echo "\n6. 攻击模拟测试\n";
echo "===============\n";

// 重置统计
$antiPenService->resetStats();

// 模拟用户ID攻击
$antiPenService->simulateAttack('user', 20);

// 模拟商品ID攻击
$antiPenService->simulateAttack('product', 15);

// 模拟邮箱攻击
$antiPenService->simulateAttack('email', 10);

echo "\n7. 重复攻击测试（验证缓存效果）\n";
echo "===============================\n";

echo "第二轮攻击（相同的无效ID）:\n";
$antiPenService->simulateAttack('user', 20); // 相同的ID范围

echo "\n8. 缓存键管理\n";
echo "=============\n";

echo "生成的防穿透缓存键示例:\n";
$sampleKeys = [
    $keyManager->make('user', ['id' => 999]),
    $keyManager->make('product', ['id' => 20000]),
    $keyManager->make('user_email', ['email_hash' => md5('fake@example.com')]),
    $keyManager->make('post_slug', ['slug_hash' => md5('non-existent-post')])
];

foreach ($sampleKeys as $key) {
    echo "  - {$key}\n";
}

echo "\n9. 缓存状态检查\n";
echo "===============\n";

// 检查一些缓存是否存在
$cacheChecks = [
    ['user', ['id' => 1]],      // 存在的用户
    ['user', ['id' => 999]],    // 不存在的用户（但已缓存null）
    ['product', ['id' => 101]], // 存在的商品
    ['product', ['id' => 20000]] // 不存在的商品（但已缓存null）
];

foreach ($cacheChecks as [$template, $params]) {
    $exists = $cache->hasByTemplate($template, $params);
    $key = $keyManager->make($template, $params);
    echo "缓存键 {$key}: " . ($exists ? "✅ 已缓存" : "❌ 未缓存") . "\n";
}

echo "\n10. 最终统计\n";
echo "============\n";

$totalDbQueries = $antiPenService->getDbQueryCount();
$cacheStats = $cache->getStats();

echo "防穿透效果统计:\n";
echo "  - 总数据库查询次数: {$totalDbQueries}\n";
echo "  - 缓存命中次数: {$cacheStats['hits']}\n";
echo "  - 缓存未命中次数: {$cacheStats['misses']}\n";
echo "  - 缓存命中率: {$cacheStats['hit_rate']}%\n";
echo "  - 防穿透效果: " . ($cacheStats['hit_rate'] > 50 ? "✅ 优秀" : "⚠️  需要优化") . "\n";

echo "\n=== 缓存穿透预防示例完成 ===\n";
```

## 高级防穿透策略

### 1. 布隆过滤器集成
```php
class BloomFilterAntiPenetration
{
    private $cache;
    private $bloomFilter;
    
    public function getUser($userId)
    {
        // 先检查布隆过滤器
        if (!$this->bloomFilter->mightContain($userId)) {
            // 布隆过滤器确定不存在，直接返回null
            return null;
        }
        
        // 可能存在，继续正常的缓存流程
        return $this->cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
            return getUserFromDatabase($userId);
        });
    }
}
```

### 2. 智能TTL策略
```php
public function getWithSmartTTL($template, $params, $callback)
{
    return $this->cache->getByTemplate($template, $params, $callback, function($result) {
        // 存在的数据：长缓存
        if ($result !== null) {
            return 3600; // 1小时
        }
        
        // 不存在的数据：短缓存（防止长期占用内存）
        return 300; // 5分钟
    });
}
```

### 3. 频率限制
```php
class RateLimitedCache
{
    public function getWithRateLimit($key, $callback, $maxRequests = 10, $timeWindow = 60)
    {
        $rateLimitKey = "rate_limit:" . md5($key);
        $requests = $this->cache->get($rateLimitKey, function() { return 0; });
        
        if ($requests >= $maxRequests) {
            throw new Exception("Rate limit exceeded for key: {$key}");
        }
        
        // 增加请求计数
        $this->cache->set($rateLimitKey, $requests + 1, $timeWindow);
        
        // 正常处理请求
        return $this->cache->get($key, $callback);
    }
}
```

## 监控和告警

### 1. 穿透检测
```php
public function detectPenetration()
{
    $stats = $this->cache->getStats();
    $dbQueries = $this->getDbQueryCount();
    
    // 如果数据库查询次数接近缓存未命中次数，可能存在穿透
    $penetrationRatio = $dbQueries / max($stats['misses'], 1);
    
    if ($penetrationRatio > 0.8) {
        $this->alertPenetrationDetected($penetrationRatio);
    }
}
```

### 2. 异常模式识别
```php
public function detectAbnormalPattern()
{
    $recentQueries = $this->getRecentQueries();
    
    // 检测大量查询不存在ID的模式
    $nullResults = array_filter($recentQueries, function($query) {
        return $query['result'] === null;
    });
    
    if (count($nullResults) > count($recentQueries) * 0.7) {
        $this->alertSuspiciousActivity($nullResults);
    }
}
```

## 最佳实践总结

### 1. 自动空值缓存
- ✅ CacheKV 自动缓存 null 结果
- ✅ 防止重复查询不存在的数据
- ✅ 可配置空值缓存的TTL

### 2. 合理的缓存策略
```php
// 不同类型数据的缓存时间
$ttlStrategy = [
    'existing_data' => 3600,    // 存在的数据：1小时
    'null_data' => 300,         // 空数据：5分钟
    'error_data' => 60,         // 错误数据：1分钟
];
```

### 3. 监控和告警
- 监控缓存命中率
- 检测异常查询模式
- 设置数据库查询频率告警

### 4. 安全考虑
- 对敏感参数进行哈希处理
- 实施频率限制
- 记录可疑查询日志

通过 CacheKV 的自动防穿透机制，可以有效保护数据库免受恶意攻击，同时提升系统的整体性能和稳定性。
