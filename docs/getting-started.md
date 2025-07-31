# CacheKV 入门指南

## 什么是 CacheKV？

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。该库支持单条及批量数据操作、基于标签的缓存失效管理，并提供基础的性能统计功能。

### 核心价值

**解决缓存使用中的常见痛点：**
- ❌ 手动检查缓存是否存在
- ❌ 缓存未命中时手动从数据源获取
- ❌ 手动将获取的数据写入缓存
- ❌ 批量操作时的复杂逻辑处理
- ❌ 相关缓存的批量失效管理

**CacheKV 让这一切变得简单：**
```php
// 一行代码搞定：检查缓存 -> 未命中则获取数据 -> 自动回填缓存
$user = $cache->get('user:123', function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
});
```

## 安装

通过 Composer 安装 CacheKV：

```bash
composer require asfop/cache-kv
```

## 快速开始

### 基本使用

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 1. 创建缓存实例
$cache = new CacheKV(new ArrayDriver(), 3600);

// 2. 使用核心功能：自动回填缓存
$user = $cache->get('user:123', function() {
    // 这里写你的数据获取逻辑
    return [
        'id' => 123,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
});

echo "用户信息：" . json_encode($user);
```

### 使用门面

```php
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\CacheKVFacade;

// 注册服务
CacheKVServiceProvider::register([
    'default' => 'array',
    'stores' => [
        'array' => ['driver' => \Asfop\CacheKV\Cache\Drivers\ArrayDriver::class]
    ]
]);

// 使用门面
$product = CacheKVFacade::get('product:456', function() {
    return getProductFromAPI(456);
});
```

## 三大核心功能

### 🎯 1. 自动回填缓存（核心功能）

**单条数据获取：**
```php
// 缓存存在：直接返回缓存数据
// 缓存不存在：执行回调函数获取数据，自动写入缓存后返回
$product = $cache->get('product:1', function() {
    return $productService->getById(1); // 仅在缓存未命中时调用
});
```

**批量数据获取：**
```php
$userIds = [1, 2, 3, 4, 5];

// 自动处理：部分命中缓存，部分从数据源获取
$users = $cache->getMultiple($userIds, function($missingIds) {
    // 只获取缓存中不存在的用户数据
    return $userService->getByIds($missingIds);
});
```

### 🏷️ 2. 基于标签的缓存失效管理

```php
// 设置带标签的缓存
$cache->setWithTag('user:1', $userData, ['users', 'vip_users']);
$cache->setWithTag('user:2', $userData, ['users', 'normal_users']);

// 批量清除：一次清除所有用户相关缓存
$cache->clearTag('users');
```

### 📊 3. 性能统计功能

```php
$stats = $cache->getStats();
// 输出：['hits' => 85, 'misses' => 15, 'hit_rate' => 85.0]

// 监控缓存效果，优化缓存策略
if ($stats['hit_rate'] < 70) {
    // 缓存命中率过低，需要优化
}
```

## 驱动配置

### Redis 驱动（生产环境推荐）

```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

RedisDriver::setRedisFactory(function() {
    return new \Predis\Client(['host' => '127.0.0.1', 'port' => 6379]);
});

$cache = new CacheKV(new RedisDriver());
```

### Array 驱动（开发测试）

```php
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

$cache = new CacheKV(new ArrayDriver());
```

## 实际应用场景

### 场景1：用户信息缓存
```php
// 传统方式（繁琐）
if ($cache->has('user:' . $userId)) {
    $user = $cache->get('user:' . $userId);
} else {
    $user = $userService->getById($userId);
    $cache->set('user:' . $userId, $user, 3600);
}

// CacheKV 方式（简洁）
$user = $cache->get('user:' . $userId, function() use ($userId, $userService) {
    return $userService->getById($userId);
});
```

### 场景2：批量商品查询
```php
$productIds = [1, 2, 3, 4, 5];

// CacheKV 自动处理批量缓存逻辑
$products = $cache->getMultiple($productIds, function($missingIds) {
    return $productService->getByIds($missingIds); // 只查询缺失的商品
});
```

### 场景3：相关缓存失效
```php
// 用户更新时，清除相关的所有缓存
$cache->setWithTag('user:profile:' . $userId, $profile, ['user_' . $userId]);
$cache->setWithTag('user:settings:' . $userId, $settings, ['user_' . $userId]);
$cache->setWithTag('user:permissions:' . $userId, $permissions, ['user_' . $userId]);

// 用户信息变更时，一次性清除所有相关缓存
$cache->clearTag('user_' . $userId);
```

## 下一步

现在你已经了解了 CacheKV 的基本使用方法，可以继续阅读：

- [核心功能详解](core-features.md) - 深入了解三大核心功能的实现原理
- [使用指南](usage-guide.md) - 更多实际应用场景和最佳实践
- [API 参考文档](api-reference.md) - 完整的 API 文档
- [架构文档](architecture.md) - 了解 CacheKV 的设计架构

或者查看框架集成指南：
- [Laravel 集成](laravel-integration.md)
- [ThinkPHP 集成](thinkphp-integration.md)
- [Webman 集成](webman-integration.md)
