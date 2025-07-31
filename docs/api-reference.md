# CacheKV API 参考文档

## 概述

本文档详细描述了 CacheKV 库的所有公共 API，包括方法签名、参数说明、返回值和使用示例。

## CacheKV 类

### 构造方法

#### `__construct(CacheDriver $driver, $defaultTtl = 3600)`

创建 CacheKV 实例。

**参数：**
- `$driver` (CacheDriver): 缓存驱动实例
- `$defaultTtl` (int): 默认缓存过期时间（秒），默认 3600

**示例：**
```php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

$cache = new CacheKV(new ArrayDriver(), 7200);
```

### 核心方法

#### `get($key, $callback = null, $ttl = null)`

获取缓存值，支持自动回填。

**参数：**
- `$key` (string): 缓存键名
- `$callback` (callable|null): 缓存未命中时的回调函数
- `$ttl` (int|null): 缓存过期时间，null 使用默认值

**返回值：**
- `mixed|null`: 缓存的值，如果不存在且无回调则返回 null

**特性：**
- 缓存命中时自动延长过期时间（滑动过期）
- 缓存未命中时执行回调并自动回填
- 即使回调返回 null 也会被缓存（防止缓存穿透）

**示例：**
```php
// 简单获取
$value = $cache->get('simple_key');

// 带回调的获取
$user = $cache->get('user:123', function() {
    return getUserFromDatabase(123);
});

// 指定 TTL
$data = $cache->get('temp_data', function() {
    return fetchTempData();
}, 300); // 5分钟过期
```

#### `set($key, $value, $ttl = null)`

设置缓存值。

**参数：**
- `$key` (string): 缓存键名
- `$value` (mixed): 要缓存的值
- `$ttl` (int|null): 缓存过期时间，null 使用默认值

**返回值：**
- `bool`: 操作是否成功

**示例：**
```php
// 使用默认 TTL
$cache->set('user:123', $userData);

// 指定 TTL
$cache->set('session:abc', $sessionData, 1800);

// 缓存复杂数据
$cache->set('config', [
    'app_name' => 'MyApp',
    'version' => '1.0.0'
]);
```

#### `getMultiple($keys, $callback, $ttl = null)`

批量获取缓存值，支持智能回填。

**参数：**
- `$keys` (array): 缓存键名数组
- `$callback` (callable): 回调函数，接收未命中的键数组，返回键值对数组
- `$ttl` (int|null): 新缓存的过期时间

**返回值：**
- `array`: 键值对数组，包含所有请求的数据

**回调函数签名：**
```php
function(array $missingKeys): array
```

**示例：**
```php
$userIds = [1, 2, 3, 4, 5];

$users = $cache->getMultiple($userIds, function($missingIds) {
    // 只查询缓存中不存在的用户
    $users = getUsersByIds($missingIds);
    
    // 返回格式：['key' => 'value', ...]
    $result = [];
    foreach ($users as $user) {
        $result[$user['id']] = $user;
    }
    return $result;
});

// $users 现在包含所有请求的用户数据
```

#### `setWithTag($key, $value, $tags, $ttl = null)`

设置带标签的缓存值。

**参数：**
- `$key` (string): 缓存键名
- `$value` (mixed): 要缓存的值
- `$tags` (string|array): 标签名或标签数组
- `$ttl` (int|null): 缓存过期时间

**返回值：**
- `bool`: 操作是否成功

**示例：**
```php
// 单个标签
$cache->setWithTag('post:123', $postData, 'posts');

// 多个标签
$cache->setWithTag('user:123', $userData, ['users', 'vip_users']);

// 层次化标签
$cache->setWithTag(
    "user:profile:{$userId}", 
    $profile, 
    ['users', "user_{$userId}", 'profiles']
);
```

### 管理方法

#### `forget($key)`

删除指定的缓存项。

**参数：**
- `$key` (string): 要删除的缓存键名

**返回值：**
- `bool`: 删除是否成功

**示例：**
```php
$cache->forget('user:123');
$cache->forget('expired_data');
```

#### `clearTag($tag)`

清除指定标签下的所有缓存项。

**参数：**
- `$tag` (string): 要清除的标签名

**返回值：**
- `bool`: 清除是否成功

**示例：**
```php
// 清除所有用户缓存
$cache->clearTag('users');

// 清除特定用户的所有缓存
$cache->clearTag('user_123');

// 清除分类相关缓存
$cache->clearTag('category_5');
```

#### `has($key)`

检查缓存项是否存在。

**参数：**
- `$key` (string): 要检查的缓存键名

**返回值：**
- `bool`: 缓存项是否存在且未过期

**示例：**
```php
if ($cache->has('user:123')) {
    echo "用户缓存存在";
} else {
    echo "需要从数据库获取用户数据";
}
```

### 统计方法

#### `getStats()`

获取缓存统计信息。

**返回值：**
- `array`: 包含统计信息的数组

**返回格式：**
```php
[
    'hits' => int,      // 缓存命中次数
    'misses' => int,    // 缓存未命中次数  
    'hit_rate' => float // 缓存命中率（百分比）
]
```

**示例：**
```php
$stats = $cache->getStats();

echo "命中次数: {$stats['hits']}\n";
echo "未命中次数: {$stats['misses']}\n";
echo "命中率: {$stats['hit_rate']}%\n";

// 性能监控
if ($stats['hit_rate'] < 70) {
    error_log("缓存命中率过低: {$stats['hit_rate']}%");
}
```

### 静态方法

#### `setRedisFactory(callable $factory)`

设置 Redis 工厂函数（用于 Redis 驱动）。

**参数：**
- `$factory` (callable): 返回 Redis 实例的工厂函数

**示例：**
```php
use Asfop\CacheKV\CacheKV;

CacheKV::setRedisFactory(function() {
    return new \Predis\Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
        'database' => 0
    ]);
});
```

## CacheKVFacade 类

门面类提供与 CacheKV 相同的静态方法接口。

### 设置实例

#### `setInstance(CacheKV $instance)`

设置门面使用的 CacheKV 实例。

**参数：**
- `$instance` (CacheKV): CacheKV 实例

**示例：**
```php
use Asfop\CacheKV\CacheKVFacade;

$cache = new CacheKV(new ArrayDriver());
CacheKVFacade::setInstance($cache);
```

#### `getInstance()`

获取当前的 CacheKV 实例。

**返回值：**
- `CacheKV`: 当前实例

**异常：**
- `RuntimeException`: 如果实例未设置

### 静态方法

所有 CacheKV 的公共方法都可以通过门面静态调用：

```php
// 等价于 $cache->get()
CacheKVFacade::get('key', $callback);

// 等价于 $cache->set()
CacheKVFacade::set('key', 'value');

// 等价于 $cache->getMultiple()
CacheKVFacade::getMultiple($keys, $callback);

// 等价于 $cache->setWithTag()
CacheKVFacade::setWithTag('key', 'value', ['tag1', 'tag2']);

// 等价于 $cache->clearTag()
CacheKVFacade::clearTag('tag');

// 等价于 $cache->getStats()
CacheKVFacade::getStats();
```

## CacheKVServiceProvider 类

### 注册方法

#### `register($config = null)`

注册缓存服务并设置门面。

**参数：**
- `$config` (array|null): 配置数组，null 使用默认配置

**返回值：**
- `CacheKV`: 创建的 CacheKV 实例

**配置格式：**
```php
[
    'default' => 'array',  // 默认驱动
    'stores' => [
        'array' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\ArrayDriver::class
        ],
        'redis' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\RedisDriver::class
        ]
    ],
    'default_ttl' => 3600,  // 默认 TTL
    'ttl_jitter' => 300     // TTL 随机浮动（可选）
]
```

**示例：**
```php
use Asfop\CacheKV\CacheKVServiceProvider;

// 使用默认配置
$cache = CacheKVServiceProvider::register();

// 使用自定义配置
$cache = CacheKVServiceProvider::register([
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\RedisDriver::class
        ]
    ],
    'default_ttl' => 7200
]);
```

#### `getDefaultConfig()`

获取默认配置。

**返回值：**
- `array`: 默认配置数组

#### `createManager($config = null)`

创建缓存管理器实例。

**参数：**
- `$config` (array|null): 配置数组

**返回值：**
- `CacheManager`: 缓存管理器实例

## CacheDriver 接口

所有缓存驱动必须实现的接口。

### 基础方法

```php
public function get($key);
public function set($key, $value, $ttl);
public function getMultiple(array $keys);
public function setMultiple(array $values, $ttl);
public function forget($key);
public function has($key);
```

### 高级方法

```php
public function tag($key, array $tags);
public function clearTag($tag);
public function getStats();
public function touch($key, $ttl);
```

## 错误处理

### 异常类型

1. **RuntimeException**: 实例未设置、配置错误等
2. **InvalidArgumentException**: 参数错误
3. **Exception**: 驱动相关错误（如 Redis 连接失败）

### 错误处理示例

```php
try {
    $user = $cache->get('user:123', function() {
        return fetchUserFromDatabase(123);
    });
} catch (Exception $e) {
    // 缓存失败时的降级处理
    error_log("Cache error: " . $e->getMessage());
    $user = fetchUserFromDatabase(123);
}
```

## 最佳实践

### 1. 键命名规范

```php
// 推荐的命名模式
$cache->get("user:{$userId}");
$cache->get("product:{$productId}");  
$cache->get("posts:category:{$categoryId}:page:{$page}");
```

### 2. TTL 设置策略

```php
// 根据数据特性设置不同的 TTL
$cache->set('user:session', $session, 1800);    // 30分钟
$cache->set('user:profile', $profile, 3600);    // 1小时  
$cache->set('product:info', $product, 86400);   // 1天
$cache->set('system:config', $config, 604800);  // 1周
```

### 3. 错误处理

```php
// 总是提供降级方案
$data = $cache->get('expensive:data', function() {
    try {
        return fetchExpensiveData();
    } catch (Exception $e) {
        // 记录错误但不中断流程
        error_log("Failed to fetch data: " . $e->getMessage());
        return getDefaultData();
    }
});
```

### 4. 批量操作优化

```php
// 优先使用批量操作
$users = $cache->getMultiple($userIds, function($missingIds) {
    return $userService->findByIds($missingIds);
});

// 而不是循环调用单个方法
foreach ($userIds as $id) {
    $users[$id] = $cache->get("user:{$id}", function() use ($id) {
        return $userService->findById($id);
    });
}
```

这个 API 文档提供了 CacheKV 库的完整使用指南，帮助开发者快速上手并正确使用所有功能。
