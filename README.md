# CacheKV

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## 🎯 核心价值

**解决缓存使用中的常见痛点：**
- ❌ 手动检查缓存是否存在
- ❌ 缓存未命中时手动从数据源获取
- ❌ 手动将获取的数据写入缓存
- ❌ 批量操作时的复杂逻辑处理
- ❌ 相关缓存的批量失效管理
- ❌ 缓存键名称管理混乱

**CacheKV 让这一切变得简单：**
```php
// 一行代码搞定：检查缓存 → 未命中则获取数据 → 自动回填缓存
$user = cache_kv_get($cache, 'user', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
});
```

## ⚡ 快速开始

### 安装

```bash
composer require asfop/cache-kv
```

### 5分钟上手

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheKVBuilder;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 方式1: 快速创建（开发测试推荐）
$cache = CacheKVFactory::quick('myapp', 'dev', [
    'user' => 'user:{id}',
    'product' => 'product:{id}',
]);

// 方式2: 构建器方式（生产环境推荐）
$cache = CacheKVBuilder::create()
    ->useArrayDriver()
    ->appPrefix('myapp')
    ->envPrefix('prod')
    ->version('v1')
    ->template('user', 'user:{id}')
    ->template('product', 'product:{id}')
    ->ttl(3600)
    ->build();

// 使用缓存 - 自动回填
$user = cache_kv_get($cache, 'user', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
});

echo "用户信息: " . json_encode($user);
```

## 🚀 核心功能

### 1. 自动回填缓存

```php
// 缓存存在：直接返回缓存数据
// 缓存不存在：执行回调函数获取数据，自动写入缓存后返回
$product = cache_kv_get($cache, 'product', ['id' => 1], function() {
    return getProductFromDatabase(1);
});
```

### 2. 批量操作优化

```php
$userIds = [1, 2, 3, 4, 5];
$userKeys = array_map(function($id) use ($cache) {
    return $cache->makeKey('user', ['id' => $id]);
}, $userIds);

// 自动处理：部分命中缓存，部分从数据源获取
$users = $cache->getMultiple($userKeys, function($missingKeys) {
    return getUsersFromDatabase($missingKeys);
});
```

### 3. 标签管理

```php
// 设置带标签的缓存
$cache->setWithTag('user:1', $userData, ['users', 'vip_users']);

// 批量清除：一次清除所有用户相关缓存
$cache->clearTag('users');
```

### 4. 统一键管理

```php
// 标准化的键生成：myapp:prod:v1:user:123
$userKey = $cache->makeKey('user', ['id' => 123]);

// 环境隔离：开发、测试、生产环境自动隔离
// 版本管理：数据结构变更时版本号隔离
```

## 🔧 配置方式

CacheKV 提供多种灵活的配置方式，适应不同的使用场景：

### 1. 直接创建（生产环境推荐）

```php
use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

$driver = new ArrayDriver();
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        'user' => 'user:{id}',
        'product' => 'product:{id}',
    ]
]);

$cache = CacheKVFactory::create($driver, 3600, $keyManager);
```

### 2. 配置数组方式（配置驱动）

```php
$config = [
    'driver' => new ArrayDriver(),
    'ttl' => 3600,
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            'user' => 'user:{id}',
            'product' => 'product:{id}',
        ]
    ]
];

$cache = CacheKVFactory::createFromConfig($config);
```

### 3. 构建器方式（流畅API）

```php
use Asfop\CacheKV\CacheKVBuilder;

$cache = CacheKVBuilder::create()
    ->useArrayDriver()
    ->ttl(3600)
    ->appPrefix('myapp')
    ->envPrefix('prod')
    ->version('v1')
    ->template('user', 'user:{id}')
    ->template('product', 'product:{id}')
    ->build();
```

### 4. 快速创建（开发测试）

```php
$cache = CacheKVFactory::quick('myapp', 'dev', [
    'user' => 'user:{id}',
    'product' => 'product:{id}',
], 3600);
```

## 🔧 驱动支持

### Redis 驱动（生产环境推荐）

#### 使用 Predis
```bash
composer require predis/predis
```

```php
use Asfop\CacheKV\CacheKVBuilder;

$redis = new \Predis\Client(['host' => '127.0.0.1', 'port' => 6379]);

$cache = CacheKVBuilder::create()
    ->useRedisDriver($redis)
    ->appPrefix('myapp')
    ->envPrefix('prod')
    ->template('user', 'user:{id}')
    ->ttl(3600)
    ->build();
```

#### 使用 PhpRedis 扩展
```php
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$cache = CacheKVBuilder::create()
    ->useRedisDriver($redis)
    ->appPrefix('myapp')
    ->template('user', 'user:{id}')
    ->build();
```

### Array 驱动（开发测试）
```php
$cache = CacheKVBuilder::create()
    ->useArrayDriver()
    ->appPrefix('myapp')
    ->template('user', 'user:{id}')
    ->build();
```

## 🎨 最佳实践

### 推荐的项目结构

```php
// src/Cache/CacheTemplates.php
class CacheTemplates {
    const USER = 'user';
    const PRODUCT = 'product';
    const ORDER = 'order';
}

// src/Cache/CacheHelper.php
class CacheHelper {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function getUser($userId) {
        return cache_kv_get($this->cache, CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            return getUserFromDatabase($userId);
        });
    }
    
    public function clearUserCache($userId) {
        cache_kv_delete($this->cache, CacheTemplates::USER, ['id' => $userId]);
    }
}
```

### 框架集成示例

```php
// Laravel 服务提供者
class CacheKVServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->singleton('cache.kv', function($app) {
            $config = $app['config']['cache.kv'];
            return CacheKVFactory::createFromConfig($config);
        });
    }
}

// 配置文件 config/cache.php
return [
    'kv' => [
        'driver' => new ArrayDriver(), // 或 RedisDriver
        'ttl' => 3600,
        'key_manager' => [
            'app_prefix' => env('APP_NAME', 'laravel'),
            'env_prefix' => env('APP_ENV', 'production'),
            'version' => 'v1',
            'templates' => [
                'user' => 'user:{id}',
                'product' => 'product:{id}',
            ]
        ]
    ]
];
```

## 🌟 实际应用场景

### 用户信息缓存
```php
$userService = new UserService($cache);

// 获取用户信息，自动处理缓存逻辑
$user = $userService->getUser(123);

// 更新用户后清除相关缓存
$userService->updateUser(123, $data);
$userService->clearUserCache(123);
```

### 商品信息批量缓存
```php
// 批量获取商品，自动优化：部分从缓存，部分从数据库
$productIds = [1, 2, 3, 4, 5];
$productKeys = array_map(function($id) use ($cache) {
    return $cache->makeKey('product', ['id' => $id]);
}, $productIds);

$products = $cache->getMultiple($productKeys, function($missingKeys) {
    return getProductsFromDatabase($missingKeys);
});
```

### API 响应缓存
```php
// 缓存外部 API 响应，避免频繁调用
$weather = cache_kv_get($cache, 'api_weather', ['city' => $city], function() use ($city) {
    return callWeatherAPI($city);
}, 1800); // 30分钟缓存
```

## 🔄 多环境支持

```php
// 开发环境
$devCache = CacheKVFactory::quick('myapp', 'dev', $templates, 600);

// 测试环境
$testCache = CacheKVFactory::quick('myapp', 'test', $templates, 300);

// 生产环境
$prodCache = CacheKVBuilder::create()
    ->useRedisDriver($redis)
    ->appPrefix('myapp')
    ->envPrefix('prod')
    ->templates($templates)
    ->ttl(3600)
    ->build();
```

## 📊 性能提升

| 场景 | 传统方案 | CacheKV 方案 | 性能提升 |
|------|----------|--------------|----------|
| 单条查询 | 每次查数据库 | 缓存命中直接返回 | **10-100x** |
| 批量查询 | N次数据库查询 | 1次批量查询 | **10-1000x** |
| 相关缓存清理 | 手动逐个清除 | 标签批量清除 | **维护性大幅提升** |
| 键名管理 | 字符串硬编码 | 常量统一管理 | **可维护性大幅提升** |

## 📚 文档

- [快速开始](docs/quick-start.md) - 5分钟上手指南
- [核心功能](docs/core-features.md) - 详细功能介绍
- [API 参考](docs/api-reference.md) - 完整的 API 文档
- [实战案例](docs/examples.md) - 真实业务场景应用
- [最佳实践](docs/best-practices.md) - 生产环境使用建议

## 📈 核心优势

### ✅ 开发效率
- **一行代码**：复杂的缓存逻辑变成一行代码
- **自动化**：缓存命中、未命中、回填全自动处理
- **标准化**：统一的键命名和管理规范
- **常量管理**：IDE 支持，避免拼写错误

### ✅ 性能提升
- **智能批量**：自动优化批量操作，避免 N+1 查询
- **防穿透**：自动缓存空值，防止缓存穿透攻击
- **命中率高**：科学的缓存策略，显著提升命中率

### ✅ 维护性
- **标签管理**：相关缓存批量管理，维护简单
- **环境隔离**：开发、测试、生产环境自动隔离
- **版本管理**：支持数据结构升级和版本迁移
- **灵活配置**：多种配置方式，适应不同场景

### ✅ 易用性
- **多种配置方式**：从快速创建到完全自定义
- **依赖注入友好**：完美支持现代PHP框架
- **零学习成本**：符合直觉的API设计
- **完整文档**：详细的文档和示例

## 🏆 适用场景

- **用户管理系统** - 用户信息、权限、会话缓存
- **电商平台** - 商品信息、价格、库存缓存
- **内容管理系统** - 文章、评论、分类缓存
- **API 服务** - 外部 API 响应缓存
- **数据分析平台** - 统计数据、报表缓存
- **微服务架构** - 服务间数据缓存
- **高并发应用** - 热点数据缓存

## 🤝 贡献

欢迎为 CacheKV 做出贡献！请查看我们的贡献指南了解如何参与。

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

**开始您的高效缓存之旅！** 🚀
