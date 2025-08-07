# CacheKV 文档

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。

## 目录

1. [配置和架构](#配置和架构)
2. [快速使用](#快速使用)
3. [统计功能](#统计功能)

---

## 配置和架构

### 配置文件结构

```php
// config/cache_kv.php
return array(
    // ==================== 全局缓存配置 ====================
    'cache' => array(
        'ttl' => 3600,                          // 默认缓存时间（秒）
        'enable_stats' => true,                 // 是否启用统计
        'hot_key_auto_renewal' => true,         // 是否启用热点键自动续期
        'hot_key_threshold' => 100,             // 热点键阈值（访问次数）
    ),
    
    // ==================== KeyManager 配置 ====================
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

### 配置层级关系

```
全局配置 (cache) → 组级配置 (groups.user.cache) → 键级配置 (groups.user.keys.kv.profile.cache)
```

### 对象职责说明

| 对象 | 职责 |
|------|------|
| `CacheKVFactory` | 工厂类，负责组件初始化和依赖注入 |
| `ConfigManager` | 配置文件加载和管理 |
| `KeyManager` | 键的创建、验证和管理 |
| `CacheKV` | 核心缓存操作类，实现get/set/delete等功能 |
| `CacheKey` | 缓存键对象，包含键信息和配置 |
| `KeyStats` | 统计功能，记录命中率、热点键等 |

### 初始化流程

```php
// 一行配置，开箱即用
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    '/path/to/config.php'
);
```

---

## 快速使用

### 单个缓存获取

```php
// 基本用法
$userData = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 自定义TTL
$userData = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 1800); // 30分钟
```

### 多个缓存获取

```php
// 批量获取相同模板
$users = cache_kv_get_multiple('user.profile', [
    ['id' => 1],
    ['id' => 2], 
    ['id' => 3]
], function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = getUserFromDatabase($params['id']);
    }
    return $data; // 必须返回关联数组：['key_string' => 'data']
});

// 批量获取不同参数
$avatars = cache_kv_get_multiple('user.avatar', [
    ['id' => 123, 'size' => 'small'],
    ['id' => 123, 'size' => 'large']
], function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = generateAvatarUrl($params['id'], $params['size']);
    }
    return $data;
});
```

### 批量键生成和管理

```php
// 生成批量缓存键对象
$keyCollection = cache_kv_make_keys('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
]);

echo "生成了 {$keyCollection->count()} 个缓存键\n";

// 获取键字符串数组（用于Redis操作）
$keyStrings = $keyCollection->toStrings();
// 输出：['myapp:user:v1:profile:1', 'myapp:user:v1:profile:2', ...]

// 直接用于Redis操作
$redis->del($keyStrings); // 批量删除
```

### 混合数据类型处理

```php
// 处理不同类型的缓存数据
$results = cache_kv_get_multiple('user.profile', [
    ['id' => 1],
    ['id' => 999] // 不存在的用户
], function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $user = getUserFromDatabase($params['id']);
        
        // 处理不存在的用户（缓存null值避免缓存穿透）
        $data[$keyString] = $user ?: null;
    }
    return $data;
});

// 过滤null值
$validUsers = array_filter($results, function($user) {
    return $user !== null;
});
```

---

## 统计功能

### 基础统计信息

```php
// 获取全局统计
$stats = cache_kv_get_stats();
/*
Array
(
    [hits] => 850              // 命中次数
    [misses] => 150            // 未命中次数
    [total_requests] => 1000   // 总请求次数
    [hit_rate] => 85%          // 命中率
)
*/
```

### 热点键检测

```php
// 获取访问频率最高的键
$hotKeys = cache_kv_get_hot_keys(10); // 获取前10个热点键
/*
Array
(
    [myapp:user:v1:profile:123] => 500  // 访问次数
    [myapp:user:v1:profile:456] => 300
)
*/
```

### 热点键自动续期

当某个键的访问频率达到阈值时，系统会自动延长其缓存时间：

```php
'cache' => array(
    'hot_key_auto_renewal' => true,         // 启用自动续期
    'hot_key_threshold' => 100,             // 访问100次算热点
    'hot_key_extend_ttl' => 7200,           // 热点时延长到2小时
    'hot_key_max_ttl' => 86400,             // 最大24小时
),
```

**工作原理：**
1. 系统记录每个键的访问频率
2. 当访问次数达到阈值时，自动将TTL延长到配置的时间
3. 延长后的TTL不会超过最大TTL限制

### 统计配置选项

```php
'cache' => array(
    'enable_stats' => true,                 // 是否启用统计（默认：true）
    'hot_key_threshold' => 100,             // 热点键阈值（默认：100次访问）
    'hot_key_auto_renewal' => true,         // 是否启用自动续期（默认：true）
    'hot_key_extend_ttl' => 7200,           // 延长的TTL（默认：2小时）
    'hot_key_max_ttl' => 86400,             // 最大TTL（默认：24小时）
),
```

---

## 最佳实践

1. **合理设置TTL**：根据数据更新频率设置合适的缓存时间
2. **使用批量操作**：对于多个相关的缓存操作，优先使用 `cache_kv_get_multiple()`
3. **监控统计信息**：定期检查命中率和热点键，优化缓存策略
4. **启用热点键续期**：对于高并发场景，启用自动续期功能
