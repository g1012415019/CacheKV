# API 参考

CacheKV 提供的所有 API 接口说明。

## 辅助函数

### cache_kv_get()

获取单个缓存，若无则执行回调并回填缓存。

```php
function cache_kv_get($template, array $params = array(), $callback = null, $ttl = null)
```

**参数：**
- `$template` (string): 键模板，格式为 `'group.key'`
- `$params` (array): 模板参数
- `$callback` (callable|null): 回调函数，缓存未命中时执行
- `$ttl` (int|null): 自定义TTL，覆盖配置中的TTL

**返回值：** `mixed` - 缓存数据或回调函数的返回值

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
```

---

### cache_kv_get_multiple()

批量获取缓存，支持自动回填未命中的键。

```php
function cache_kv_get_multiple($template, array $paramsArray, $callback = null)
```

**参数：**
- `$template` (string): 缓存模板名称
- `$paramsArray` (array): 参数数组，每个元素为一组参数
- `$callback` (callable|null): 回调函数，参数为未命中的CacheKey对象数组

**返回值：** `array` - 结果数组，键为完整的缓存键字符串，值为缓存数据

**回调函数格式：**
```php
function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = fetchData($params); // 必须返回关联数组
    }
    return $data;
}
```

**示例：**
```php
$users = cache_kv_get_multiple('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
], function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = getUserFromDatabase($params['id']);
    }
    return $data;
});
```

---

### cache_kv_make_keys()

批量创建缓存键集合。

```php
function cache_kv_make_keys($template, array $paramsList)
```

**参数：**
- `$template` (string): 键模板
- `$paramsList` (array): 参数数组列表

**返回值：** `CacheKeyCollection` - 缓存键集合对象

**示例：**
```php
$keyCollection = cache_kv_make_keys('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
]);

// 获取键字符串数组
$keyStrings = $keyCollection->toStrings();

// 获取键对象数组
$cacheKeys = $keyCollection->getKeys();

// 获取数量
$count = $keyCollection->count();
```

---

### cache_kv_get_stats()

获取缓存统计信息。

```php
function cache_kv_get_stats()
```

**返回值：** `array` - 统计信息数组

**返回值结构：**
```php
[
    'hits' => 850,              // 命中次数
    'misses' => 150,            // 未命中次数
    'total_requests' => 1000,   // 总请求次数
    'hit_rate' => '85%',        // 命中率
    'sets' => 200,              // 设置次数
    'deletes' => 10,            // 删除次数
    'enabled' => true           // 统计是否启用
]
```

---

### cache_kv_get_hot_keys()

获取热点键列表。

```php
function cache_kv_get_hot_keys($limit = 10)
```

**参数：**
- `$limit` (int): 返回数量限制，默认10

**返回值：** `array` - 热点键数组，按访问频率降序排列

**返回值格式：**
```php
[
    'myapp:user:v1:profile:123' => 45,  // 键名 => 访问次数
    'myapp:user:v1:profile:456' => 32,
    // ... 更多热点键
]
```

### cache_kv_delete_by_prefix()

按前缀删除缓存，相当于按 tag 删除。

```php
function cache_kv_delete_by_prefix($template, array $params = array())
```

**参数：**
- `$template` (string): 键模板，格式为 `'group.key'`
- `$params` (array): 参数数组（可选），用于生成具体的前缀

**返回值：** `int` - 删除的键数量

**示例：**
```php
// 删除所有用户设置缓存
$count = cache_kv_delete_by_prefix('user.settings');
echo "删除了 {$count} 个用户设置缓存\n";

// 删除特定用户的设置缓存
$count = cache_kv_delete_by_prefix('user.settings', ['id' => 123]);
echo "删除了用户123的 {$count} 个设置缓存\n";

// 删除所有商品信息缓存
$count = cache_kv_delete_by_prefix('goods.info');
echo "删除了 {$count} 个商品缓存\n";
```

---

### cache_kv_delete_by_full_prefix()

按完整前缀删除缓存（更直接的方式）。

```php
function cache_kv_delete_by_full_prefix($prefix)
```

**参数：**
- `$prefix` (string): 完整的键前缀，如 `'myapp:user:v1:settings:'`

**返回值：** `int` - 删除的键数量

**示例：**
```php
// 使用完整前缀删除
$count = cache_kv_delete_by_full_prefix('myapp:user:v1:settings:');
echo "删除了 {$count} 个缓存\n";

// 从现有键提取前缀
$sampleKey = cache_kv_make_key('user.profile', ['id' => 123]);
$fullKey = (string)$sampleKey;  // myapp:user:v1:profile:123
$prefix = substr($fullKey, 0, strrpos($fullKey, ':') + 1);  // myapp:user:v1:profile:
$count = cache_kv_delete_by_full_prefix($prefix);
```

---

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

**返回值：** `CacheKV` - CacheKV实例

---

### CacheKey

缓存键对象，包含键信息和配置。

#### 主要方法

```php
public function __toString()                // 转换为字符串
public function getGroupName()              // 获取分组名称
public function getKeyName()                // 获取键名称
public function getParams()                 // 获取参数
public function isStatsEnabled()            // 检查是否启用统计
```

**示例：**
```php
$cacheKey = cache_kv_make_key('user.profile', ['id' => 123]);

echo (string)$cacheKey;         // myapp:user:v1:profile:123
echo $cacheKey->getGroupName(); // user
echo $cacheKey->getKeyName();   // profile
print_r($cacheKey->getParams()); // ['id' => 123]
```

---

### CacheKeyCollection

缓存键集合类，包装 CacheKey 数组。

#### 主要方法

```php
public function getKeys()                   // 获取 CacheKey 对象数组
public function toStrings()                 // 转换为字符串数组
public function count()                     // 获取集合大小
public function isEmpty()                   // 检查是否为空
public function get($index)                 // 获取指定索引的 CacheKey
```

**示例：**
```php
$collection = cache_kv_make_keys('user.profile', [['id' => 1], ['id' => 2]]);

$keys = $collection->getKeys();         // CacheKey[]
$strings = $collection->toStrings();    // string[]
$count = $collection->count();          // 2
$first = $collection->get(0);           // CacheKey
```

---

## 高级用法

### 直接使用核心类

```php
use Asfop\CacheKV\Core\CacheKVFactory;
use Asfop\CacheKV\Key\KeyManager;

// 获取实例
$cache = CacheKVFactory::getInstance();
$keyManager = KeyManager::getInstance();

// 创建键对象
$cacheKey = $keyManager->createKey('user', 'profile', ['id' => 123]);

// 直接操作缓存
$user = $cache->get($cacheKey, function() {
    return getUserFromDatabase(123);
});

// 批量操作
$cacheKeys = [
    $keyManager->createKey('user', 'profile', ['id' => 1]),
    $keyManager->createKey('user', 'profile', ['id' => 2]),
];

$results = $cache->getMultiple($cacheKeys, function($missedKeys) {
    // 处理未命中的键
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = getUserFromDatabase($params['id']);
    }
    return $data;
});
```

### 手动缓存操作

```php
$cache = CacheKVFactory::getInstance();
$keyManager = KeyManager::getInstance();

$cacheKey = $keyManager->createKey('user', 'profile', ['id' => 123]);

// 设置缓存
$cache->set($cacheKey, $userData, 3600);

// 删除缓存
$cache->delete($cacheKey);

// 检查是否存在
$exists = $cache->exists($cacheKey);
```

---

## 异常处理

### CacheException

缓存相关异常。

**常见异常情况：**
- 分组不存在
- 键配置错误
- 参数验证失败
- Redis连接失败

**示例：**
```php
try {
    $user = cache_kv_get('invalid.key', ['id' => 123], function() {
        return getUserFromDatabase(123);
    });
} catch (\Asfop\CacheKV\Exception\CacheException $e) {
    echo "缓存错误: " . $e->getMessage();
}
```

---

## 完整示例

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 配置
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    '/path/to/config.php'
);

// 单个缓存
$user = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 批量缓存
$users = cache_kv_get_multiple('user.profile', [
    ['id' => 1], ['id' => 2]
], function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = getUserFromDatabase($params['id']);
    }
    return $data;
});

// 键管理
$keyCollection = cache_kv_make_keys('user.profile', [['id' => 1], ['id' => 2]]);
$keyStrings = $keyCollection->toStrings();

// 统计监控
$stats = cache_kv_get_stats();
$hotKeys = cache_kv_get_hot_keys(10);

echo "命中率: {$stats['hit_rate']}\n";
echo "热点键数量: " . count($hotKeys) . "\n";
```
