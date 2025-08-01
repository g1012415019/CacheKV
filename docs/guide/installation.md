# 安装和配置

本指南将帮助您在 5 分钟内完成 CacheKV 的安装和基本配置。

## 系统要求

- **PHP 版本**：7.0 或更高版本
- **内存**：建议至少 64MB 可用内存
- **扩展**：无特殊要求（Redis 驱动需要 Redis 扩展）

## 安装方式

### 方式一：Composer 安装（推荐）

```bash
composer require asfop/cache-kv
```

### 方式二：手动下载

1. 从 [GitHub Releases](https://github.com/asfop1/CacheKV/releases) 下载最新版本
2. 解压到项目目录
3. 引入 autoload 文件：

```php
require_once 'path/to/cachekv/vendor/autoload.php';
```

## 快速配置

### 基本配置

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 1. 配置键管理器
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',        // 应用名称
    'env_prefix' => 'prod',         // 环境标识
    'version' => 'v1',              // 版本号
]);

// 2. 创建缓存实例
$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 3. 开始使用
$data = $cache->getByTemplate('user', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John Doe'];
});

echo "用户数据: " . json_encode($data);
```

### 生产环境配置

```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// Redis 驱动配置
RedisDriver::setRedisFactory(function() {
    $redis = new \Predis\Client([
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'password' => null, // 如果有密码
    ]);
    return $redis;
});

// 生产环境键管理器
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // 定义业务模板
        'user' => 'user:{id}',
        'product' => 'product:{id}',
        'order' => 'order:{id}',
    ]
]);

// 创建生产环境缓存实例
$cache = new CacheKV(new RedisDriver(), 3600, $keyManager);
```

## 配置选项详解

### KeyManager 配置

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `app_prefix` | string | 'app' | 应用前缀，用于区分不同应用 |
| `env_prefix` | string | 'prod' | 环境前缀（dev/test/prod） |
| `version` | string | 'v1' | 版本号，用于版本隔离 |
| `separator` | string | ':' | 键分隔符 |
| `templates` | array | [] | 自定义键模板 |

### CacheKV 配置

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `driver` | CacheDriver | 必需 | 缓存驱动实例 |
| `defaultTtl` | int | 3600 | 默认过期时间（秒） |
| `keyManager` | KeyManager | null | 键管理器实例 |

## 驱动选择

### ArrayDriver（开发测试）

```php
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

$driver = new ArrayDriver();
$cache = new CacheKV($driver, 3600, $keyManager);
```

**特点：**
- ✅ 无需外部依赖
- ✅ 启动速度快
- ❌ 数据不持久化
- ❌ 仅限单进程使用

### RedisDriver（生产推荐）

```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 配置 Redis 连接
RedisDriver::setRedisFactory(function() {
    return new \Predis\Client([
        'host' => 'redis.example.com',
        'port' => 6379,
        'database' => 1,
        'timeout' => 5.0,
    ]);
});

$driver = new RedisDriver();
$cache = new CacheKV($driver, 3600, $keyManager);
```

**特点：**
- ✅ 数据持久化
- ✅ 支持分布式
- ✅ 高性能
- ❌ 需要 Redis 服务器

## 环境配置示例

### 开发环境

```php
// config/cache_dev.php
return [
    'driver' => 'array',
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'dev',
        'version' => 'v1',
    ],
    'default_ttl' => 600, // 10分钟，便于调试
];
```

### 测试环境

```php
// config/cache_test.php
return [
    'driver' => 'redis',
    'redis' => [
        'host' => 'test-redis.internal',
        'database' => 2,
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'test',
        'version' => 'v1',
    ],
    'default_ttl' => 1800, // 30分钟
];
```

### 生产环境

```php
// config/cache_prod.php
return [
    'driver' => 'redis',
    'redis' => [
        'host' => 'prod-redis.internal',
        'port' => 6379,
        'database' => 0,
        'password' => env('REDIS_PASSWORD'),
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            // 生产环境的完整模板定义
            'user' => 'user:{id}',
            'user_profile' => 'user:profile:{id}',
            'product' => 'product:{id}',
            'product_detail' => 'product:detail:{id}',
            'order' => 'order:{id}',
            'api_response' => 'api:{service}:{params_hash}',
        ]
    ],
    'default_ttl' => 3600, // 1小时
];
```

## 验证安装

创建一个简单的测试文件来验证安装：

```php
<?php
// test_installation.php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

try {
    // 创建实例
    $keyManager = new KeyManager([
        'app_prefix' => 'test',
        'env_prefix' => 'dev',
        'version' => 'v1',
    ]);
    
    $cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);
    
    // 测试基本功能
    $testData = $cache->getByTemplate('test', ['id' => 1], function() {
        return ['message' => 'CacheKV 安装成功！', 'timestamp' => time()];
    });
    
    echo "✅ 安装验证成功！\n";
    echo "测试数据: " . json_encode($testData) . "\n";
    
    // 测试缓存命中
    $cachedData = $cache->getByTemplate('test', ['id' => 1], function() {
        return ['message' => '这不应该被执行'];
    });
    
    if ($cachedData['message'] === 'CacheKV 安装成功！') {
        echo "✅ 缓存功能正常！\n";
    }
    
} catch (Exception $e) {
    echo "❌ 安装验证失败: " . $e->getMessage() . "\n";
}
```

运行测试：

```bash
php test_installation.php
```

## 常见问题

### Q: 如何选择合适的驱动？

**A:** 
- **开发阶段**：使用 ArrayDriver，简单快速
- **生产环境**：使用 RedisDriver，支持持久化和分布式

### Q: 键前缀有什么作用？

**A:** 键前缀用于：
- **应用隔离**：不同应用使用不同的 app_prefix
- **环境隔离**：开发、测试、生产环境使用不同的 env_prefix
- **版本管理**：数据结构变更时使用不同的 version

### Q: 如何处理 Redis 连接失败？

**A:** 
```php
try {
    RedisDriver::setRedisFactory(function() {
        $redis = new \Predis\Client(['host' => 'redis.example.com']);
        $redis->ping(); // 测试连接
        return $redis;
    });
} catch (Exception $e) {
    // 降级到 ArrayDriver
    $driver = new ArrayDriver();
}
```

## 下一步

安装完成后，建议您：

1. 阅读 [基础概念](concepts.md) 了解核心概念
2. 查看 [第一个示例](first-example.md) 编写第一个程序
3. 学习 [核心功能](core-features.md) 掌握主要特性

---

**恭喜！您已经成功安装了 CacheKV！** 🎉
