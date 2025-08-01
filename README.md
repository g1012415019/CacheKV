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
$data = cache_kv_get('data_item', ['id' => 123], function() {
    return getDataFromDatabase(123); // 只在缓存未命中时执行
});
```

## ⚡ 快速开始

### 安装

```bash
composer require asfop/cache-kv
```

### 30秒上手

```php
<?php
require_once 'vendor/autoload.php';

// 零配置，直接使用
$data = cache_kv_get('data_item', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'Sample Data', 'value' => 'sample_value'];
});

echo "数据信息: " . json_encode($data);
```

### 推荐配置（一行搞定）

```php
// 一次配置，全局使用
cache_kv_config([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'templates' => [
        'item' => 'item:{id}',
        'record' => 'record:{type}:{id}',
        'content' => 'content:{category}:{id}',
    ]
]);

// 然后在任何地方直接使用
$item = cache_kv_get('item', ['id' => 123], function() {
    return getItemFromDatabase(123);
});

$record = cache_kv_get('record', ['type' => 'log', 'id' => 456], function() {
    return getRecordFromDatabase('log', 456);
});
```

## 🚀 核心功能

### 1. 自动回填缓存

```php
// 缓存存在：直接返回缓存数据
// 缓存不存在：执行回调函数获取数据，自动写入缓存后返回
$item = cache_kv_get('item', ['id' => 1], function() {
    return getItemFromDatabase(1);
});
```

### 2. 批量操作优化

```php
$cache = cache_kv_instance();
$itemIds = [1, 2, 3, 4, 5];
$itemKeys = array_map(function($id) use ($cache) {
    return $cache->makeKey('item', ['id' => $id]);
}, $itemIds);

// 自动处理：部分命中缓存，部分从数据源获取
$items = $cache->getMultiple($itemKeys, function($missingKeys) {
    return getItemsFromDatabase($missingKeys);
});
```

### 3. 标签管理

```php
$cache = cache_kv_instance();

// 设置带标签的缓存
$cache->setWithTag('item:1', $itemData, ['items', 'active_items']);

// 批量清除：一次清除所有相关缓存
cache_kv_clear_tag('items');
```

### 4. 统一键管理

```php
$cache = cache_kv_instance();

// 标准化的键生成：myapp:prod:v1:item:123
$itemKey = $cache->makeKey('item', ['id' => 123]);

// 环境隔离：开发、测试、生产环境自动隔离
// 版本管理：数据结构变更时版本号隔离
```

## 🔧 配置方式

### 方式1：零配置使用（最简单）

```php
// 无需任何配置，直接使用
$data = cache_kv_get('data_item', ['id' => 123], function() {
    return getDataFromDatabase(123);
});
```

### 方式2：全局配置（推荐）

```php
// 一次配置，全局使用
cache_kv_config([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'ttl' => 3600,
    'templates' => [
        'item' => 'item:{id}',
        'record' => 'record:{type}:{id}',
        'content' => 'content:{category}:{id}',
    ]
]);

// 然后在任何地方直接使用
$item = cache_kv_get('item', ['id' => 123], function() {
    return getItemFromDatabase(123);
});
```

### 方式3：独立实例（多实例场景）

```php
use Asfop\CacheKV\CacheKVFactory;

// 服务A缓存
$serviceACache = CacheKVFactory::quick([
    'entity' => 'entity:{id}',
    'relation' => 'relation:{from}:{to}',
], [
    'app_prefix' => 'service-a',
    'ttl' => 1800
]);

// 服务B缓存
$serviceBCache = CacheKVFactory::quick([
    'resource' => 'resource:{id}',
    'metadata' => 'meta:{resource_id}',
], [
    'app_prefix' => 'service-b',
    'ttl' => 3600
]);
```

## 🔧 驱动支持

### Redis 驱动（生产环境推荐）

```bash
composer require predis/predis
```

```php
cache_kv_config([
    'driver' => new \Asfop\CacheKV\Cache\Drivers\RedisDriver(
        new \Predis\Client(['host' => '127.0.0.1', 'port' => 6379])
    ),
    'app_prefix' => 'myapp',
    'templates' => [
        'item' => 'item:{id}',
        'record' => 'record:{type}:{id}',
    ]
]);
```

### Array 驱动（开发测试，默认）

```php
// 默认使用 Array 驱动，无需额外配置
$data = cache_kv_get('data_item', ['id' => 123], function() {
    return getDataFromDatabase(123);
});
```

## 🎨 实际应用场景

### 数据项缓存
```php
function getDataItem($itemId) {
    return cache_kv_get('item', ['id' => $itemId], function() use ($itemId) {
        return getItemFromDatabase($itemId);
    });
}

// 使用
$item = getDataItem(123);
```

### API 响应缓存
```php
function getApiResult($endpoint, $params) {
    $paramsHash = md5(json_encode($params));
    return cache_kv_get('api_result', ['endpoint' => $endpoint, 'params_hash' => $paramsHash], function() use ($endpoint, $params) {
        return callExternalAPI($endpoint, $params);
    }, 1800); // 30分钟缓存
}

// 使用
$result = getApiResult('data_service', ['type' => 'list']);
```

### 计算结果缓存
```php
function getCalculationResult($params) {
    $key = md5(json_encode($params));
    return cache_kv_get('calculation', ['key' => $key], function() use ($params) {
        // 复杂计算
        return performExpensiveCalculation($params);
    }, 3600); // 1小时缓存
}
```

## 🔄 框架集成

### Laravel 集成

```php
// 在 AppServiceProvider 的 boot 方法中
public function boot()
{
    cache_kv_config([
        'driver' => new \Asfop\CacheKV\Cache\Drivers\RedisDriver(
            app('redis')->connection()
        ),
        'app_prefix' => env('APP_NAME', 'laravel'),
        'env_prefix' => env('APP_ENV', 'production'),
        'templates' => config('cache.templates', [])
    ]);
}
```

### 其他框架

```php
// 在应用启动时配置
cache_kv_config([
    'app_prefix' => 'myapp',
    'env_prefix' => getenv('APP_ENV') ?: 'production',
    'templates' => [
        'item' => 'item:{id}',
        'record' => 'record:{type}:{id}',
    ]
]);
```

## 📊 性能提升

| 场景 | 传统方案 | CacheKV 方案 | 性能提升 |
|------|----------|--------------|----------|
| 单条查询 | 每次查数据库 | 缓存命中直接返回 | **10-100x** |
| 批量查询 | N次数据库查询 | 1次批量查询 | **10-1000x** |
| 相关缓存清理 | 手动逐个清除 | 标签批量清除 | **维护性大幅提升** |
| 键名管理 | 字符串硬编码 | 模板统一管理 | **可维护性大幅提升** |

## 📈 核心优势

### ✅ 极简使用
- **零配置**：直接使用，无需任何配置
- **一行配置**：全局配置，一次设置处处使用
- **无重复代码**：辅助函数封装，避免重复

### ✅ 自动化
- **自动回填**：缓存未命中自动从数据源获取
- **智能批量**：自动优化批量操作，避免 N+1 查询
- **防穿透**：自动缓存空值，防止缓存穿透

### ✅ 维护性
- **标签管理**：相关缓存批量管理
- **环境隔离**：开发、测试、生产环境自动隔离
- **版本管理**：支持数据结构升级和版本迁移

### ✅ 灵活性
- **多种配置方式**：从零配置到完全自定义
- **多实例支持**：支持微服务架构
- **框架友好**：完美集成各种 PHP 框架
- **无业务耦合**：通用设计，适用于任何业务场景

## 🏆 适用场景

- **数据管理系统** - 各种数据项、记录、内容缓存
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
