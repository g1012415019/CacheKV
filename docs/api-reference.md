# API 参考

本文档提供 CacheKV 的完整 API 参考。

## 辅助函数

### cache_kv_get()

从缓存获取数据，如果不存在则执行回调函数并回填缓存。

```php
function cache_kv_get(string $template, array $params, callable $callback, int $ttl = null): mixed
```

**参数：**
- `$template` (string): 缓存模板名称
- `$params` (array): 模板参数
- `$callback` (callable): 缓存未命中时的回调函数
- `$ttl` (int|null): 缓存时间（秒），null 使用默认值

**返回值：**
- `mixed`: 缓存数据或回调函数返回值

**示例：**
```php
$user = cache_kv_get(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 3600);
```

### cache_kv_set()

设置缓存数据。

```php
function cache_kv_set(string $template, array $params, mixed $value, int $ttl = null): bool
```

**参数：**
- `$template` (string): 缓存模板名称
- `$params` (array): 模板参数
- `$value` (mixed): 要缓存的数据
- `$ttl` (int|null): 缓存时间（秒）

**返回值：**
- `bool`: 设置成功返回 true

**示例：**
```php
cache_kv_set(CacheTemplates::USER, ['id' => 123], $userData, 3600);
```

### cache_kv_delete()

删除缓存数据。

```php
function cache_kv_delete(string $template, array $params): bool
```

**参数：**
- `$template` (string): 缓存模板名称
- `$params` (array): 模板参数

**返回值：**
- `bool`: 删除成功返回 true

**示例：**
```php
cache_kv_delete(CacheTemplates::USER, ['id' => 123]);
```

### cache_kv_clear_tag()

清除指定标签的所有缓存。

```php
function cache_kv_clear_tag(string $tag): bool
```

**参数：**
- `$tag` (string): 标签名称

**返回值：**
- `bool`: 清除成功返回 true

**示例：**
```php
cache_kv_clear_tag('users');
```

### cache_kv_quick()

快速创建 CacheKV 实例。

```php
function cache_kv_quick(string $appPrefix, string $envPrefix, array $templates): CacheKV
```

**参数：**
- `$appPrefix` (string): 应用前缀
- `$envPrefix` (string): 环境前缀
- `$templates` (array): 模板配置

**返回值：**
- `CacheKV`: CacheKV 实例

**示例：**
```php
$cache = cache_kv_quick('myapp', 'dev', [
    'user' => 'user:{id}',
    'product' => 'product:{id}',
]);
```

## CacheKV 类

### 构造函数

```php
public function __construct(CacheDriverInterface $driver, int $defaultTtl, KeyManagerInterface $keyManager)
```

**参数：**
- `$driver` (CacheDriverInterface): 缓存驱动
- `$defaultTtl` (int): 默认 TTL
- `$keyManager` (KeyManagerInterface): 键管理器

### getByTemplate()

根据模板获取缓存数据。

```php
public function getByTemplate(string $template, array $params, callable $callback = null, int $ttl = null): mixed
```

**参数：**
- `$template` (string): 缓存模板名称
- `$params` (array): 模板参数
- `$callback` (callable|null): 缓存未命中时的回调函数
- `$ttl` (int|null): 缓存时间

**返回值：**
- `mixed`: 缓存数据

**示例：**
```php
$user = $cache->getByTemplate(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

### setByTemplate()

根据模板设置缓存数据。

```php
public function setByTemplate(string $template, array $params, mixed $value, int $ttl = null): bool
```

**参数：**
- `$template` (string): 缓存模板名称
- `$params` (array): 模板参数
- `$value` (mixed): 要缓存的数据
- `$ttl` (int|null): 缓存时间

**返回值：**
- `bool`: 设置成功返回 true

### setByTemplateWithTag()

根据模板设置带标签的缓存数据。

```php
public function setByTemplateWithTag(string $template, array $params, mixed $value, array $tags, int $ttl = null): bool
```

**参数：**
- `$template` (string): 缓存模板名称
- `$params` (array): 模板参数
- `$value` (mixed): 要缓存的数据
- `$tags` (array): 标签数组
- `$ttl` (int|null): 缓存时间

**返回值：**
- `bool`: 设置成功返回 true

**示例：**
```php
$cache->setByTemplateWithTag(
    CacheTemplates::USER, 
    ['id' => 123], 
    $userData, 
    ['users', 'vip_users']
);
```

### deleteByTemplate()

根据模板删除缓存数据。

```php
public function deleteByTemplate(string $template, array $params): bool
```

**参数：**
- `$template` (string): 缓存模板名称
- `$params` (array): 模板参数

**返回值：**
- `bool`: 删除成功返回 true

### get()

根据键获取缓存数据。

```php
public function get(string $key): mixed
```

**参数：**
- `$key` (string): 缓存键

**返回值：**
- `mixed`: 缓存数据，不存在返回 null

### set()

根据键设置缓存数据。

```php
public function set(string $key, mixed $value, int $ttl = null): bool
```

**参数：**
- `$key` (string): 缓存键
- `$value` (mixed): 要缓存的数据
- `$ttl` (int|null): 缓存时间

**返回值：**
- `bool`: 设置成功返回 true

### delete()

根据键删除缓存数据。

```php
public function delete(string $key): bool
```

**参数：**
- `$key` (string): 缓存键

**返回值：**
- `bool`: 删除成功返回 true

### has()

检查缓存键是否存在。

```php
public function has(string $key): bool
```

**参数：**
- `$key` (string): 缓存键

**返回值：**
- `bool`: 存在返回 true

### getMultiple()

批量获取缓存数据。

```php
public function getMultiple(array $keys, callable $callback = null): array
```

**参数：**
- `$keys` (array): 缓存键数组
- `$callback` (callable|null): 处理未命中键的回调函数

**返回值：**
- `array`: 键值对数组

**示例：**
```php
$users = $cache->getMultiple($userKeys, function($missingKeys) {
    return getUsersFromDatabase($missingKeys);
});
```

### setMultiple()

批量设置缓存数据。

```php
public function setMultiple(array $values, int $ttl = null): bool
```

**参数：**
- `$values` (array): 键值对数组
- `$ttl` (int|null): 缓存时间

**返回值：**
- `bool`: 设置成功返回 true

### deleteMultiple()

批量删除缓存数据。

```php
public function deleteMultiple(array $keys): bool
```

**参数：**
- `$keys` (array): 缓存键数组

**返回值：**
- `bool`: 删除成功返回 true

### clearTag()

清除指定标签的所有缓存。

```php
public function clearTag(string $tag): bool
```

**参数：**
- `$tag` (string): 标签名称

**返回值：**
- `bool`: 清除成功返回 true

### keys()

根据模式获取缓存键。

```php
public function keys(string $pattern): array
```

**参数：**
- `$pattern` (string): 键模式（支持通配符 *）

**返回值：**
- `array`: 匹配的键数组

**示例：**
```php
$userKeys = $cache->keys('myapp:prod:v1:user_profile:*');
```

### flush()

清空所有缓存。

```php
public function flush(): bool
```

**返回值：**
- `bool`: 清空成功返回 true

## CacheKVFactory 类

### setDefaultConfig()

设置默认配置。

```php
public static function setDefaultConfig(array $config): void
```

**参数：**
- `$config` (array): 配置数组

**示例：**
```php
CacheKVFactory::setDefaultConfig([
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => new RedisDriver($redis),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            CacheTemplates::USER => 'user:{id}',
        ]
    ]
]);
```

### store()

获取缓存存储实例。

```php
public static function store(string $name = null): CacheKV
```

**参数：**
- `$name` (string|null): 存储名称，null 使用默认存储

**返回值：**
- `CacheKV`: CacheKV 实例

**示例：**
```php
$cache = CacheKVFactory::store();
$redisCache = CacheKVFactory::store('redis');
```

### getKeyManager()

获取键管理器实例。

```php
public static function getKeyManager(): KeyManagerInterface
```

**返回值：**
- `KeyManagerInterface`: 键管理器实例

## KeyManager 类

### make()

根据模板和参数生成缓存键。

```php
public function make(string $template, array $params = []): string
```

**参数：**
- `$template` (string): 模板名称
- `$params` (array): 参数数组

**返回值：**
- `string`: 生成的缓存键

**示例：**
```php
$key = $keyManager->make(CacheTemplates::USER, ['id' => 123]);
// 结果: myapp:prod:v1:user_profile:123
```

### parse()

解析缓存键。

```php
public function parse(string $key): array
```

**参数：**
- `$key` (string): 缓存键

**返回值：**
- `array`: 解析结果数组

**示例：**
```php
$parsed = $keyManager->parse('myapp:prod:v1:user_profile:123');
// 结果: ['app_prefix' => 'myapp', 'env_prefix' => 'prod', ...]
```

### getTemplate()

获取模板配置。

```php
public function getTemplate(string $name): string
```

**参数：**
- `$name` (string): 模板名称

**返回值：**
- `string`: 模板字符串

### setTemplate()

设置模板配置。

```php
public function setTemplate(string $name, string $template): void
```

**参数：**
- `$name` (string): 模板名称
- `$template` (string): 模板字符串

## CacheKVFacade 类

门面类，提供静态方法访问 CacheKV 功能。

### getByTemplate()

```php
public static function getByTemplate(string $template, array $params, callable $callback = null, int $ttl = null): mixed
```

### setByTemplate()

```php
public static function setByTemplate(string $template, array $params, mixed $value, int $ttl = null): bool
```

### deleteByTemplate()

```php
public static function deleteByTemplate(string $template, array $params): bool
```

### clearTag()

```php
public static function clearTag(string $tag): bool
```

**示例：**
```php
// 注册服务提供者后使用
$user = CacheKVFacade::getByTemplate(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

## 驱动接口

### CacheDriverInterface

所有缓存驱动必须实现的接口。

```php
interface CacheDriverInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl): bool;
    public function delete(string $key): bool;
    public function has(string $key): bool;
    public function getMultiple(array $keys): array;
    public function setMultiple(array $values, int $ttl): bool;
    public function deleteMultiple(array $keys): bool;
    public function keys(string $pattern): array;
    public function flush(): bool;
}
```

### TaggableDriverInterface

支持标签功能的驱动接口。

```php
interface TaggableDriverInterface extends CacheDriverInterface
{
    public function setWithTag(string $key, mixed $value, array $tags, int $ttl): bool;
    public function clearTag(string $tag): bool;
    public function getTagKeys(string $tag): array;
}
```

## 异常类

### CacheException

缓存操作异常基类。

```php
class CacheException extends \Exception
{
    // 基础异常类
}
```

### DriverException

驱动相关异常。

```php
class DriverException extends CacheException
{
    // 驱动异常
}
```

### KeyManagerException

键管理器相关异常。

```php
class KeyManagerException extends CacheException
{
    // 键管理器异常
}
```

## 配置选项

### 完整配置示例

```php
[
    // 默认存储
    'default' => 'redis',
    
    // 存储配置
    'stores' => [
        'redis' => [
            'driver' => new RedisDriver($redis),
            'ttl' => 3600,
            'prefix' => 'cache:'
        ],
        'array' => [
            'driver' => new ArrayDriver(),
            'ttl' => 1800
        ]
    ],
    
    // 键管理器配置
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'separator' => ':',
        'templates' => [
            CacheTemplates::USER => 'user:{id}',
            CacheTemplates::PRODUCT => 'product:{id}:{status}',
        ]
    ],
    
    // 全局选项
    'options' => [
        'serialize' => true,
        'compress' => false,
        'encrypt' => false
    ]
]
```

这个 API 参考文档涵盖了 CacheKV 的所有公共接口和使用方法，为开发者提供了完整的技术参考。
