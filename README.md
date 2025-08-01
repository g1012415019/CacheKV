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

### 基本使用

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;

// 1. 一次性配置（通常在应用启动时）
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            'user' => 'user:{id}',
            'product' => 'product:{id}',
            'order' => 'order:{id}',
        ]
    ]
]);

// 2. 在任何地方直接使用辅助函数
$user = cache_kv_get('user', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
});

echo "用户信息: " . json_encode($user);
```

### 快速创建（适合简单场景）

```php
// 一行代码创建独立实例
$cache = cache_kv_quick('myapp', 'dev', [
    'user' => 'user:{id}',
    'product' => 'product:{id}',
]);

// 直接使用
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

## 🚀 核心功能

### 1. 自动回填缓存

```php
// 缓存存在：直接返回缓存数据
// 缓存不存在：执行回调函数获取数据，自动写入缓存后返回
$product = $cache->getByTemplate('product', ['id' => 1], function() {
    return getProductFromDatabase(1);
});
```

### 2. 批量操作

```php
$userIds = [1, 2, 3, 4, 5];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

// 自动处理：部分命中缓存，部分从数据源获取
$users = $cache->getMultiple($userKeys, function($missingKeys) {
    return getUsersFromDatabase($missingKeys);
});
```

### 3. 标签管理

```php
// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 1], $userData, ['users', 'vip_users']);

// 批量清除：一次清除所有用户相关缓存
$cache->clearTag('users');
```

### 4. 统一键管理

```php
// 标准化的键生成：myapp:prod:v1:user:123
$userKey = $keyManager->make('user', ['id' => 123]);

// 环境隔离：开发、测试、生产环境自动隔离
// 版本管理：数据结构变更时版本号隔离
```

## 📊 性能提升

| 场景 | 传统方案 | CacheKV 方案 | 性能提升 |
|------|----------|--------------|----------|
| 单条查询 | 每次查数据库 | 缓存命中直接返回 | **10-100x** |
| 批量查询 | N次数据库查询 | 1次批量查询 | **10-1000x** |
| 相关缓存清理 | 手动逐个清除 | 标签批量清除 | **维护性大幅提升** |

## 🔧 驱动支持

### Redis 驱动（生产环境推荐）

CacheKV 不依赖特定的 Redis 客户端库，您可以使用任何 Redis 客户端：

#### 使用 Predis
```bash
composer require predis/predis
```

```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 创建 Predis 客户端
$redis = new \Predis\Client(['host' => '127.0.0.1', 'port' => 6379]);

// 注入到 RedisDriver
$driver = new RedisDriver($redis);
$cache = new CacheKV($driver, 3600, $keyManager);
```

#### 使用 PhpRedis 扩展
```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 创建 PhpRedis 客户端
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

// 注入到 RedisDriver
$driver = new RedisDriver($redis);
$cache = new CacheKV($driver, 3600, $keyManager);
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
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 创建 Redis 实例
$redis = new \Predis\Client(['host' => '127.0.0.1', 'port' => 6379]);

// 注册服务
CacheKVServiceProvider::register([
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => new RedisDriver($redis)
        ]
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

## 📚 文档

- [快速开始](docs/quick-start.md) - 5分钟上手指南
- [核心功能](docs/core-features.md) - 详细功能介绍
- [API 参考](docs/api-reference.md) - 完整的 API 文档
- [实战案例](docs/examples.md) - 真实业务场景应用

## 🌟 实际应用场景

### 用户信息缓存
```php
// 获取用户信息，自动处理缓存逻辑
$user = cache_kv_get('user', ['id' => $userId], function() use ($userId) {
    return getUserFromDatabase($userId);
});

// 更新用户后清除相关缓存
updateUserInDatabase($userId, $data);
cache_kv_clear_tag('users'); // 清除所有用户相关缓存
```

### 商品信息批量缓存
```php
// 批量获取商品，自动优化：部分从缓存，部分从数据库
$productIds = [1, 2, 3, 4, 5];
$productKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('product', ['id' => $id]);
}, $productIds);

$products = $cache->getMultiple($productKeys, function($missingKeys) {
    return getProductsFromDatabase($missingKeys);
});
```

### API 响应缓存
```php
// 缓存外部 API 响应，避免频繁调用
$weather = cache_kv_get('api_weather', ['city' => $city], function() use ($city) {
    return callWeatherAPI($city);
}, 1800); // 30分钟缓存
```

### 模板名称管理优化
```php
// 问题：硬编码模板名称 'user' 难以维护和修改

// 1. 常量定义方式（推荐）
class CacheTemplates {
    const USER = 'user_profile';
    const PRODUCT = 'product_info';
    const ORDER = 'order_detail';
}

// 使用常量，便于统一管理和修改
$user = $cache->getByTemplate(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 2. 配置文件方式
// config/cache_templates.php
return [
    'user' => 'user_profile',
    'product' => 'product_info',
    'order' => 'order_detail',
];

// 使用配置
$templates = require 'config/cache_templates.php';
$user = $cache->getByTemplate($templates['user'], ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 3. 枚举类方式（PHP 8.1+）
enum CacheTemplate: string {
    case USER = 'user_profile';
    case PRODUCT = 'product_info';
    case ORDER = 'order_detail';
}

$user = $cache->getByTemplate(CacheTemplate::USER->value, ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 4. 辅助函数封装
function getUserCache($userId, $callback) {
    return cache_kv_get(CacheTemplates::USER, ['id' => $userId], $callback);
}

function getProductCache($productId, $callback) {
    return cache_kv_get(CacheTemplates::PRODUCT, ['id' => $productId], $callback);
}

// 使用封装函数
$user = getUserCache(123, function() {
    return getUserFromDatabase(123);
});
```

### 缓存 Key 重命名迁移
```php
// 场景：需要将模板名从 'user' 改为 'user_profile'

// 1. 版本管理方式（推荐）
CacheKVFactory::setDefaultConfig([
    'key_manager' => [
        'version' => 'v2', // 升级版本号，自动隔离新旧缓存
        'templates' => [
            'user_profile' => 'user:{id}', // 新的模板名
        ]
    ]
]);

// 更新常量定义
class CacheTemplates {
    const USER = 'user_profile'; // 从 'user' 改为 'user_profile'
}

// 2. 平滑迁移方式
function migrateTemplateNames() {
    $oldTemplate = 'user';
    $newTemplate = 'user_profile';
    
    // 获取所有旧模板的缓存
    $oldPattern = "myapp:prod:v1:{$oldTemplate}:*";
    $oldKeys = $cache->keys($oldPattern);
    
    foreach ($oldKeys as $oldKey) {
        $userId = extractUserIdFromKey($oldKey);
        
        // 使用新模板名生成新 key
        $newKey = $keyManager->make($newTemplate, ['id' => $userId]);
        
        $data = $cache->get($oldKey);
        $cache->set($newKey, $data);
        $cache->delete($oldKey);
    }
}

// 3. 兼容性处理（过渡期使用）
function getWithFallback($newTemplate, $oldTemplate, $params, $callback) {
    // 先尝试新模板
    $data = $cache->getByTemplate($newTemplate, $params, null);
    if ($data !== null) {
        return $data;
    }
    
    // 回退到旧模板
    $data = $cache->getByTemplate($oldTemplate, $params, $callback);
    if ($data !== null) {
        // 同时写入新模板缓存
        $cache->setByTemplate($newTemplate, $params, $data);
        // 删除旧模板缓存
        $cache->deleteByTemplate($oldTemplate, $params);
    }
    
    return $data;
}

// 使用兼容性处理
$user = getWithFallback('user_profile', 'user', ['id' => 123], function() {
    return getUserFromDatabase(123);
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

## 🏆 适用场景

- **用户管理系统** - 用户信息、权限、会话缓存
- **电商平台** - 商品信息、价格、库存缓存
- **内容管理系统** - 文章、评论、分类缓存
- **API 服务** - 外部 API 响应缓存
- **数据分析平台** - 统计数据、报表缓存

## 🤝 贡献

欢迎为 CacheKV 做出贡献！请查看我们的贡献指南了解如何参与。

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

**开始您的高效缓存之旅！** 🚀
