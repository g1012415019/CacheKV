# CacheKV

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/asfop1/cache-kv.svg)](https://packagist.org/packages/asfop1/cache-kv)
[![Packagist Downloads](https://img.shields.io/packagist/dt/asfop1/cache-kv.svg)](https://packagist.org/packages/asfop1/cache-kv)
[![GitHub Stars](https://img.shields.io/github/stars/asfop1/CacheKV.svg)](https://github.com/asfop1/CacheKV/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/asfop1/CacheKV.svg)](https://github.com/asfop1/CacheKV/issues)

## 🎯 核心价值

**CacheKV 让缓存操作变得简单：**
```php
// 一行代码搞定：检查缓存 → 未命中则获取数据 → 自动回填缓存
$data = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
});
```

**解决的痛点：**
- ❌ 手动检查缓存是否存在
- ❌ 缓存未命中时手动从数据源获取
- ❌ 手动将获取的数据写入缓存
- ❌ 批量操作时的复杂逻辑处理

## ⚡ 快速开始

### 安装

```bash
composer require asfop1/cache-kv
```

### 基础使用

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 配置Redis连接
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});

// 单个数据获取
$user = kv_get('user.profile', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John', 'email' => 'john@example.com'];
});

// 批量数据获取
$users = kv_get_multi('user.profile', [
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

// 批量获取键对象（不执行缓存操作）
$keys = kv_get_keys('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
]);

// 检查键配置
foreach ($keys as $keyString => $keyObj) {
    echo "键: {$keyString}, 有缓存配置: " . ($keyObj->hasCacheConfig() ? '是' : '否') . "\n";
}
```

## 🚀 核心功能

- **自动回填缓存**：缓存未命中时自动执行回调并缓存结果
- **批量操作优化**：高效的批量获取，避免N+1查询问题
- **按前缀删除**：支持按键前缀批量删除缓存，相当于按 tag 删除
- **热点键自动续期**：自动检测并延长热点数据的缓存时间
- **统计监控**：实时统计命中率、热点键等性能指标
- **统一键管理**：标准化键生成，支持环境隔离和版本管理

## 📊 统计功能

```php
// 获取统计信息
$stats = kv_stats();
// ['hits' => 1500, 'misses' => 300, 'hit_rate' => '83.33%', ...]

// 获取热点键
$hotKeys = kv_hot_keys(10);
// ['user:profile:123' => 45, 'user:profile:456' => 32, ...]
```

## ✨ 简洁API设计

CacheKV 提供了简洁易用的函数名，同时保持向后兼容：

### 🔧 核心操作
```php
kv_get($template, $params, $callback, $ttl)      // 获取缓存
kv_get_multi($template, $paramsList, $callback)  // 批量获取
```

### 🗝️ 键管理
```php
kv_key($template, $params)           // 创建键字符串
kv_keys($template, $paramsList)      // 批量创建键
kv_get_keys($template, $paramsList)  // 获取键对象
```

### 🗑️ 删除操作
```php
kv_delete_prefix($template, $params)  // 按前缀删除
kv_delete_full($prefix)               // 按完整前缀删除
```

### 📊 统计功能
```php
kv_stats()              // 获取统计信息
kv_hot_keys($limit)     // 获取热点键
kv_clear_stats()        // 清空统计
```

### ⚙️ 配置管理
```php
kv_config()     // 获取完整配置信息
```

### 🔄 向后兼容
所有原有函数名仍然可用：
```php
// 新版本（推荐）
$user = kv_get('user.profile', ['id' => 123], $callback);

// 旧版本（仍然支持）
$user = cache_kv_get('user.profile', ['id' => 123], $callback);
```

## 📚 文档

- **[完整文档](docs/README.md)** - 详细的配置、架构和使用说明 ⭐
- **[快速开始](docs/QUICK_START.md)** - 5分钟快速上手指南
- **[配置参考](docs/CONFIG.md)** - 所有配置选项的详细说明
- **[统计功能](docs/STATS.md)** - 性能监控和热点键管理
- **[API 参考](docs/API.md)** - 完整的API文档
- **[更新日志](CHANGELOG.md)** - 版本更新记录

## 🏆 适用场景

- **Web 应用** - 用户数据、页面内容缓存
- **API 服务** - 接口响应、计算结果缓存
- **电商平台** - 商品信息、价格、库存缓存
- **数据分析** - 统计数据、报表缓存

## 📋 系统要求

- PHP >= 7.0
- Redis 扩展

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

**开始您的高效缓存之旅！** 🚀

> 💡 **提示：** 查看 [完整文档](docs/README.md) 了解详细的配置和高级用法
