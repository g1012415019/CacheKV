# CacheKV

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/asfop1/cache-kv.svg)](https://packagist.org/packages/asfop1/cache-kv)
[![Packagist Downloads](https://img.shields.io/packagist/dt/asfop1/cache-kv.svg)](https://packagist.org/packages/asfop1/cache-kv)
[![GitHub Stars](https://img.shields.io/github/stars/asfop1/CacheKV.svg)](https://github.com/asfop1/CacheKV/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/asfop1/CacheKV.svg)](https://github.com/asfop1/CacheKV/issues)

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
$data = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
});
```

## ⚡ 快速开始

### 安装

```bash
composer require asfop1/cache-kv
```

### 30秒上手

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 一行配置，开箱即用
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    '/path/to/config.php' // 配置文件路径（可选）
);

// 使用缓存
$userData = cache_kv_get('user.profile', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John', 'email' => 'john@example.com'];
});

echo json_encode($userData);
```

## 🚀 核心功能

### 1. 自动回填缓存
```php
// 缓存存在：直接返回缓存数据
// 缓存不存在：执行回调函数获取数据，自动写入缓存后返回
$item = cache_kv_get('user.profile', ['id' => 1], function() {
    return getUserFromDatabase(1);
});
```

### 2. 批量操作优化
```php
// 🔥 简洁的批量操作API

// 1. 简单参数批量获取
$users = cache_kv_get_multiple('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
], function($missedKeys) {
    // $missedKeys 是 CacheKey 对象数组
    // 方式1：返回关联数组（键字符串 => 数据）
    $results = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $results[$keyString] = getUserFromDatabase($cacheKey);
    }
    return $results;
    
    // 方式2：返回索引数组（按顺序对应）
    // return [
    //     getUserFromDatabase($missedKeys[0]),
    //     getUserFromDatabase($missedKeys[1]),
    //     getUserFromDatabase($missedKeys[2])
    // ];
});

// 2. 复杂参数批量获取
$reports = cache_kv_get_multiple('report.daily', [
    ['id' => 1, 'ymd' => '20240804', 'uid' => 123, 'sex' => 'M'],
    ['id' => 2, 'ymd' => '20240804', 'uid' => 456, 'sex' => 'F'],
    ['id' => 3, 'ymd' => '20240805', 'uid' => 789, 'sex' => 'M']
], function($missedKeys) {
    // 批量查询数据库
    return getReportsFromDatabase($missedKeys);
});
```

### 3. 热点键自动续期
```php
// 系统自动检测热点数据并延长缓存时间
// 无需手动干预，热点数据永不过期
```

### 4. 统一键管理
```php
// 标准化的键生成：myapp:user:v1:profile:123
$key = cache_kv_make_key('user.profile', ['id' => 123]);

// 环境隔离：开发、测试、生产环境自动隔离
// 版本管理：数据结构变更时版本号隔离
```

### 5. 持久化统计监控
```php
// 获取统计信息（持久化数据）
$stats = cache_kv_get_stats();
// 输出：['hits' => 1500, 'misses' => 300, 'hit_rate' => '83.33%', ...]

// 获取热点键（持久化数据）
$hotKeys = cache_kv_get_hot_keys(10);
// 获取访问频率最高的10个缓存键

// 强制同步统计数据到Redis
cache_kv_sync_stats();

// 清空所有统计数据
cache_kv_clear_stats();
```

**持久化特性：**
- ✅ **数据持久化**：统计数据保存到Redis，应用重启不丢失
- ✅ **分布式支持**：多个应用实例共享统计数据
- ✅ **自动同步**：每60秒自动同步内存数据到Redis
- ✅ **优雅降级**：Redis故障时不影响主要功能
- ✅ **数据保留**：统计数据在Redis中保存7天
```

### 5. 持久化统计监控
```php
// 获取统计信息（持久化数据）
$stats = cache_kv_get_stats();
// 输出：['hits' => 1500, 'misses' => 300, 'hit_rate' => '83.33%', ...]

// 获取热点键（持久化数据）
$hotKeys = cache_kv_get_hot_keys(10);
// 获取访问频率最高的10个缓存键

// 强制同步统计数据到Redis
cache_kv_sync_stats();

// 清空所有统计数据
cache_kv_clear_stats();
```

**持久化特性：**
- ✅ **数据持久化**：统计数据保存到Redis，应用重启不丢失
- ✅ **分布式支持**：多个应用实例共享统计数据
- ✅ **自动同步**：每60秒自动同步内存数据到Redis
- ✅ **优雅降级**：Redis故障时不影响主要功能
- ✅ **数据保留**：统计数据在Redis中保存7天

## 📚 文档

- **[完整文档](docs/README.md)** - 详细的配置和架构说明
- **[快速开始](docs/QUICK_START.md)** - 5分钟快速上手指南
- **[配置参考](docs/CONFIG.md)** - 所有配置选项的详细说明
- **[统计功能](docs/STATS.md)** - 性能监控和热点键管理
- **[API 参考](docs/API.md)** - 完整的API文档
- **[更新日志](CHANGELOG.md)** - 版本更新记录

## 🔧 配置示例

### 基础配置
```php
// config/cache_kv.php
return array(
    'cache' => array(
        'ttl' => 3600,                          // 默认缓存时间
        'enable_stats' => true,                 // 启用统计
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

## 🎨 实际应用场景

### 用户数据缓存
```php
function getUserProfile($userId) {
    return cache_kv_get('user.profile', ['id' => $userId], function() use ($userId) {
        return getUserFromDatabase($userId);
    });
}
```

### API 响应缓存
```php
function getApiResult($endpoint, $params) {
    return cache_kv_get('api.result', [
        'endpoint' => $endpoint,
        'hash' => md5(json_encode($params))
    ], function() use ($endpoint, $params) {
        return callExternalAPI($endpoint, $params);
    }, 1800); // 30分钟缓存
}
```

### 批量数据获取
```php
function getUserProfiles($userIds) {
    // 构建参数数组
    $paramsList = [];
    foreach ($userIds as $id) {
        $paramsList[] = ['id' => $id];
    }
    
    return cache_kv_get_multiple('user.profile', $paramsList, function($missedKeys) {
        // 批量从数据库获取未命中的用户
        return batchGetUsersFromDatabase($missedKeys);
    });
}

function getDailyReports($reportParams) {
    // 复杂参数批量获取
    return cache_kv_get_multiple('report.daily', $reportParams, function($missedKeys) {
        // $reportParams = [
        //     ['id' => 1, 'ymd' => '20240804', 'uid' => 123, 'sex' => 'M'],
        //     ['id' => 2, 'ymd' => '20240804', 'uid' => 456, 'sex' => 'F']
        // ]
        return batchGetReportsFromDatabase($missedKeys);
    });
}
```

## 📈 核心优势

### ✅ 极简使用
- **零配置**：直接使用，无需复杂配置
- **一行配置**：全局配置，一次设置处处使用
- **无重复代码**：辅助函数封装，避免重复

### ✅ 自动化
- **自动回填**：缓存未命中自动从数据源获取
- **智能批量**：自动优化批量操作，避免 N+1 查询
- **热点续期**：热点数据自动延长缓存时间

### ✅ 可观测性
- **命中率统计**：实时监控缓存性能
- **热点检测**：识别高频访问的数据
- **性能报告**：详细的统计和分析

### ✅ 维护性
- **环境隔离**：开发、测试、生产环境自动隔离
- **版本管理**：支持数据结构升级和版本迁移
- **统一管理**：标准化的键命名和配置管理

## 🏆 适用场景

- **Web 应用** - 用户数据、页面内容缓存
- **API 服务** - 接口响应、计算结果缓存
- **电商平台** - 商品信息、价格、库存缓存
- **内容管理** - 文章、评论、分类缓存
- **数据分析** - 统计数据、报表缓存
- **微服务架构** - 服务间数据缓存

## 📋 系统要求

- PHP >= 7.0
- Redis 扩展（推荐）

## 🤝 贡献

欢迎为 CacheKV 做出贡献！请查看我们的 [贡献指南](CONTRIBUTING.md) 了解如何参与。

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

**开始您的高效缓存之旅！** 🚀
