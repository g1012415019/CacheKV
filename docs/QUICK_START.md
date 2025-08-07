# 快速开始

5分钟快速上手 CacheKV。

## 安装

```bash
composer require asfop1/cache-kv
```

## 基础配置

创建 `config/cache_kv.php`：

```php
<?php
return array(
    'cache' => array(
        'ttl' => 3600,                          // 默认1小时
        'enable_stats' => true,                 // 启用统计
    ),
    'key_manager' => array(
        'app_prefix' => 'myapp',                // 应用前缀
        'groups' => array(
            'user' => array(
                'prefix' => 'user',
                'version' => 'v1',
                'keys' => array(
                    'kv' => array(
                        'profile' => array('template' => 'profile:{id}'),
                    ),
                ),
            ),
        ),
    ),
);
```

## 初始化

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    __DIR__ . '/config/cache_kv.php'
);
```

## 基本使用

### 单个缓存

```php
// 获取用户资料（若无则从数据库获取并缓存）
$user = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

### 批量缓存

```php
$users = cache_kv_get_multiple('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
], function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = getUserFromDatabase($params['id']);
    }
    return $data; // 返回关联数组
});
```

### 查看统计

```php
$stats = cache_kv_get_stats();
echo "命中率: {$stats['hit_rate']}\n";

$hotKeys = cache_kv_get_hot_keys(5);
foreach ($hotKeys as $key => $count) {
    echo "热点键: {$key} ({$count}次)\n";
}
```

## 完整示例

```php
<?php
require_once 'vendor/autoload.php';
use Asfop\CacheKV\Core\CacheKVFactory;

// 配置
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    __DIR__ . '/config/cache_kv.php'
);

// 模拟数据库函数
function getUserFromDatabase($userId) {
    return [
        'id' => $userId,
        'name' => "User {$userId}",
        'email' => "user{$userId}@example.com"
    ];
}

// 测试单个缓存
$user = cache_kv_get('user.profile', ['id' => 123], function() {
    echo "从数据库获取用户...\n";
    return getUserFromDatabase(123);
});
echo "用户: " . $user['name'] . "\n";

// 测试批量缓存
$users = cache_kv_get_multiple('user.profile', [
    ['id' => 1], ['id' => 2]
], function($missedKeys) {
    echo "批量从数据库获取 " . count($missedKeys) . " 个用户\n";
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = getUserFromDatabase($params['id']);
    }
    return $data;
});
echo "获取到 " . count($users) . " 个用户\n";

// 查看统计
$stats = cache_kv_get_stats();
echo "命中率: {$stats['hit_rate']}\n";
```

## 下一步

- [完整文档](README.md) - 详细功能说明
- [配置参考](CONFIG.md) - 所有配置选项
- [统计功能](STATS.md) - 性能监控
