# 快速开始

## 安装

```bash
composer require asfop/cache-kv
```

## 基础配置

### 1. 定义缓存模板常量

```php
// src/Cache/CacheTemplates.php
class CacheTemplates {
    const USER = 'user_profile';
    const PRODUCT = 'product_info';
    const ORDER = 'order_detail';
    const USER_PERMISSIONS = 'user_permissions';
}
```

### 2. 配置 CacheKV

```php
// config/cache.php 或在应用启动时
use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'dev',
        'version' => 'v1',
        'templates' => [
            CacheTemplates::USER => 'user:{id}',
            CacheTemplates::PRODUCT => 'product:{id}',
            CacheTemplates::ORDER => 'order:{id}',
            CacheTemplates::USER_PERMISSIONS => 'user_perms:{user_id}',
        ]
    ]
]);
```

## 基本使用

### 方式一：使用辅助函数（推荐）

```php
// 获取用户信息
$user = cache_kv_get(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 获取商品信息
$product = cache_kv_get(CacheTemplates::PRODUCT, ['id' => 456], function() {
    return getProductFromDatabase(456);
});
```

### 方式二：使用 CacheKV 实例

```php
$cache = CacheKVFactory::store();

$user = $cache->getByTemplate(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

### 方式三：封装辅助类

```php
class CacheHelper {
    private static $cache;
    
    private static function getCache() {
        if (!self::$cache) {
            self::$cache = CacheKVFactory::store();
        }
        return self::$cache;
    }
    
    public static function getUser($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            return getUserFromDatabase($userId);
        });
    }
    
    public static function getProduct($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT, ['id' => $productId], function() use ($productId) {
            return getProductFromDatabase($productId);
        });
    }
}

// 使用
$user = CacheHelper::getUser(123);
$product = CacheHelper::getProduct(456);
```

## 生产环境配置

### Redis 配置

```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 使用 Predis
$redis = new \Predis\Client([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
]);

// 或使用 PhpRedis
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$redis->select(0);

CacheKVFactory::setDefaultConfig([
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => new RedisDriver($redis),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            CacheTemplates::USER => 'user:{id}',
            CacheTemplates::PRODUCT => 'product:{id}',
        ]
    ]
]);
```

## 常见操作

### 清除缓存

```php
// 清除单个缓存
$cache->deleteByTemplate(CacheTemplates::USER, ['id' => 123]);

// 清除标签相关的所有缓存
$cache->clearTag('users');
```

### 批量操作

```php
$userIds = [1, 2, 3, 4, 5];
$keyManager = CacheKVFactory::getKeyManager();

$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make(CacheTemplates::USER, ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    return getUsersFromDatabase($missingKeys);
});
```

### 设置带标签的缓存

```php
$cache->setByTemplateWithTag(
    CacheTemplates::USER, 
    ['id' => 123], 
    $userData, 
    ['users', 'vip_users']
);
```

## 下一步

- 查看 [核心功能](core-features.md) 了解更多高级功能
- 查看 [实战案例](examples.md) 了解真实业务场景应用
- 查看 [最佳实践](best-practices.md) 了解生产环境使用建议
