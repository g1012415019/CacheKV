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
$data = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
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
$templates = [
    ['template' => 'user.profile', 'params' => ['id' => 1]],
    ['template' => 'user.profile', 'params' => ['id' => 2]],
    ['template' => 'user.profile', 'params' => ['id' => 3]]
];

$users = cache_kv_get_multiple($templates, function($missedKeys) {
    return getUsersFromDatabase($missedKeys);
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

### 5. 性能监控
```php
$stats = cache_kv_get_stats();
// 输出：['hit_rate' => '85%', 'total_requests' => 1000, ...]

$hotKeys = cache_kv_get_hot_keys();
// 获取访问频率最高的缓存键
```

## 📚 文档

- **[完整文档](docs/README.md)** - 详细的配置和架构说明
- **[快速开始](docs/QUICK_START.md)** - 5分钟快速上手指南
- **[配置参考](docs/CONFIG.md)** - 所有配置选项的详细说明
- **[统计功能](docs/STATS.md)** - 性能监控和热点键管理
- **[API 参考](docs/API.md)** - 完整的API文档

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
    $templates = [];
    foreach ($userIds as $id) {
        $templates[] = ['template' => 'user.profile', 'params' => ['id' => $id]];
    }
    
    return cache_kv_get_multiple($templates, function($missedKeys) {
        // 批量从数据库获取未命中的用户
        return batchGetUsersFromDatabase($missedKeys);
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

欢迎为 CacheKV 做出贡献！请查看我们的贡献指南了解如何参与。

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

**开始您的高效缓存之旅！** 🚀
