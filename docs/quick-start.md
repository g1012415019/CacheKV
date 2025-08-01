# 快速开始

本指南将帮助您在 5 分钟内上手 CacheKV。

## 安装

```bash
composer require asfop/cache-kv
```

## 基本配置

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 1. 配置键管理器
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',        // 应用名称
    'env_prefix' => 'dev',          // 环境标识
    'version' => 'v1',              // 版本号
]);

// 2. 创建缓存实例
$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);
```

## 第一个示例

```php
// 模拟数据库查询函数
function getUserFromDatabase($userId) {
    echo "从数据库查询用户 {$userId}...\n";
    return [
        'id' => $userId,
        'name' => "User {$userId}",
        'email' => "user{$userId}@example.com"
    ];
}

// 使用 CacheKV 获取用户信息
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

echo "用户信息: " . json_encode($user) . "\n";

// 第二次调用，直接从缓存获取
$user2 = $cache->getByTemplate('user', ['id' => 123], function() {
    echo "这不会被执行（缓存命中）\n";
    return null;
});

echo "缓存命中: " . json_encode($user2) . "\n";
```

## 批量操作示例

```php
// 批量获取用户
$userIds = [1, 2, 3, 4, 5];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) use ($keyManager) {
    // 解析出需要查询的用户ID
    $missingIds = [];
    foreach ($missingKeys as $key) {
        $parsed = $keyManager->parse($key);
        $missingIds[] = explode(':', $parsed['business_key'])[1];
    }
    
    echo "批量查询用户: " . implode(', ', $missingIds) . "\n";
    
    // 批量查询数据库
    $results = [];
    foreach ($missingKeys as $i => $key) {
        $userId = $missingIds[$i];
        $results[$key] = getUserFromDatabase($userId);
    }
    
    return $results;
});

echo "批量获取了 " . count($users) . " 个用户\n";
```

## 标签管理示例

```php
// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 1], [
    'id' => 1,
    'name' => 'John',
    'email' => 'john@example.com'
], ['users', 'vip_users']);

$cache->setByTemplateWithTag('user', ['id' => 2], [
    'id' => 2,
    'name' => 'Jane',
    'email' => 'jane@example.com'
], ['users', 'normal_users']);

echo "设置了带标签的用户缓存\n";

// 检查缓存状态
echo "用户1存在: " . ($cache->hasByTemplate('user', ['id' => 1]) ? 'Yes' : 'No') . "\n";
echo "用户2存在: " . ($cache->hasByTemplate('user', ['id' => 2]) ? 'Yes' : 'No') . "\n";

// 清除标签
$cache->clearTag('users');
echo "清除 'users' 标签后:\n";
echo "用户1存在: " . ($cache->hasByTemplate('user', ['id' => 1]) ? 'Yes' : 'No') . "\n";
echo "用户2存在: " . ($cache->hasByTemplate('user', ['id' => 2]) ? 'Yes' : 'No') . "\n";
```

## 生产环境配置

### Redis 驱动

```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 配置 Redis 连接
RedisDriver::setRedisFactory(function() {
    return new \Predis\Client([
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'password' => null,
    ]);
});

// 使用 Redis 驱动
$cache = new CacheKV(new RedisDriver(), 3600, $keyManager);
```

### 门面使用

```php
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\CacheKVFacade;

// 注册服务
CacheKVServiceProvider::register([
    'default' => 'redis',
    'stores' => [
        'redis' => ['driver' => RedisDriver::class]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1'
    ]
]);

// 使用门面
$user = CacheKVFacade::getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

## 完整示例

将以上代码组合成一个完整的示例文件：

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== CacheKV 快速开始示例 ===\n\n";

// 配置
$keyManager = new KeyManager([
    'app_prefix' => 'demo',
    'env_prefix' => 'dev',
    'version' => 'v1',
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 模拟数据源
function getUserFromDatabase($userId) {
    echo "📊 从数据库查询用户 {$userId}\n";
    return [
        'id' => $userId,
        'name' => "User {$userId}",
        'email' => "user{$userId}@example.com"
    ];
}

// 1. 基本使用
echo "1. 基本缓存操作\n";
echo "===============\n";
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});
echo "用户信息: " . json_encode($user) . "\n\n";

// 2. 缓存命中
echo "2. 缓存命中测试\n";
echo "===============\n";
$user2 = $cache->getByTemplate('user', ['id' => 123], function() {
    echo "这不会被执行\n";
    return null;
});
echo "缓存命中: " . json_encode($user2) . "\n\n";

// 3. 批量操作
echo "3. 批量操作\n";
echo "===========\n";
$userIds = [1, 2, 3];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) use ($keyManager) {
    $missingIds = [];
    foreach ($missingKeys as $key) {
        $parsed = $keyManager->parse($key);
        $missingIds[] = explode(':', $parsed['business_key'])[1];
    }
    
    $results = [];
    foreach ($missingKeys as $i => $key) {
        $userId = $missingIds[$i];
        $results[$key] = getUserFromDatabase($userId);
    }
    
    return $results;
});

echo "批量获取了 " . count($users) . " 个用户\n\n";

// 4. 统计信息
echo "4. 缓存统计\n";
echo "===========\n";
$stats = $cache->getStats();
echo "命中次数: {$stats['hits']}\n";
echo "未命中次数: {$stats['misses']}\n";
echo "命中率: {$stats['hit_rate']}%\n\n";

echo "🎉 快速开始示例完成！\n";
```

## 下一步

完成快速开始后，建议您：

1. 查看 [核心功能](core-features.md) 了解详细特性
2. 阅读 [API 参考](api-reference.md) 了解完整接口
3. 学习 [实战案例](examples.md) 了解实际应用

---

**恭喜！您已经掌握了 CacheKV 的基本使用！** 🎉
