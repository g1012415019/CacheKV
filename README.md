# CacheKV

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。该库支持单条及批量数据操作、基于标签的缓存失效管理，并提供基础的性能统计功能。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## 核心价值

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

## 主要功能

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

## 快速开始

### 安装

```bash
composer require asfop/cache-kv
```

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

### 门面使用

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

## 驱动支持

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

## API 参考

| 方法 | 功能 | 说明 |
|------|------|------|
| `get($key, $callback, $ttl)` | **核心功能**：自动回填缓存 | 缓存未命中时执行回调并回填 |
| `getMultiple($keys, $callback, $ttl)` | **批量获取**：自动处理批量缓存 | 只获取缓存中不存在的数据 |
| `setWithTag($key, $value, $tags, $ttl)` | **标签缓存**：设置带标签的缓存 | 便于批量管理相关缓存 |
| `clearTag($tag)` | **批量失效**：清除标签下所有缓存 | 一次清除相关的所有缓存项 |
| `getStats()` | **性能统计**：获取缓存统计信息 | 监控缓存命中率和性能 |
| `set($key, $value, $ttl)` | 设置缓存 | 基础缓存操作 |
| `has($key)` | 检查缓存是否存在 | 基础缓存操作 |
| `forget($key)` | 删除缓存 | 基础缓存操作 |

## 性能优化建议

1. **合理使用批量操作**：对于需要获取多个相关数据的场景，使用 `getMultiple` 而不是多次调用 `get`
2. **善用标签管理**：将相关的缓存项用标签分组，便于批量失效
3. **监控缓存命中率**：定期检查 `getStats()` 的结果，优化缓存策略
4. **选择合适的 TTL**：根据数据更新频率设置合理的过期时间

## 系统要求

- PHP 7.0 或更高版本
- 使用 Redis 驱动时需要 Redis 服务器和 predis/predis 包

## 测试

```bash
# 运行测试
./phpunit.sh

# 或使用 composer
composer test
```

## 框架集成

- [Laravel 集成](docs/laravel-integration.md)
- [ThinkPHP 集成](docs/thinkphp-integration.md)
- [Webman 集成](docs/webman-integration.md)

## 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

**CacheKV** - 让缓存回填变得简单！
