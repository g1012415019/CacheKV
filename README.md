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
$data = cache_kv_get('user.profile', ['id' => 123], function() {
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
$user = cache_kv_get('user.profile', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John', 'email' => 'john@example.com'];
});

// 批量数据获取
$users = cache_kv_get_multiple('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
], function($missedKeys) {
    return batchGetUsersFromDatabase($missedKeys);
});
```

## 🚀 核心功能

- **自动回填缓存**：缓存未命中时自动执行回调并缓存结果
- **批量操作优化**：高效的批量获取，避免N+1查询问题
- **热点键自动续期**：自动检测并延长热点数据的缓存时间
- **统计监控**：实时统计命中率、热点键等性能指标
- **统一键管理**：标准化键生成，支持环境隔离和版本管理

## 📊 统计功能

```php
// 获取统计信息
$stats = cache_kv_get_stats();
// ['hits' => 1500, 'misses' => 300, 'hit_rate' => '83.33%', ...]

// 获取热点键
$hotKeys = cache_kv_get_hot_keys(10);
// ['user:profile:123' => 45, 'user:profile:456' => 32, ...]
```

## 🔧 配置示例

```php
// config/cache_kv.php
return array(
    'cache' => array(
        'ttl' => 3600,                          // 默认缓存时间
        'enable_stats' => true,                 // 启用统计
        'stats_prefix' => 'cachekv:stats:',     // 统计数据Redis键前缀
        'stats_ttl' => 604800,                  // 统计数据TTL（7天）
        'hot_key_auto_renewal' => true,         // 启用热点键自动续期
        'hot_key_threshold' => 100,             // 热点键阈值
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
                        'settings' => array('template' => 'settings:{id}'),
                    ),
                ),
            ),
        ),
    ),
);
```

## 📚 文档

- **[完整文档](docs/README.md)** - 详细的配置和架构说明
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
