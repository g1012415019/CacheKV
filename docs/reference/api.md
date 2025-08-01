# API 参考文档

CacheKV 提供简洁而强大的 API，本文档详细介绍所有可用的方法和参数。

## 核心类

### CacheKV

主缓存管理类，提供所有缓存操作的统一接口。

#### 构造方法

```php
public function __construct(CacheDriver $driver, int $defaultTtl = 3600, KeyManager $keyManager = null)
```

**参数：**
- `$driver` - 缓存驱动实例
- `$defaultTtl` - 默认过期时间（秒）
- `$keyManager` - 键管理器实例（可选）

**示例：**
```php
$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);
```

#### 核心方法

##### getByTemplate()

使用模板获取缓存数据，支持自动回填。

```php
public function getByTemplate(string $template, array $params = [], callable $callback = null, int $ttl = null): mixed
```

**参数：**
- `$template` - 键模板名称
- `$params` - 模板参数
- `$callback` - 缓存未命中时的回调函数
- `$ttl` - 缓存过期时间（可选）

**返回值：** 缓存数据或回调函数返回值

**示例：**
```php
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 3600);
```

##### setByTemplate()

使用模板设置缓存数据。

```php
public function setByTemplate(string $template, array $params = [], mixed $value = null, int $ttl = null): bool
```

**参数：**
- `$template` - 键模板名称
- `$params` - 模板参数
- `$value` - 要缓存的数据
- `$ttl` - 缓存过期时间（可选）

**返回值：** 操作是否成功

**示例：**
```php
$cache->setByTemplate('user', ['id' => 123], $userData, 3600);
```

##### getMultiple()

批量获取缓存数据。

```php
public function getMultiple(array $keys, callable $callback = null, int $ttl = null): array
```

**参数：**
- `$keys` - 缓存键数组
- `$callback` - 处理未命中键的回调函数
- `$ttl` - 缓存过期时间（可选）

**返回值：** 键值对数组

**示例：**
```php
$users = $cache->getMultiple($userKeys, function($missingKeys) {
    return getUsersFromDatabase($missingKeys);
});
```

##### setByTemplateWithTag()

使用模板设置带标签的缓存。

```php
public function setByTemplateWithTag(string $template, array $params = [], mixed $value = null, array $tags = [], int $ttl = null): bool
```

**参数：**
- `$template` - 键模板名称
- `$params` - 模板参数
- `$value` - 要缓存的数据
- `$tags` - 标签数组
- `$ttl` - 缓存过期时间（可选）

**示例：**
```php
$cache->setByTemplateWithTag('user', ['id' => 123], $userData, ['users', 'vip_users']);
```

##### clearTag()

清除指定标签下的所有缓存。

```php
public function clearTag(string $tag): bool
```

**参数：**
- `$tag` - 要清除的标签名

**示例：**
```php
$cache->clearTag('users'); // 清除所有用户相关缓存
```

##### hasByTemplate()

检查模板生成的缓存是否存在。

```php
public function hasByTemplate(string $template, array $params = []): bool
```

**示例：**
```php
$exists = $cache->hasByTemplate('user', ['id' => 123]);
```

##### forgetByTemplate()

删除模板生成的缓存。

```php
public function forgetByTemplate(string $template, array $params = []): bool
```

**示例：**
```php
$cache->forgetByTemplate('user', ['id' => 123]);
```

##### makeKey()

生成缓存键（不执行缓存操作）。

```php
public function makeKey(string $template, array $params = [], bool $withPrefix = true): string
```

**示例：**
```php
$key = $cache->makeKey('user', ['id' => 123]);
// 返回: myapp:prod:v1:user:123
```

##### getStats()

获取缓存统计信息。

```php
public function getStats(): array
```

**返回值：**
```php
[
    'hits' => 85,        // 命中次数
    'misses' => 15,      // 未命中次数
    'hit_rate' => 85.0   // 命中率（百分比）
]
```

#### 基础方法

##### get()

获取缓存数据。

```php
public function get(string $key, callable $callback = null, int $ttl = null): mixed
```

##### set()

设置缓存数据。

```php
public function set(string $key, mixed $value, int $ttl = null): bool
```

##### has()

检查缓存是否存在。

```php
public function has(string $key): bool
```

##### forget()

删除缓存。

```php
public function forget(string $key): bool
```

##### setWithTag()

设置带标签的缓存。

```php
public function setWithTag(string $key, mixed $value, array $tags, int $ttl = null): bool
```

### KeyManager

缓存键管理器，负责统一的键命名和管理。

#### 构造方法

```php
public function __construct(array $config = [])
```

**配置参数：**
```php
[
    'app_prefix' => 'myapp',     // 应用前缀
    'env_prefix' => 'prod',      // 环境前缀
    'version' => 'v1',           // 版本号
    'separator' => ':',          // 分隔符
    'templates' => [             // 键模板
        'user' => 'user:{id}',
        'product' => 'product:{id}',
    ]
]
```

#### 核心方法

##### make()

生成缓存键。

```php
public function make(string $template, array $params = [], bool $withPrefix = true): string
```

**示例：**
```php
$key = $keyManager->make('user', ['id' => 123]);
// 返回: myapp:prod:v1:user:123
```

##### makeWithHash()

生成带哈希的缓存键（用于复杂参数）。

```php
public function makeWithHash(string $template, array $params = [], array $hashParams = [], bool $withPrefix = true): string
```

**示例：**
```php
$key = $keyManager->makeWithHash('api_response', [
    'endpoint' => 'users',
    'params_hash' => ['sort' => 'name', 'limit' => 10]
], ['params_hash']);
```

##### pattern()

生成模式匹配键（用于批量操作）。

```php
public function pattern(string $template, array $params = [], bool $withPrefix = true): string
```

**示例：**
```php
$pattern = $keyManager->pattern('user', ['id' => '*']);
// 返回: myapp:prod:v1:user:*
```

##### parse()

解析缓存键。

```php
public function parse(string $key): array
```

**返回值：**
```php
[
    'full_key' => 'myapp:prod:v1:user:123',
    'has_prefix' => true,
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'business_key' => 'user:123'
]
```

##### addTemplate()

添加键模板。

```php
public function addTemplate(string $name, string $pattern): void
```

**示例：**
```php
$keyManager->addTemplate('order', 'order:{id}');
```

##### validate()

验证键格式。

```php
public function validate(string $key): bool
```

##### sanitize()

清理键名。

```php
public function sanitize(string $key): string
```

### CacheKVFacade

静态门面类，提供便捷的静态方法调用。

#### 配置方法

##### setInstance()

设置 CacheKV 实例。

```php
public static function setInstance(CacheKV $instance): void
```

##### getInstance()

获取 CacheKV 实例。

```php
public static function getInstance(): CacheKV
```

#### 门面方法

所有 CacheKV 的方法都可以通过门面静态调用：

```php
// 模板方法
CacheKVFacade::getByTemplate($template, $params, $callback, $ttl);
CacheKVFacade::setByTemplate($template, $params, $value, $ttl);
CacheKVFacade::setByTemplateWithTag($template, $params, $value, $tags, $ttl);

// 基础方法
CacheKVFacade::get($key, $callback, $ttl);
CacheKVFacade::set($key, $value, $ttl);
CacheKVFacade::has($key);
CacheKVFacade::forget($key);

// 批量和标签方法
CacheKVFacade::getMultiple($keys, $callback, $ttl);
CacheKVFacade::clearTag($tag);

// 统计方法
CacheKVFacade::getStats();
```

### CacheKVServiceProvider

服务提供者，用于配置和注册 CacheKV 服务。

#### register()

注册 CacheKV 服务。

```php
public static function register(array $config = null): CacheKV
```

**配置示例：**
```php
$config = [
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => RedisDriver::class
        ],
        'array' => [
            'driver' => ArrayDriver::class
        ]
    ],
    'default_ttl' => 3600,
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

CacheKVServiceProvider::register($config);
```

## 驱动接口

### CacheDriver

缓存驱动的基础接口。

#### 核心方法

```php
public function get(string $key): mixed;
public function set(string $key, mixed $value, int $ttl = null): bool;
public function has(string $key): bool;
public function forget(string $key): bool;
public function setWithTag(string $key, mixed $value, array $tags, int $ttl = null): bool;
public function clearTag(string $tag): bool;
public function getStats(): array;
```

### ArrayDriver

内存数组驱动，适用于开发和测试。

```php
$driver = new ArrayDriver();
```

**特点：**
- 无需外部依赖
- 数据不持久化
- 仅限单进程使用

### RedisDriver

Redis 驱动，适用于生产环境。

```php
// 配置 Redis 连接
RedisDriver::setRedisFactory(function() {
    return new \Predis\Client([
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
    ]);
});

$driver = new RedisDriver();
```

**特点：**
- 数据持久化
- 支持分布式
- 高性能

## 异常处理

### CacheException

缓存操作异常的基类。

```php
try {
    $data = $cache->getByTemplate('user', ['id' => 123], $callback);
} catch (CacheException $e) {
    // 处理缓存异常
    logger()->error('Cache error: ' . $e->getMessage());
    
    // 降级到数据库
    $data = getUserFromDatabase(123);
}
```

### 常见异常

- `InvalidArgumentException` - 参数错误
- `RuntimeException` - 运行时错误
- `CacheDriverException` - 驱动相关错误

## 配置参考

### 完整配置示例

```php
$config = [
    // 基础配置
    'default' => 'redis',
    'default_ttl' => 3600,
    
    // 驱动配置
    'stores' => [
        'redis' => [
            'driver' => RedisDriver::class,
            'connection' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => 0,
                'password' => null,
                'timeout' => 5.0,
            ]
        ],
        'array' => [
            'driver' => ArrayDriver::class
        ]
    ],
    
    // 键管理器配置
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'separator' => ':',
        'templates' => [
            // 用户相关
            'user' => 'user:{id}',
            'user_profile' => 'user:profile:{id}',
            
            // 商品相关
            'product' => 'product:{id}',
            'product_detail' => 'product:detail:{id}',
            
            // API 相关
            'api_response' => 'api:{endpoint}:{params_hash}',
        ]
    ]
];
```

## 性能建议

### 1. 合理设置 TTL

```php
// 根据数据特性设置不同的过期时间
$cache->getByTemplate('user', ['id' => $id], $callback, 3600);      // 用户信息：1小时
$cache->getByTemplate('product', ['id' => $id], $callback, 7200);   // 商品信息：2小时
$cache->getByTemplate('price', ['id' => $id], $callback, 600);      // 价格信息：10分钟
```

### 2. 使用批量操作

```php
// ✅ 推荐：批量获取
$users = $cache->getMultiple($userKeys, $batchCallback);

// ❌ 避免：循环单次获取
foreach ($userIds as $id) {
    $users[] = $cache->getByTemplate('user', ['id' => $id], $callback);
}
```

### 3. 合理使用标签

```php
// 按业务维度设置标签
$cache->setByTemplateWithTag('user', ['id' => $id], $data, ['users', "user_{$id}"]);
$cache->setByTemplateWithTag('product', ['id' => $id], $data, ['products', "category_{$categoryId}"]);
```

### 4. 监控缓存效果

```php
$stats = $cache->getStats();

if ($stats['hit_rate'] < 70) {
    // 缓存命中率过低，需要优化
    $this->optimizeCacheStrategy();
}
```

---

**这份 API 参考文档涵盖了 CacheKV 的所有核心功能！** 📚
