# API 参考

本文档详细说明了CacheKV提供的所有API接口。

## 辅助函数

### cache_kv_get()

获取单个缓存，若无则执行回调并回填缓存。

```php
function cache_kv_get($template, array $params = array(), $callback = null, $ttl = null)
```

**参数：**
- `$template` (string): 键模板，格式为 `'group.key'`
- `$params` (array): 模板参数，用于替换模板中的占位符
- `$callback` (callable|null): 回调函数，缓存未命中时执行
- `$ttl` (int|null): 自定义TTL，覆盖配置中的TTL

**返回值：**
- `mixed`: 缓存数据或回调函数的返回值

**示例：**
```php
// 基本用法
$user = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 自定义TTL
$user = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 1800); // 30分钟

// 无回调（仅获取缓存）
$user = cache_kv_get('user.profile', ['id' => 123]);
```

**异常：**
- `InvalidArgumentException`: 模板格式错误或分组不存在

---

### cache_kv_get_multiple()

批量获取缓存，支持自动回填未命中的键。

```php
function cache_kv_get_multiple(array $templates, $callback = null)
```

**参数：**
- `$templates` (array): 模板数组，每个元素包含 `template` 和 `params`
- `$callback` (callable|null): 回调函数，参数为未命中的CacheKey数组

**返回值：**
- `array`: 结果数组，键为完整的缓存键字符串，值为缓存数据

**示例：**
```php
$templates = [
    ['template' => 'user.profile', 'params' => ['id' => 1]],
    ['template' => 'user.profile', 'params' => ['id' => 2]],
    ['template' => 'user.settings', 'params' => ['id' => 1]],
];

$results = cache_kv_get_multiple($templates, function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        
        if (strpos($keyString, 'profile') !== false) {
            preg_match('/profile:(\d+)/', $keyString, $matches);
            $data[$keyString] = getUserFromDatabase($matches[1]);
        } elseif (strpos($keyString, 'settings') !== false) {
            preg_match('/settings:(\d+)/', $keyString, $matches);
            $data[$keyString] = getUserSettingsFromDatabase($matches[1]);
        }
    }
    return $data;
});

// 处理结果
foreach ($results as $keyString => $data) {
    echo "Key: {$keyString}, Data: " . json_encode($data) . "\n";
}
```

---

### cache_kv_get_stats()

获取缓存统计信息。

```php
function cache_kv_get_stats()
```

**参数：**
- 无

**返回值：**
- `array`: 统计信息数组

**返回值结构：**
```php
[
    'hits' => 850,              // 命中次数
    'misses' => 150,            // 未命中次数
    'sets' => 200,              // 设置次数
    'deletes' => 10,            // 删除次数
    'total_requests' => 1000,   // 总请求次数
    'hit_rate' => '85%',        // 命中率
    'enabled' => true           // 统计是否启用
]
```

**示例：**
```php
$stats = cache_kv_get_stats();

echo "命中率: {$stats['hit_rate']}\n";
echo "总请求: {$stats['total_requests']}\n";

if (floatval(str_replace('%', '', $stats['hit_rate'])) < 80) {
    echo "警告：命中率过低\n";
}
```

---

### cache_kv_get_hot_keys()

获取热点键列表。

```php
function cache_kv_get_hot_keys($limit = 10)
```

**参数：**
- `$limit` (int): 返回数量限制，默认10

**返回值：**
- `array`: 热点键数组，按访问频率降序排列

**返回值结构：**
```php
[
    'myapp:user:v1:profile:123' => [
        'key' => 'myapp:user:v1:profile:123',
        'total_requests' => 500,
        'hits' => 480,
        'misses' => 20,
        'hit_rate' => 96.0
    ],
    // ... 更多热点键
]
```

**示例：**
```php
$hotKeys = cache_kv_get_hot_keys(5);

echo "前5个热点键:\n";
foreach ($hotKeys as $key => $info) {
    echo "- {$key}: {$info['total_requests']}次访问, 命中率{$info['hit_rate']}%\n";
}
```

---

## 核心类

### CacheKVFactory

工厂类，负责组件初始化和配置管理。

#### configure()

配置CacheKV实例。

```php
public static function configure(callable $redisProvider, $configFile = null)
```

**参数：**
- `$redisProvider` (callable): Redis实例提供者闭包
- `$configFile` (string|null): 配置文件路径

**示例：**
```php
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    '/path/to/config.php'
);
```

#### getInstance()

获取CacheKV实例。

```php
public static function getInstance()
```

**返回值：**
- `CacheKV`: CacheKV实例

**异常：**
- `RuntimeException`: 未配置Redis提供者

---

### CacheKV

核心缓存操作类。

#### get()

获取缓存数据。

```php
public function get(CacheKey $cacheKey, $callback = null, $ttl = null)
```

**参数：**
- `$cacheKey` (CacheKey): 缓存键对象
- `$callback` (callable|null): 回调函数
- `$ttl` (int|null): 自定义TTL

**返回值：**
- `mixed`: 缓存数据或回调结果

#### set()

设置缓存数据。

```php
public function set(CacheKey $cacheKey, $data, $ttl = null)
```

**参数：**
- `$cacheKey` (CacheKey): 缓存键对象
- `$data` (mixed): 要缓存的数据
- `$ttl` (int|null): 自定义TTL

**返回值：**
- `bool`: 是否设置成功

#### delete()

删除缓存数据。

```php
public function delete(CacheKey $cacheKey)
```

**参数：**
- `$cacheKey` (CacheKey): 缓存键对象

**返回值：**
- `bool`: 是否删除成功

#### getMultiple()

批量获取缓存数据。

```php
public function getMultiple(array $cacheKeys, $callback = null)
```

**参数：**
- `$cacheKeys` (CacheKey[]): 缓存键对象数组
- `$callback` (callable|null): 回调函数

**返回值：**
- `array`: 结果数组

#### setMultiple()

批量设置缓存数据。

```php
public function setMultiple(array $items, $ttl = null)
```

**参数：**
- `$items` (array): CacheKey => data 的键值对数组
- `$ttl` (int|null): 自定义TTL

**返回值：**
- `bool`: 是否设置成功

#### getStats()

获取统计信息。

```php
public function getStats()
```

**返回值：**
- `array`: 统计信息

#### getHotKeys()

获取热点键。

```php
public function getHotKeys($limit = 10)
```

**参数：**
- `$limit` (int): 返回数量限制

**返回值：**
- `array`: 热点键数组

#### renewHotKey()

手动触发热点键续期。

```php
public function renewHotKey(CacheKey $cacheKey)
```

**参数：**
- `$cacheKey` (CacheKey): 缓存键对象

**返回值：**
- `bool`: 是否进行了续期

---

### CacheKey

缓存键对象，包含键信息和配置。

#### __construct()

构造函数。

```php
public function __construct($groupName, $keyName, array $params, $groupConfig, $keyConfig, $fullKey)
```

#### __toString()

转换为字符串。

```php
public function __toString()
```

**返回值：**
- `string`: 完整的缓存键字符串

#### getGroupName()

获取分组名称。

```php
public function getGroupName()
```

**返回值：**
- `string`: 分组名称

#### getKeyName()

获取键名称。

```php
public function getKeyName()
```

**返回值：**
- `string`: 键名称

#### getParams()

获取参数。

```php
public function getParams()
```

**返回值：**
- `array`: 参数数组

#### getCacheConfig()

获取缓存配置。

```php
public function getCacheConfig()
```

**返回值：**
- `CacheConfig|null`: 缓存配置对象

#### isStatsEnabled()

检查是否启用统计。

```php
public function isStatsEnabled()
```

**返回值：**
- `bool`: 是否启用统计

---

### KeyManager

键管理器，负责键的创建和验证。

#### getInstance()

获取单例实例。

```php
public static function getInstance()
```

**返回值：**
- `KeyManager`: KeyManager实例

#### createKey()

创建缓存键对象。

```php
public function createKey($groupName, $keyName, array $params = array())
```

**参数：**
- `$groupName` (string): 分组名称
- `$keyName` (string): 键名称
- `$params` (array): 参数数组

**返回值：**
- `CacheKey`: 缓存键对象

**异常：**
- `CacheException`: 分组不存在或参数无效

---

### KeyStats

统计管理类。

#### recordHit()

记录缓存命中。

```php
public static function recordHit($key)
```

**参数：**
- `$key` (string): 缓存键

#### recordMiss()

记录缓存未命中。

```php
public static function recordMiss($key)
```

**参数：**
- `$key` (string): 缓存键

#### recordSet()

记录缓存设置。

```php
public static function recordSet($key)
```

**参数：**
- `$key` (string): 缓存键

#### recordDelete()

记录缓存删除。

```php
public static function recordDelete($key)
```

**参数：**
- `$key` (string): 缓存键

#### getGlobalStats()

获取全局统计。

```php
public static function getGlobalStats()
```

**返回值：**
- `array`: 统计信息

#### getHotKeys()

获取热点键。

```php
public static function getHotKeys($limit = 10)
```

**参数：**
- `$limit` (int): 返回数量限制

**返回值：**
- `array`: 热点键数组

#### getKeyFrequency()

获取键的访问频率。

```php
public static function getKeyFrequency($key)
```

**参数：**
- `$key` (string): 缓存键

**返回值：**
- `int`: 访问频率

#### reset()

重置统计数据。

```php
public static function reset()
```

---

## 驱动接口

### DriverInterface

缓存驱动接口。

#### get()

获取缓存值。

```php
public function get($key)
```

#### set()

设置缓存值。

```php
public function set($key, $value, $ttl = 0)
```

#### delete()

删除缓存。

```php
public function delete($key)
```

#### exists()

检查缓存是否存在。

```php
public function exists($key)
```

#### getMultiple()

批量获取缓存。

```php
public function getMultiple(array $keys)
```

#### setMultiple()

批量设置缓存。

```php
public function setMultiple(array $items, $ttl = 0)
```

#### deleteMultiple()

批量删除缓存。

```php
public function deleteMultiple(array $keys)
```

#### expire()

设置过期时间。

```php
public function expire($key, $ttl)
```

#### ttl()

获取剩余TTL。

```php
public function ttl($key)
```

---

## 配置类

### CacheConfig

缓存配置对象。

#### getTtl()

获取TTL。

```php
public function getTtl($default = 3600)
```

#### isEnableStats()

是否启用统计。

```php
public function isEnableStats($default = true)
```

#### isHotKeyAutoRenewal()

是否启用热点键自动续期。

```php
public function isHotKeyAutoRenewal($default = true)
```

#### getHotKeyThreshold()

获取热点键阈值。

```php
public function getHotKeyThreshold($default = 100)
```

#### getHotKeyExtendTtl()

获取热点键延长TTL。

```php
public function getHotKeyExtendTtl($default = 7200)
```

#### getHotKeyMaxTtl()

获取热点键最大TTL。

```php
public function getHotKeyMaxTtl($default = 86400)
```

---

## 异常类

### CacheException

缓存相关异常。

```php
class CacheException extends \Exception
{
    // 标准异常类
}
```

**常见异常情况：**
- 分组不存在
- 键配置错误
- 参数验证失败
- Redis连接失败

---

## 使用示例

### 完整的API使用示例

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;
use Asfop\CacheKV\Key\KeyManager;

// 1. 配置
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    '/path/to/config.php'
);

// 2. 使用辅助函数（推荐）
$user = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 3. 使用核心类（高级用法）
$cache = CacheKVFactory::getInstance();
$keyManager = KeyManager::getInstance();

$cacheKey = $keyManager->createKey('user', 'profile', ['id' => 123]);
$user = $cache->get($cacheKey, function() {
    return getUserFromDatabase(123);
});

// 4. 批量操作
$templates = [
    ['template' => 'user.profile', 'params' => ['id' => 1]],
    ['template' => 'user.profile', 'params' => ['id' => 2]],
];

$results = cache_kv_get_multiple($templates, function($missedKeys) {
    // 处理未命中的键
    return batchGetUsersFromDatabase($missedKeys);
});

// 5. 统计监控
$stats = cache_kv_get_stats();
$hotKeys = cache_kv_get_hot_keys(10);

echo "命中率: {$stats['hit_rate']}\n";
echo "热点键数量: " . count($hotKeys) . "\n";
```
