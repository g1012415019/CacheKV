# API 参考

CacheKV 提供简洁而强大的 API，本文档详细介绍所有可用的方法和参数。

## CacheKV 类

### 构造方法

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

### 模板方法

#### getByTemplate()

使用模板获取缓存数据，支持自动回填。

```php
public function getByTemplate(string $template, array $params = [], callable $callback = null, int $ttl = null): mixed
```

**参数：**
- `$template` - 键模板名称
- `$params` - 模板参数
- `$callback` - 缓存未命中时的回调函数
- `$ttl` - 缓存过期时间（可选）

**示例：**
```php
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 3600);
```

#### setByTemplate()

使用模板设置缓存数据。

```php
public function setByTemplate(string $template, array $params = [], mixed $value = null, int $ttl = null): bool
```

**示例：**
```php
$cache->setByTemplate('user', ['id' => 123], $userData, 3600);
```

#### setByTemplateWithTag()

使用模板设置带标签的缓存。

```php
public function setByTemplateWithTag(string $template, array $params = [], mixed $value = null, array $tags = [], int $ttl = null): bool
```

**示例：**
```php
$cache->setByTemplateWithTag('user', ['id' => 123], $userData, ['users', 'vip_users']);
```

#### hasByTemplate()

检查模板生成的缓存是否存在。

```php
public function hasByTemplate(string $template, array $params = []): bool
```

#### forgetByTemplate()

删除模板生成的缓存。

```php
public function forgetByTemplate(string $template, array $params = []): bool
```

#### makeKey()

生成缓存键（不执行缓存操作）。

```php
public function makeKey(string $template, array $params = [], bool $withPrefix = true): string
```

### 批量操作

#### getMultiple()

批量获取缓存数据。

```php
public function getMultiple(array $keys, callable $callback = null, int $ttl = null): array
```

**参数：**
- `$keys` - 缓存键数组
- `$callback` - 处理未命中键的回调函数
- `$ttl` - 缓存过期时间（可选）

**示例：**
```php
$users = $cache->getMultiple($userKeys, function($missingKeys) {
    return getUsersFromDatabase($missingKeys);
});
```

### 标签管理

#### clearTag()

清除指定标签下的所有缓存。

```php
public function clearTag(string $tag): bool
```

**示例：**
```php
$cache->clearTag('users'); // 清除所有用户相关缓存
```

### 基础方法

#### get()

获取缓存数据。

```php
public function get(string $key, callable $callback = null, int $ttl = null): mixed
```

#### set()

设置缓存数据。

```php
public function set(string $key, mixed $value, int $ttl = null): bool
```

#### has()

检查缓存是否存在。

```php
public function has(string $key): bool
```

#### forget()

删除缓存。

```php
public function forget(string $key): bool
```

#### setWithTag()

设置带标签的缓存。

```php
public function setWithTag(string $key, mixed $value, array $tags, int $ttl = null): bool
```

### 统计方法

#### getStats()

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

## KeyManager 类

### 构造方法

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

### 核心方法

#### make()

生成缓存键。

```php
public function make(string $template, array $params = [], bool $withPrefix = true): string
```

**示例：**
```php
$key = $keyManager->make('user', ['id' => 123]);
// 返回: myapp:prod:v1:user:123
```

#### parse()

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

#### addTemplate()

添加键模板。

```php
public function addTemplate(string $name, string $pattern): void
```

#### validate()

验证键格式。

```php
public function validate(string $key): bool
```

## CacheKVFacade 类

静态门面类，提供便捷的静态方法调用。

### 配置方法

#### setInstance()

设置 CacheKV 实例。

```php
public static function setInstance(CacheKV $instance): void
```

### 门面方法

所有 CacheKV 的方法都可以通过门面静态调用：

```php
// 模板方法
CacheKVFacade::getByTemplate($template, $params, $callback, $ttl);
CacheKVFacade::setByTemplate($template, $params, $value, $ttl);

// 基础方法
CacheKVFacade::get($key, $callback, $ttl);
CacheKVFacade::set($key, $value, $ttl);

// 批量和标签方法
CacheKVFacade::getMultiple($keys, $callback, $ttl);
CacheKVFacade::clearTag($tag);

// 统计方法
CacheKVFacade::getStats();
```

## CacheKVServiceProvider 类

### register()

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

### ArrayDriver

内存数组驱动，适用于开发和测试。

```php
$driver = new ArrayDriver();
```

### RedisDriver

Redis 驱动，适用于生产环境。不依赖特定的 Redis 客户端库。

#### 使用 Predis
```php
// 安装 Predis: composer require predis/predis
$redis = new \Predis\Client([
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 0,
]);

$driver = new RedisDriver($redis);
```

#### 使用 PhpRedis 扩展
```php
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$redis->select(0);

$driver = new RedisDriver($redis);
```

**特点：**
- 数据持久化
- 支持分布式
- 高性能
- 支持任何 Redis 客户端

## 使用示例

### 基本使用

```php
// 创建实例
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1'
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 使用模板方法
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

### 门面使用

```php
// 注册服务
CacheKVServiceProvider::register([
    'default' => 'array',
    'stores' => ['array' => ['driver' => ArrayDriver::class]],
    'key_manager' => ['app_prefix' => 'myapp']
]);

// 使用门面
$user = CacheKVFacade::getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

### 批量操作

```php
$userIds = [1, 2, 3, 4, 5];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    return getUsersFromDatabase($missingKeys);
});
```

### 标签管理

```php
// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 123], $userData, ['users', 'vip_users']);

// 清除标签
$cache->clearTag('users');
```

---

**这份 API 参考文档涵盖了 CacheKV 的所有核心功能！** 📚
