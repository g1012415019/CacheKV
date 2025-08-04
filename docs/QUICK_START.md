# CacheKV 快速开始

本指南将帮助你在5分钟内开始使用CacheKV。

## 安装

```bash
composer require asfop1/cache-kv
```

## 基础配置

### 1. 创建配置文件

创建 `config/cache_kv.php`：

```php
<?php
return array(
    'cache' => array(
        'ttl' => 3600,                          // 默认1小时
        'enable_stats' => true,                 // 启用统计
        'hot_key_auto_renewal' => true,         // 启用热点键自动续期
        'hot_key_threshold' => 100,             // 100次访问算热点
    ),
    
    'key_manager' => array(
        'app_prefix' => 'myapp',                // 你的应用前缀
        'groups' => array(
            'user' => array(
                'prefix' => 'user',
                'version' => 'v1',
                'keys' => array(
                    'kv' => array(
                        'profile' => array('template' => 'profile:{id}'),
                        'settings' => array('template' => 'settings:{id}'),
                    ),
                ),
            ),
        ),
    ),
);
```

### 2. 初始化CacheKV

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 配置Redis连接和配置文件
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    __DIR__ . '/config/cache_kv.php'
);
```

## 使用示例

### 单个缓存操作

```php
// 获取用户资料（若无则从数据库获取并缓存）
$userProfile = cache_kv_get('user.profile', ['id' => 123], function() {
    // 这个回调只在缓存未命中时执行
    return [
        'id' => 123,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
});

echo json_encode($userProfile);
```

### 批量缓存操作

```php
// 批量获取多个用户的资料
$userParams = [
    ['id' => 1],
    ['id' => 2], 
    ['id' => 3],
];

$results = cache_kv_get_multiple('user.profile', $userParams, function($missedKeys) {
    // 批量处理未命中的键
    $data = [];
    foreach ($missedKeys as $params) {
        $userId = $params['id'];
        $data[] = getUserFromDatabase($userId);
    }
    return $data;
});

foreach ($results as $userData) {
    echo "Key: {$key}\n";
    echo "Data: " . json_encode($userData) . "\n\n";
}
```

### 查看统计信息

```php
// 获取缓存统计
$stats = cache_kv_get_stats();
echo "命中率: {$stats['hit_rate']}\n";
echo "总请求: {$stats['total_requests']}\n";

// 获取热点键
$hotKeys = cache_kv_get_hot_keys(5);
foreach ($hotKeys as $key => $count) {
    echo "热点键: {$key} (访问{$count}次)\n";
}
```

## 完整示例

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 1. 配置
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    __DIR__ . '/config/cache_kv.php'
);

// 2. 模拟数据库函数
function getUserFromDatabase($userId) {
    // 模拟数据库查询
    sleep(1); // 模拟查询耗时
    return [
        'id' => $userId,
        'name' => "User {$userId}",
        'email' => "user{$userId}@example.com",
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// 3. 单个缓存测试
echo "=== 单个缓存测试 ===\n";
$start = microtime(true);

$user = cache_kv_get('user.profile', ['id' => 123], function() {
    echo "从数据库获取用户123...\n";
    return getUserFromDatabase(123);
});

$time1 = microtime(true) - $start;
echo "第一次获取耗时: " . round($time1 * 1000, 2) . "ms\n";
echo "用户数据: " . json_encode($user) . "\n\n";

// 4. 再次获取（应该从缓存获取）
$start = microtime(true);

$user = cache_kv_get('user.profile', ['id' => 123], function() {
    echo "从数据库获取用户123...\n";
    return getUserFromDatabase(123);
});

$time2 = microtime(true) - $start;
echo "第二次获取耗时: " . round($time2 * 1000, 2) . "ms\n";
echo "性能提升: " . round(($time1 - $time2) / $time1 * 100, 1) . "%\n\n";

// 5. 批量缓存测试
echo "=== 批量缓存测试 ===\n";
$userParams = [];
for ($i = 1; $i <= 5; $i++) {
    $userParams[] = ['id' => $i];
}

$start = microtime(true);
$results = cache_kv_get_multiple('user.profile', $userParams, function($missedKeys) {
    echo "批量从数据库获取 " . count($missedKeys) . " 个用户...\n";
    $data = [];
    foreach ($missedKeys as $params) {
        $userId = $params['id'];
        $data[] = getUserFromDatabase($userId);
    }
    return $data;
});

$batchTime = microtime(true) - $start;
echo "批量获取耗时: " . round($batchTime * 1000, 2) . "ms\n";
echo "获取到 " . count($results) . " 个用户数据\n\n";

// 6. 统计信息
echo "=== 统计信息 ===\n";
$stats = cache_kv_get_stats();
echo "命中率: {$stats['hit_rate']}\n";
echo "总请求: {$stats['total_requests']}\n";
echo "命中次数: {$stats['hits']}\n";
echo "未命中次数: {$stats['misses']}\n\n";

$hotKeys = cache_kv_get_hot_keys(3);
echo "热点键:\n";
foreach ($hotKeys as $key => $count) {
    echo "  {$key}: {$count}次访问\n";
}
```

## 下一步

- 阅读 [完整文档](README.md) 了解更多功能
- 查看 [配置参考](CONFIG.md) 了解所有配置选项
- 学习 [统计功能](STATS.md) 进行性能监控
