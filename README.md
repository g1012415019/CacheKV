# CacheKV

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。该库支持单条及批量数据操作、基于标签的缓存失效管理，并提供统一的键管理系统。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## 🎯 核心价值

**解决缓存使用中的常见痛点：**
- ❌ 手动检查缓存是否存在
- ❌ 缓存未命中时手动从数据源获取
- ❌ 手动将获取的数据写入缓存
- ❌ 批量操作时的复杂逻辑处理
- ❌ 相关缓存的批量失效管理
- ❌ 缓存键命名不规范，难以维护

**CacheKV 让这一切变得简单：**
```php
// 一行代码搞定：检查缓存 → 未命中则获取数据 → 自动回填缓存
$user = $cache->getByTemplate('user', ['id' => 123], function() {
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

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 1. 配置键管理器
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
]);

// 2. 创建缓存实例
$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 3. 开始使用 - 自动回填缓存
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
});

echo "用户信息: " . json_encode($user);
```

## 🚀 四大核心功能

### 1. 自动回填缓存

**一行代码搞定缓存逻辑**

```php
// 缓存存在：直接返回缓存数据
// 缓存不存在：执行回调函数获取数据，自动写入缓存后返回
$product = $cache->getByTemplate('product', ['id' => 1], function() {
    return getProductFromDatabase(1); // 仅在缓存未命中时调用
});
```

### 2. 批量操作

**避免 N+1 查询问题**

```php
$userIds = [1, 2, 3, 4, 5];

// 自动处理：部分命中缓存，部分从数据源获取
$users = $cache->getMultiple($userKeys, function($missingKeys) {
    // 只获取缓存中不存在的用户数据
    return getUsersFromDatabase($missingKeys);
});
```

### 3. 标签管理

**批量失效相关缓存**

```php
// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 1], $userData, ['users', 'vip_users']);
$cache->setByTemplateWithTag('user', ['id' => 2], $userData, ['users', 'normal_users']);

// 批量清除：一次清除所有用户相关缓存
$cache->clearTag('users');
```

### 4. 统一键管理

**标准化的缓存键命名**

```php
// 标准化的键生成：myapp:prod:v1:user:123
$userKey = $keyManager->make('user', ['id' => 123]);

// 环境隔离：开发、测试、生产环境自动隔离
// 版本管理：数据结构变更时版本号隔离
// 模板化：统一的键命名规范
```

## 📊 性能提升

| 场景 | 传统方案 | CacheKV 方案 | 性能提升 |
|------|----------|--------------|----------|
| 单条查询 | 每次查数据库 | 缓存命中直接返回 | **10-100x** |
| 批量查询 | N次数据库查询 | 1次批量查询 | **10-1000x** |
| 相关缓存清理 | 手动逐个清除 | 标签批量清除 | **维护性大幅提升** |

## 📚 文档导航

### 🚀 快速开始
- [**安装和配置**](docs/guide/installation.md) - 5分钟快速上手
- [**基础概念**](docs/guide/concepts.md) - 理解核心概念
- [**第一个示例**](docs/guide/first-example.md) - 15分钟实践教程

### 📖 使用指南
- [**核心功能**](docs/guide/core-features.md) - 四大核心功能详解
- [**Key 管理**](docs/guide/key-management.md) - 统一的缓存键管理
- [**高级特性**](docs/guide/advanced-features.md) - 滑动过期、防穿透等
- [**性能优化**](docs/guide/performance.md) - 缓存策略和性能调优

### 💡 实战案例
- [**用户信息缓存**](docs/examples/user-caching.md) - 用户系统缓存最佳实践
- [**商品数据缓存**](docs/examples/product-caching.md) - 电商商品缓存优化
- [**API 响应缓存**](docs/examples/api-caching.md) - 外部 API 调用缓存
- [**内容管理缓存**](docs/examples/content-caching.md) - CMS 系统缓存策略

### 🔧 框架集成
- [**Laravel 集成**](docs/integrations/laravel.md) - 在 Laravel 中使用
- [**ThinkPHP 集成**](docs/integrations/thinkphp.md) - 在 ThinkPHP 中使用
- [**Webman 集成**](docs/integrations/webman.md) - 在 Webman 中使用

### 📋 参考资料
- [**API 参考**](docs/reference/api.md) - 完整的 API 文档
- [**配置参考**](docs/reference/configuration.md) - 所有配置选项
- [**架构设计**](docs/advanced/architecture.md) - 深入了解架构

## 🎯 推荐学习路径

### 新手入门（30分钟）
1. [安装和配置](docs/guide/installation.md) ⏱️ 5分钟
2. [基础概念](docs/guide/concepts.md) ⏱️ 10分钟
3. [第一个示例](docs/guide/first-example.md) ⏱️ 15分钟

### 进阶使用（2小时）
1. [核心功能](docs/guide/core-features.md) ⏱️ 30分钟
2. [Key 管理](docs/guide/key-management.md) ⏱️ 45分钟
3. [实战案例](docs/examples/) ⏱️ 45分钟

### 生产部署（1小时）
1. [性能优化](docs/guide/performance.md) ⏱️ 30分钟
2. [框架集成](docs/integrations/) ⏱️ 20分钟
3. [故障排查](docs/advanced/troubleshooting.md) ⏱️ 10分钟

## 🌟 实际应用场景

### 用户系统缓存
```php
class UserService
{
    public function getUser($userId)
    {
        return $this->cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
            return $this->userRepository->find($userId);
        });
    }
    
    public function updateUser($userId, $data)
    {
        $this->userRepository->update($userId, $data);
        
        // 一行代码清除用户相关的所有缓存
        $this->cache->clearTag("user_{$userId}");
    }
}
```

### 电商商品缓存
```php
class ProductService
{
    public function getProducts($productIds)
    {
        $productKeys = array_map(function($id) {
            return $this->keyManager->make('product', ['id' => $id]);
        }, $productIds);
        
        // 批量获取，自动处理缓存命中和未命中
        return $this->cache->getMultiple($productKeys, function($missingKeys) {
            return $this->productRepository->findByKeys($missingKeys);
        });
    }
}
```

### API 响应缓存
```php
class ApiService
{
    public function getWeather($city)
    {
        return $this->cache->getByTemplate('api_weather', ['city' => $city], function() use ($city) {
            return $this->weatherApi->getCurrentWeather($city);
        }, 1800); // 30分钟缓存
    }
}
```

## 🔧 驱动支持

### Redis 驱动（生产环境推荐）
```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

RedisDriver::setRedisFactory(function() {
    return new \Predis\Client(['host' => '127.0.0.1', 'port' => 6379]);
});

$cache = new CacheKV(new RedisDriver(), 3600, $keyManager);
```

### Array 驱动（开发测试）
```php
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);
```

## 🎨 门面使用

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
$product = CacheKVFacade::getByTemplate('product', ['id' => 456], function() {
    return getProductFromAPI(456);
});
```

## 📈 核心优势

### ✅ 开发效率
- **一行代码**：复杂的缓存逻辑变成一行代码
- **自动化**：缓存命中、未命中、回填全自动处理
- **标准化**：统一的键命名和管理规范

### ✅ 性能提升
- **智能批量**：自动优化批量操作，避免 N+1 查询
- **防穿透**：自动缓存空值，防止缓存穿透攻击
- **命中率高**：科学的缓存策略，显著提升命中率

### ✅ 维护性
- **标签管理**：相关缓存批量管理，维护简单
- **环境隔离**：开发、测试、生产环境自动隔离
- **版本管理**：支持数据结构升级和版本迁移

### ✅ 扩展性
- **驱动支持**：支持 Redis、Array 等多种存储后端
- **框架集成**：无缝集成 Laravel、ThinkPHP 等主流框架
- **自定义扩展**：支持自定义驱动和扩展开发

## 🏆 适用场景

- **用户管理系统** - 用户信息、权限、会话缓存
- **电商平台** - 商品信息、价格、库存缓存
- **内容管理系统** - 文章、评论、分类缓存
- **API 服务** - 外部 API 响应缓存
- **数据分析平台** - 统计数据、报表缓存
- **社交网络** - 用户动态、关系链缓存

## 🤝 贡献

欢迎为 CacheKV 做出贡献！请查看我们的贡献指南了解如何参与。

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

**开始您的高效缓存之旅！** 🚀

建议从 [安装和配置](docs/guide/installation.md) 开始，然后根据您的需求选择相应的学习路径。
