# CacheKV 文档

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。

## 目录

1. [配置和架构](#配置和架构)
2. [快速使用](#快速使用)
3. [统计功能](#统计功能)
4. [API 参考](#api-参考)

---

## 配置和架构

### 配置文件结构

CacheKV 使用分层配置系统，配置优先级为：**键级配置 > 组级配置 > 全局配置**

```php
// config/cache_kv.php
return array(
    // ==================== 全局缓存配置 ====================
    'cache' => array(
        // 基础配置
        'ttl' => 3600,                          // 默认缓存时间（秒）
        'null_cache_ttl' => 300,                // 空值缓存时间（秒）
        'enable_null_cache' => true,            // 是否启用空值缓存
        'ttl_random_range' => 300,              // TTL随机范围（秒）
        
        // 统计配置
        'enable_stats' => true,                 // 是否启用统计
        
        // 热点键自动续期配置
        'hot_key_auto_renewal' => true,         // 是否启用热点键自动续期
        'hot_key_threshold' => 100,             // 热点键阈值（访问次数）
        'hot_key_extend_ttl' => 7200,           // 热点键延长TTL（秒）
        'hot_key_max_ttl' => 86400,             // 热点键最大TTL（秒）
        
        // 标签配置
        'tag_prefix' => 'tag:',                 // 标签前缀
    ),
    
    // ==================== KeyManager 配置 ====================
    'key_manager' => array(
        'app_prefix' => 'myapp',                // 应用前缀
        'separator' => ':',                     // 分隔符
        
        // 分组配置
        'groups' => array(
            'user' => array(
                'prefix' => 'user',
                'version' => 'v1',
                'description' => '用户相关数据缓存',
                
                // 组级缓存配置（覆盖全局配置）
                'cache' => array(
                    'ttl' => 7200,              // 用户数据缓存2小时
                    'hot_key_threshold' => 50,  // 用户数据热点阈值更低
                ),
                
                // 键定义
                'keys' => array(
                    'kv' => array(
                        'profile' => array(
                            'template' => 'profile:{id}',
                            'description' => '用户基础资料',
                            // 键级缓存配置（最高优先级）
                            'cache' => array(
                                'ttl' => 10800,     // 用户资料缓存3小时
                                'hot_key_threshold' => 30,
                            )
                        ),
                        'settings' => array(
                            'template' => 'settings:{id}',
                            'description' => '用户设置信息',
                        ),
                    ),
                ),
            ),
        ),
    ),
);
```

### 配置层级关系

```
全局配置 (cache)
    ↓ 继承并可覆盖
组级配置 (groups.user.cache)
    ↓ 继承并可覆盖
键级配置 (groups.user.keys.kv.profile.cache)
```

**示例：**
- 全局 TTL: 3600秒
- 用户组 TTL: 7200秒（覆盖全局）
- 用户资料 TTL: 10800秒（覆盖组级）

### 核心对象关系

```
CacheKVFactory (工厂类)
    ├── ConfigManager (配置管理)
    │   └── CacheKVConfig (配置对象)
    │       ├── CacheConfig (缓存配置)
    │       └── KeyManagerConfig (键管理配置)
    │
    ├── KeyManager (键管理器)
    │   ├── GroupConfig (分组配置)
    │   └── KeyConfig (键配置)
    │
    ├── CacheKV (核心缓存类)
    │   ├── DriverInterface (驱动接口)
    │   │   └── RedisDriver (Redis驱动)
    │   └── CacheKey (缓存键对象)
    │
    └── KeyStats (统计管理)
```

### 对象职责说明

| 对象 | 职责 |
|------|------|
| `CacheKVFactory` | 工厂类，负责组件初始化和依赖注入 |
| `ConfigManager` | 配置文件加载和管理 |
| `CacheConfig` | 缓存相关配置的封装和访问 |
| `KeyManager` | 键的创建、验证和管理 |
| `CacheKV` | 核心缓存操作类，实现get/set/delete等功能 |
| `CacheKey` | 缓存键对象，包含键信息和配置 |
| `DriverInterface` | 缓存驱动接口，支持Redis等 |
| `KeyStats` | 统计功能，记录命中率、热点键等 |

### 初始化流程

```php
// 1. 配置CacheKV
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    '/path/to/config.php'
);

// 2. 内部初始化流程
ConfigManager::loadConfig()           // 加载配置文件
    ↓
KeyManager::injectGlobalConfig()      // 注入键管理配置
    ↓
CacheKVFactory::getInstance()         // 创建CacheKV实例
    ↓
Ready to use                          // 可以使用辅助函数
```

---

## 快速使用

### 基本配置

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
    '/path/to/config.php'
);
```

### 单个缓存获取

#### 基本用法

```php
// 最简单的用法：缓存存在则返回，不存在则执行回调并缓存
$userData = cache_kv_get('user.profile', ['id' => 123], function() {
    // 只在缓存未命中时执行
    return getUserFromDatabase(123);
});

echo json_encode($userData);
```

#### 带自定义TTL

```php
// 自定义缓存时间（覆盖配置中的TTL）
$userData = cache_kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 1800); // 30分钟
```

#### 不同类型的数据

```php
// 缓存数组
$userSettings = cache_kv_get('user.settings', ['id' => 123], function() {
    return [
        'theme' => 'dark',
        'language' => 'zh-CN',
        'notifications' => true
    ];
});

// 缓存字符串
$userAvatar = cache_kv_get('user.avatar', ['id' => 123, 'size' => 'large'], function() {
    return generateAvatarUrl(123, 'large');
});

// 缓存对象
$userProfile = cache_kv_get('user.profile', ['id' => 123], function() {
    $user = new User();
    $user->loadFromDatabase(123);
    return $user;
});
```

### 多个缓存获取

#### 批量获取相同模板（推荐）

```php
// 批量获取多个用户资料
$users = cache_kv_get_multiple('user.profile', [
    ['id' => 1],
    ['id' => 2], 
    ['id' => 3]
], function($missedKeys) {
    $data = [];
    
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $userId = $params['id'];
        
        // 从数据库获取用户数据
        $data[$keyString] = getUserFromDatabase($userId);
    }
    
    return $data; // 必须返回关联数组：['key_string' => 'data']
});

// 处理结果
foreach ($users as $keyString => $userData) {
    echo "用户数据: " . json_encode($userData) . "\n";
}
```

#### 批量获取不同参数

```php
// 获取不同尺寸的用户头像
$avatars = cache_kv_get_multiple('user.avatar', [
    ['id' => 123, 'size' => 'small'],
    ['id' => 123, 'size' => 'medium'],
    ['id' => 123, 'size' => 'large']
], function($missedKeys) {
    $data = [];
    
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        
        // 生成对应尺寸的头像URL
        $data[$keyString] = generateAvatarUrl($params['id'], $params['size']);
    }
    
    return $data;
});
```

#### 批量键生成和管理

```php
// 生成批量缓存键对象
$keyCollection = cache_kv_make_keys('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
]);

echo "生成了 {$keyCollection->count()} 个缓存键\n";

// 获取键字符串数组（用于Redis操作）
$keyStrings = $keyCollection->toStrings();
print_r($keyStrings);
/*
输出：
Array
(
    [0] => myapp:user:v1:profile:1
    [1] => myapp:user:v1:profile:2
    [2] => myapp:user:v1:profile:3
)
*/

// 遍历键对象
foreach ($keyCollection->getKeys() as $cacheKey) {
    echo "键: " . (string)$cacheKey . "\n";
    echo "参数: " . json_encode($cacheKey->getParams()) . "\n";
}

// 直接用于Redis操作
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->del($keyStrings); // 批量删除
```

#### 性能优势对比

```php
// ❌ 低效方式：多次单独调用
$users = [];
for ($i = 1; $i <= 100; $i++) {
    $users[$i] = cache_kv_get('user.profile', ['id' => $i], function() use ($i) {
        return getUserFromDatabase($i);
    });
}
// 结果：可能产生100次Redis调用 + 100次数据库查询

// ✅ 高效方式：批量调用
$paramsList = [];
for ($i = 1; $i <= 100; $i++) {
    $paramsList[] = ['id' => $i];
}

$users = cache_kv_get_multiple('user.profile', $paramsList, function($missedKeys) {
    // 批量从数据库获取未命中的用户
    $userIds = [];
    $data = [];
    
    foreach ($missedKeys as $cacheKey) {
        $params = $cacheKey->getParams();
        $userIds[] = $params['id'];
    }
    
    // 一次数据库查询获取所有用户
    $users = getUsersFromDatabase($userIds);
    
    // 按键字符串组织返回数据
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $userId = $params['id'];
        $data[$keyString] = $users[$userId] ?? null;
    }
    
    return $data;
});
// 结果：最多2次Redis调用 + 1次数据库查询
```

#### 混合数据类型处理

```php
// 处理不同类型的缓存数据
$results = cache_kv_get_multiple('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 999] // 不存在的用户
], function($missedKeys) {
    $data = [];
    
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $userId = $params['id'];
        
        $user = getUserFromDatabase($userId);
        
        // 处理不存在的用户（缓存null值避免缓存穿透）
        $data[$keyString] = $user ?: null;
    }
    
    return $data;
});

// 处理结果，过滤null值
$validUsers = array_filter($results, function($user) {
    return $user !== null;
});
```

### 实际应用场景

#### 用户资料页面

```php
function getUserProfilePage($userId) {
    // 获取用户基础资料
    $userProfile = cache_kv_get('user.profile', ['id' => $userId], function() use ($userId) {
        return getUserProfile($userId);
    });
    
    // 获取用户设置
    $userSettings = cache_kv_get('user.settings', ['id' => $userId], function() use ($userId) {
        return getUserSettings($userId);
    });
    
    // 获取用户头像
    $userAvatar = cache_kv_get('user.avatar', ['id' => $userId, 'size' => 'large'], function() use ($userId) {
        return generateAvatarUrl($userId, 'large');
    });
    
    return [
        'profile' => $userProfile,
        'settings' => $userSettings,
        'avatar' => $userAvatar,
    ];
}
```

#### 商品列表页面

```php
function getProductList($productIds) {
    // 批量获取商品信息
    $paramsList = [];
    foreach ($productIds as $productId) {
        $paramsList[] = ['id' => $productId];
    }
    
    $products = cache_kv_get_multiple('product.info', $paramsList, function($missedKeys) {
        $data = [];
        $productIds = [];
        
        // 收集未命中的商品ID
        foreach ($missedKeys as $cacheKey) {
            $params = $cacheKey->getParams();
            $productIds[] = $params['id'];
        }
        
        // 批量从数据库获取商品信息
        $productsFromDB = getProductsFromDatabase($productIds);
        
        // 按键字符串组织返回数据
        foreach ($missedKeys as $cacheKey) {
            $keyString = (string)$cacheKey;
            $params = $cacheKey->getParams();
            $productId = $params['id'];
            $data[$keyString] = $productsFromDB[$productId] ?? null;
        }
        
        return $data;
    });
    
    return array_values(array_filter($products)); // 过滤null值并重新索引
}
```

#### API 响应缓存

```php
function getCachedApiResponse($endpoint, $params = []) {
    $cacheKey = [
        'endpoint' => $endpoint,
        'hash' => md5(json_encode($params))
    ];
    
    return cache_kv_get('api.response', $cacheKey, function() use ($endpoint, $params) {
        return callExternalAPI($endpoint, $params);
    }, 300); // API响应缓存5分钟
}

// 批量API调用
function getBatchApiResponses($requests) {
    $paramsList = [];
    foreach ($requests as $request) {
        $paramsList[] = [
            'endpoint' => $request['endpoint'],
            'hash' => md5(json_encode($request['params'] ?? []))
        ];
    }
    
    return cache_kv_get_multiple('api.response', $paramsList, function($missedKeys) use ($requests) {
        $data = [];
        
        foreach ($missedKeys as $cacheKey) {
            $keyString = (string)$cacheKey;
            $params = $cacheKey->getParams();
            
            // 找到对应的请求
            foreach ($requests as $request) {
                $requestHash = md5(json_encode($request['params'] ?? []));
                if ($params['hash'] === $requestHash && $params['endpoint'] === $request['endpoint']) {
                    $data[$keyString] = callExternalAPI($request['endpoint'], $request['params'] ?? []);
                    break;
                }
            }
        }
        
        return $data;
    });
}
```

#### 统计数据缓存

```php
function getDashboardStats($userId, $dateRange) {
    // 生成统计数据的缓存键
    $statsKey = [
        'user_id' => $userId,
        'date_range' => $dateRange,
        'version' => 'v1'
    ];
    
    return cache_kv_get('stats.dashboard', $statsKey, function() use ($userId, $dateRange) {
        return [
            'total_orders' => getOrderCount($userId, $dateRange),
            'total_revenue' => getRevenue($userId, $dateRange),
            'top_products' => getTopProducts($userId, $dateRange, 10),
            'generated_at' => time()
        ];
    }, 1800); // 统计数据缓存30分钟
}
```

---

## 统计功能

CacheKV 提供了完整的统计功能，帮助你监控缓存性能和识别热点数据。

### 基础统计信息

```php
// 获取全局统计
$stats = cache_kv_get_stats();

print_r($stats);
/*
输出：
Array
(
    [hits] => 850              // 命中次数
    [misses] => 150            // 未命中次数
    [sets] => 200              // 设置次数
    [deletes] => 10            // 删除次数
    [total_requests] => 1000   // 总请求次数
    [hit_rate] => 85%          // 命中率
    [enabled] => 1             // 统计是否启用
)
*/
```

### 热点键检测

```php
// 获取访问频率最高的键
$hotKeys = cache_kv_get_hot_keys(10); // 获取前10个热点键

print_r($hotKeys);
/*
输出：
Array
(
    [myapp:user:v1:profile:123] => Array
    (
        [key] => myapp:user:v1:profile:123
        [total_requests] => 500
        [hits] => 480
        [misses] => 20
        [hit_rate] => 96
    )
    [myapp:user:v1:profile:456] => Array
    (
        [key] => myapp:user:v1:profile:456
        [total_requests] => 300
        [hits] => 290
        [misses] => 10
        [hit_rate] => 96.67
    )
    // ... 更多热点键
)
*/
```

### 热点键自动续期

当某个键的访问频率达到阈值时，系统会自动延长其缓存时间，避免热点数据过期。

```php
// 配置热点键自动续期
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
4. 只在缓存命中时检查，性能开销极小

### 统计功能的实际应用

#### 性能监控

```php
function monitorCachePerformance() {
    $stats = cache_kv_get_stats();
    
    // 监控命中率
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    if ($hitRate < 80) {
        // 命中率过低，可能需要调整缓存策略
        logWarning("Cache hit rate is low: {$stats['hit_rate']}");
    }
    
    // 监控总请求量
    if ($stats['total_requests'] > 10000) {
        // 高并发场景，检查热点键
        $hotKeys = cache_kv_get_hot_keys(5);
        foreach ($hotKeys as $key => $info) {
            if ($info['total_requests'] > 1000) {
                logInfo("High traffic key detected: {$key} ({$info['total_requests']} requests)");
            }
        }
    }
}
```

#### 缓存优化建议

```php
function getCacheOptimizationSuggestions() {
    $stats = cache_kv_get_stats();
    $hotKeys = cache_kv_get_hot_keys(20);
    $suggestions = [];
    
    // 基于命中率的建议
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    if ($hitRate < 70) {
        $suggestions[] = "命中率较低({$stats['hit_rate']})，建议检查缓存键的设计和TTL设置";
    }
    
    // 基于热点键的建议
    foreach ($hotKeys as $key => $info) {
        if ($info['hit_rate'] < 90 && $info['total_requests'] > 100) {
            $suggestions[] = "热点键 {$key} 命中率较低({$info['hit_rate']}%)，建议增加TTL";
        }
    }
    
    // 基于请求量的建议
    if ($stats['total_requests'] > 50000) {
        $suggestions[] = "请求量较大，建议启用热点键自动续期功能";
    }
    
    return $suggestions;
}
```

#### 定期统计报告

```php
function generateCacheReport() {
    $stats = cache_kv_get_stats();
    $hotKeys = cache_kv_get_hot_keys(10);
    
    $report = [
        'timestamp' => date('Y-m-d H:i:s'),
        'summary' => [
            'total_requests' => $stats['total_requests'],
            'hit_rate' => $stats['hit_rate'],
            'cache_efficiency' => $stats['hits'] > 0 ? 'Good' : 'Poor'
        ],
        'hot_keys' => array_slice($hotKeys, 0, 5), // 前5个热点键
        'recommendations' => getCacheOptimizationSuggestions()
    ];
    
    // 保存报告或发送邮件
    file_put_contents('/var/log/cache_report_' . date('Y-m-d') . '.json', json_encode($report, JSON_PRETTY_PRINT));
    
    return $report;
}
```

### 统计配置选项

```php
'cache' => array(
    // 统计功能开关
    'enable_stats' => true,                 // 是否启用统计（默认：true）
    
    // 热点键检测配置
    'hot_key_threshold' => 100,             // 热点键阈值（默认：100次访问）
    
    // 自动续期配置
    'hot_key_auto_renewal' => true,         // 是否启用自动续期（默认：true）
    'hot_key_extend_ttl' => 7200,           // 延长的TTL（默认：2小时）
    'hot_key_max_ttl' => 86400,             // 最大TTL（默认：24小时）
),
```

**注意事项：**
- 统计功能会有轻微的性能开销，但在大多数场景下可以忽略
- 热点键自动续期只在缓存命中时触发，避免额外的性能损耗
- 统计数据存储在内存中，重启应用后会重置

---

## API 参考

### 辅助函数

| 函数 | 说明 | 参数 | 返回值 |
|------|------|------|--------|
| `cache_kv_get()` | 获取单个缓存 | `$template, $params, $callback, $ttl` | `mixed` |
| `cache_kv_get_multiple()` | 批量获取缓存 | `$template, $paramsArray, $callback` | `array` |
| `cache_kv_get_stats()` | 获取统计信息 | 无 | `array` |
| `cache_kv_get_hot_keys()` | 获取热点键 | `$limit` | `array` |

### 核心类

详细的类和方法文档请参考源码中的注释。

---

## 最佳实践

1. **合理设置TTL**：根据数据更新频率设置合适的缓存时间
2. **使用批量操作**：对于多个相关的缓存操作，优先使用批量方法
3. **监控统计信息**：定期检查命中率和热点键，优化缓存策略
4. **启用热点键续期**：对于高并发场景，启用自动续期功能
5. **合理的键设计**：使用清晰的分组和键名，便于管理和调试
