# API 参考文档

本文档详细介绍 CacheKV 的所有 API 接口。

## 🔧 核心操作函数

### kv_get()

获取缓存数据，支持回调自动回填。

```php
function kv_get($template, array $params = [], $callback = null, $ttl = null)
```

**参数：**
- `$template` (string): 键模板，格式：'group.key'
- `$params` (array): 参数数组，用于替换模板中的占位符
- `$callback` (callable|null): 缓存未命中时的回调函数
- `$ttl` (int|null): 自定义TTL（秒），覆盖配置中的默认值

**返回值：**
- `mixed`: 缓存数据或回调函数的返回值

**示例：**
```php
// 基础用法
$user = kv_get('user.profile', ['id' => 123]);

// 带回调的用法
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 自定义TTL
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 7200); // 2小时
```

### kv_get_multi()

批量获取缓存数据，支持批量回调。

```php
function kv_get_multi($template, array $paramsList, $callback = null)
```

**参数：**
- `$template` (string): 键模板，格式：'group.key'
- `$paramsList` (array): 参数数组列表
- `$callback` (callable|null): 批量回调函数

**回调函数签名：**
```php
function($missedKeys) {
    // $missedKeys 是 CacheKey 对象数组
    // 必须返回关联数组：['key_string' => 'data', ...]
}
```

**返回值：**
- `array`: 结果数组，键为缓存键字符串，值为缓存数据

**示例：**
```php
// 批量获取用户信息
$users = kv_get_multi('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
], function($missedKeys) {
    $results = [];
    foreach ($missedKeys as $cacheKey) {
        $params = $cacheKey->getParams();
        $userId = $params['id'];
        $results[(string)$cacheKey] = getUserFromDatabase($userId);
    }
    return $results;
});

// 结果格式：
// [
//     'app:user:v1:1' => ['id' => 1, 'name' => 'User1'],
//     'app:user:v1:2' => ['id' => 2, 'name' => 'User2'],
//     'app:user:v1:3' => ['id' => 3, 'name' => 'User3']
// ]
```

## 🗝️ 键管理函数

### kv_key()

生成单个缓存键字符串。

```php
function kv_key($template, array $params = [])
```

**参数：**
- `$template` (string): 键模板，格式：'group.key'
- `$params` (array): 参数数组

**返回值：**
- `string`: 生成的缓存键字符串

**示例：**
```php
$key = kv_key('user.profile', ['id' => 123]);
// 返回: "app:user:v1:123"
```

### kv_keys()

批量生成缓存键字符串。

```php
function kv_keys($template, array $paramsList)
```

**参数：**
- `$template` (string): 键模板，格式：'group.key'
- `$paramsList` (array): 参数数组列表

**返回值：**
- `array`: 键字符串数组

**示例：**
```php
$keys = kv_keys('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
]);
// 返回: ["app:user:v1:1", "app:user:v1:2", "app:user:v1:3"]
```

### kv_get_keys()

批量获取缓存键对象（不执行缓存操作）。

```php
function kv_get_keys($template, array $paramsList)
```

**参数：**
- `$template` (string): 键模板，格式：'group.key'
- `$paramsList` (array): 参数数组列表

**返回值：**
- `array`: 关联数组，键为键字符串，值为 CacheKey 对象

**示例：**
```php
$keyObjects = kv_get_keys('user.profile', [
    ['id' => 1],
    ['id' => 2]
]);

foreach ($keyObjects as $keyString => $keyObj) {
    echo "键: {$keyString}\n";
    echo "参数: " . json_encode($keyObj->getParams()) . "\n";
    echo "有缓存配置: " . ($keyObj->hasCacheConfig() ? '是' : '否') . "\n";
}
```

## 🗑️ 删除操作函数

### kv_delete_prefix()

按前缀删除缓存，相当于按 tag 删除。

```php
function kv_delete_prefix($template, array $params = [])
```

**参数：**
- `$template` (string): 键模板，格式：'group.key'
- `$params` (array): 参数数组（可选）

**返回值：**
- `int`: 删除的键数量

**示例：**
```php
// 删除特定用户的所有缓存
$deleted = kv_delete_prefix('user.profile', ['id' => 123]);

// 删除所有用户资料缓存
$deleted = kv_delete_prefix('user.profile');

// 删除整个用户组的缓存
$deleted = kv_delete_prefix('user');
```

### kv_delete_full()

按完整前缀删除缓存。

```php
function kv_delete_full($prefix)
```

**参数：**
- `$prefix` (string): 完整的键前缀

**返回值：**
- `int`: 删除的键数量

**示例：**
```php
// 删除所有以 "app:user:" 开头的缓存
$deleted = kv_delete_full('app:user:');

// 删除所有以 "temp:" 开头的临时缓存
$deleted = kv_delete_full('temp:');
```

## 📊 统计功能函数

### kv_stats()

获取全局统计信息。

```php
function kv_stats()
```

**返回值：**
- `array`: 统计信息数组

**示例：**
```php
$stats = kv_stats();
print_r($stats);

// 输出示例：
// [
//     'hits' => 1500,
//     'misses' => 300,
//     'hit_rate' => '83.33%',
//     'total_requests' => 1800,
//     'sets' => 350,
//     'deletes' => 50
// ]
```

### kv_hot_keys()

获取热点键列表。

```php
function kv_hot_keys($limit = 10)
```

**参数：**
- `$limit` (int): 返回的热点键数量限制，默认10个

**返回值：**
- `array`: 热点键数组，键为缓存键，值为访问次数

**示例：**
```php
$hotKeys = kv_hot_keys(5);
print_r($hotKeys);

// 输出示例：
// [
//     'app:user:v1:123' => 45,
//     'app:user:v1:456' => 32,
//     'app:product:v1:789' => 28,
//     'app:user:v1:101' => 25,
//     'app:config:v1:settings' => 20
// ]
```

### kv_clear_stats()

清空统计数据。

```php
function kv_clear_stats()
```

**返回值：**
- `bool`: 是否成功清空

**示例：**
```php
$success = kv_clear_stats();
if ($success) {
    echo "统计数据已清空\n";
}
```

## ⚙️ 配置管理函数

### kv_config()

获取完整的配置对象。

```php
function kv_config()
```

**返回值：**
- `CacheKVConfig`: 配置对象，可转换为数组

**示例：**
```php
$config = kv_config();

// 转换为数组查看
$configArray = $config->toArray();
print_r($configArray);

// 获取特定配置
$cacheConfig = $config->getCacheConfig();
$keyManagerConfig = $config->getKeyManagerConfig();
```

## 🔄 使用模式

### 1. 简单缓存模式

```php
// 最简单的用法
$data = kv_get('user.profile', ['id' => 123], function() {
    return fetchUserFromDatabase(123);
});
```

### 2. 批量处理模式

```php
// 批量获取，避免N+1查询
$userIds = [1, 2, 3, 4, 5];
$paramsList = array_map(function($id) {
    return ['id' => $id];
}, $userIds);

$users = kv_get_multi('user.profile', $paramsList, function($missedKeys) {
    $userIds = [];
    foreach ($missedKeys as $key) {
        $userIds[] = $key->getParams()['id'];
    }
    
    // 一次性从数据库获取所有缺失的用户
    $users = fetchUsersFromDatabase($userIds);
    
    $results = [];
    foreach ($missedKeys as $key) {
        $userId = $key->getParams()['id'];
        $results[(string)$key] = $users[$userId] ?? null;
    }
    
    return $results;
});
```

### 3. 缓存失效模式

```php
// 更新数据后清理相关缓存
function updateUser($userId, $userData) {
    // 更新数据库
    updateUserInDatabase($userId, $userData);
    
    // 清理相关缓存
    kv_delete_prefix('user.profile', ['id' => $userId]);
    kv_delete_prefix('user.settings', ['id' => $userId]);
}
```

### 4. 监控模式

```php
// 定期检查缓存性能
function checkCachePerformance() {
    $stats = kv_stats();
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    
    if ($hitRate < 80) {
        // 命中率过低，需要优化
        logWarning("Cache hit rate is low: {$stats['hit_rate']}");
    }
    
    // 检查热点键
    $hotKeys = kv_hot_keys(10);
    foreach ($hotKeys as $key => $count) {
        if ($count > 1000) {
            logInfo("Hot key detected: {$key} ({$count} hits)");
        }
    }
}
```

## 🚨 错误处理

所有函数都会妥善处理错误情况：

- **配置错误**：抛出 `CacheException`
- **网络错误**：Redis连接失败时返回默认值
- **序列化错误**：自动降级处理
- **回调错误**：记录日志但不影响主流程

**最佳实践：**
```php
try {
    $data = kv_get('user.profile', ['id' => 123], function() {
        return fetchUserFromDatabase(123);
    });
} catch (CacheException $e) {
    // 处理配置错误
    logError("Cache configuration error: " . $e->getMessage());
    $data = fetchUserFromDatabase(123); // 降级到直接查询
}
```

## 📝 注意事项

1. **模板格式**：必须使用 'group.key' 格式
2. **参数命名**：参数名必须与模板中的占位符匹配
3. **回调返回值**：批量回调必须返回关联数组
4. **键字符串**：生成的键会包含应用前缀、组前缀和版本号
5. **TTL优先级**：函数参数 > 键级配置 > 组级配置 > 全局配置

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
$users = kv_get_multi('user.profile', [
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

### kv_keys()

批量创建缓存键集合。

```php
function kv_keys($template, array $paramsList)
```

**参数：**
- `$template` (string): 键模板
- `$paramsList` (array): 参数数组列表

**返回值：** `CacheKeyCollection` - 缓存键集合对象

**示例：**
```php
$keyCollection = kv_keys('user.profile', [
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

### kv_stats()

获取缓存统计信息。

```php
function kv_stats()
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

### kv_hot_keys()

获取热点键列表。

```php
function kv_hot_keys($limit = 10)
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

### kv_delete_prefix()

按前缀删除缓存，相当于按 tag 删除。

```php
function kv_delete_prefix($template, array $params = array())
```

**参数：**
- `$template` (string): 键模板，格式为 `'group.key'`
- `$params` (array): 参数数组（可选），用于生成具体的前缀

**返回值：** `int` - 删除的键数量

**示例：**
```php
// 删除所有用户设置缓存
$count = kv_delete_prefix('user.settings');
echo "删除了 {$count} 个用户设置缓存\n";

// 删除特定用户的设置缓存
$count = kv_delete_prefix('user.settings', ['id' => 123]);
echo "删除了用户123的 {$count} 个设置缓存\n";

// 删除所有商品信息缓存
$count = kv_delete_prefix('goods.info');
echo "删除了 {$count} 个商品缓存\n";
```

---

### kv_delete_full()

按完整前缀删除缓存（更直接的方式）。

```php
function kv_delete_full($prefix)
```

**参数：**
- `$prefix` (string): 完整的键前缀，如 `'myapp:user:v1:settings:'`

**返回值：** `int` - 删除的键数量

**示例：**
```php
// 使用完整前缀删除
$count = kv_delete_full('myapp:user:v1:settings:');
echo "删除了 {$count} 个缓存\n";

// 从现有键提取前缀
$sampleKey = kv_key('user.profile', ['id' => 123]);
$fullKey = (string)$sampleKey;  // myapp:user:v1:profile:123
$prefix = substr($fullKey, 0, strrpos($fullKey, ':') + 1);  // myapp:user:v1:profile:
$count = kv_delete_full($prefix);
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
$cacheKey = kv_key('user.profile', ['id' => 123]);

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
$collection = kv_keys('user.profile', [['id' => 1], ['id' => 2]]);

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
    $user = kv_get('invalid.key', ['id' => 123], function() {
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
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 批量缓存
$users = kv_get_multi('user.profile', [
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
$keyCollection = kv_keys('user.profile', [['id' => 1], ['id' => 2]]);
$keyStrings = $keyCollection->toStrings();

// 统计监控
$stats = kv_stats();
$hotKeys = kv_hot_keys(10);

echo "命中率: {$stats['hit_rate']}\n";
echo "热点键数量: " . count($hotKeys) . "\n";
```
