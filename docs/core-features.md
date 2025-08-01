# 核心功能

CacheKV 提供了一套完整的缓存解决方案，本文档详细介绍各项核心功能。

## 1. 自动回填缓存

### 功能说明
CacheKV 的核心功能是实现"若无则从数据源获取并回填缓存"的模式，一行代码解决复杂的缓存逻辑。

### 基本用法

```php
// 使用辅助函数
$user = cache_kv_get(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 使用 CacheKV 实例
$cache = CacheKVFactory::store();
$user = $cache->getByTemplate(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

### 工作流程

1. **检查缓存**：根据模板和参数生成缓存键，检查缓存是否存在
2. **缓存命中**：如果缓存存在且未过期，直接返回缓存数据
3. **缓存未命中**：执行回调函数获取数据
4. **自动回填**：将获取的数据写入缓存
5. **返回数据**：返回获取的数据

### 高级选项

```php
// 自定义 TTL
$user = cache_kv_get(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 1800); // 30分钟

// 条件缓存
$user = cache_kv_get(CacheTemplates::USER, ['id' => 123], function() {
    $user = getUserFromDatabase(123);
    // 只缓存有效用户
    return $user && $user['status'] === 'active' ? $user : null;
});
```

## 2. 批量操作

### 功能说明
CacheKV 支持批量操作，自动优化性能，避免 N+1 查询问题。

### 基本用法

```php
$cache = CacheKVFactory::store();
$keyManager = CacheKVFactory::getKeyManager();

// 生成批量缓存键
$userIds = [1, 2, 3, 4, 5];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make(CacheTemplates::USER, ['id' => $id]);
}, $userIds);

// 批量获取
$users = $cache->getMultiple($userKeys, function($missingKeys) {
    // 只查询缓存未命中的数据
    $missingUserIds = extractIdsFromKeys($missingKeys);
    return getUsersFromDatabase($missingUserIds);
});
```

### 性能优势

| 操作 | 传统方式 | CacheKV 批量操作 | 性能提升 |
|------|----------|------------------|----------|
| 获取100个用户 | 100次缓存查询 + N次数据库查询 | 1次批量缓存查询 + 1次数据库查询 | **10-100x** |
| 网络请求 | 100次 | 1-2次 | **50-100x** |
| 数据库连接 | 可能100次 | 最多1次 | **显著减少** |

### 批量设置

```php
// 批量设置缓存
$data = [
    $keyManager->make(CacheTemplates::USER, ['id' => 1]) => $user1Data,
    $keyManager->make(CacheTemplates::USER, ['id' => 2]) => $user2Data,
    $keyManager->make(CacheTemplates::USER, ['id' => 3]) => $user3Data,
];

$cache->setMultiple($data, 3600);
```

## 3. 标签管理

### 功能说明
标签管理允许你为缓存项添加标签，然后批量操作具有相同标签的缓存项。

### 基本用法

```php
$cache = CacheKVFactory::store();

// 设置带标签的缓存
$cache->setByTemplateWithTag(
    CacheTemplates::USER, 
    ['id' => 123], 
    $userData, 
    ['users', 'vip_users', 'active_users']
);

// 批量清除标签相关的所有缓存
$cache->clearTag('users'); // 清除所有用户缓存
$cache->clearTag('vip_users'); // 只清除VIP用户缓存
```

### 标签策略

```php
class CacheHelper {
    public static function setUserCache($userId, $userData) {
        $cache = CacheKVFactory::store();
        
        // 根据用户属性设置不同标签
        $tags = ['users', "user_{$userId}"];
        
        if ($userData['is_vip']) {
            $tags[] = 'vip_users';
        }
        
        if ($userData['department_id']) {
            $tags[] = "department_{$userData['department_id']}";
        }
        
        $cache->setByTemplateWithTag(
            CacheTemplates::USER, 
            ['id' => $userId], 
            $userData,
            $tags
        );
    }
    
    // 按部门清除用户缓存
    public static function clearDepartmentUsers($departmentId) {
        $cache = CacheKVFactory::store();
        $cache->clearTag("department_{$departmentId}");
    }
}
```

### 标签层次结构

```php
// 层次化标签管理
$tags = [
    'products',                    // 所有商品
    "category_{$categoryId}",      // 特定分类
    "brand_{$brandId}",           // 特定品牌
    "product_{$productId}",       // 特定商品
];

// 清除操作
$cache->clearTag('products');              // 清除所有商品缓存
$cache->clearTag("category_{$categoryId}"); // 清除特定分类缓存
$cache->clearTag("brand_{$brandId}");      // 清除特定品牌缓存
```

## 4. 键管理系统

### 功能说明
CacheKV 提供统一的键管理系统，支持环境隔离、版本管理和模板化键生成。

### 键结构

```
{app_prefix}:{env_prefix}:{version}:{template}:{params}
```

示例：`myapp:prod:v1:user_profile:123`

### 配置示例

```php
CacheKVFactory::setDefaultConfig([
    'key_manager' => [
        'app_prefix' => 'myapp',        // 应用前缀
        'env_prefix' => 'prod',         // 环境前缀
        'version' => 'v1',              // 版本号
        'separator' => ':',             // 分隔符
        'templates' => [
            CacheTemplates::USER => 'user:{id}',
            CacheTemplates::PRODUCT => 'product:{id}',
            CacheTemplates::ORDER => 'order:{id}:{status}',
        ]
    ]
]);
```

### 键生成

```php
$keyManager = CacheKVFactory::getKeyManager();

// 简单参数
$userKey = $keyManager->make(CacheTemplates::USER, ['id' => 123]);
// 结果: myapp:prod:v1:user_profile:123

// 复杂参数
$orderKey = $keyManager->make(CacheTemplates::ORDER, [
    'id' => 456, 
    'status' => 'pending'
]);
// 结果: myapp:prod:v1:order_detail:456:pending
```

### 环境隔离

```php
// 开发环境
CacheKVFactory::setDefaultConfig([
    'key_manager' => [
        'env_prefix' => 'dev',
        // ...
    ]
]);

// 测试环境
CacheKVFactory::setDefaultConfig([
    'key_manager' => [
        'env_prefix' => 'test',
        // ...
    ]
]);

// 生产环境
CacheKVFactory::setDefaultConfig([
    'key_manager' => [
        'env_prefix' => 'prod',
        // ...
    ]
]);
```

## 5. 多驱动支持

### 支持的驱动

#### Redis 驱动
```php
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 使用 Predis
$redis = new \Predis\Client(['host' => '127.0.0.1', 'port' => 6379]);
$driver = new RedisDriver($redis);

// 使用 PhpRedis
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisDriver($redis);
```

#### Array 驱动（内存）
```php
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

$driver = new ArrayDriver();
```

### 多存储配置

```php
CacheKVFactory::setDefaultConfig([
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => new RedisDriver($redis),
            'ttl' => 3600
        ],
        'memory' => [
            'driver' => new ArrayDriver(),
            'ttl' => 1800
        ]
    ]
]);

// 使用不同存储
$redisCache = CacheKVFactory::store('redis');
$memoryCache = CacheKVFactory::store('memory');
```

## 6. TTL 管理

### 全局 TTL
```php
CacheKVFactory::setDefaultConfig([
    'stores' => [
        'redis' => [
            'driver' => $driver,
            'ttl' => 3600  // 默认1小时
        ]
    ]
]);
```

### 动态 TTL
```php
// 方法级别 TTL
$user = cache_kv_get(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 1800); // 30分钟

// 模板级别 TTL
$cache->getByTemplate(CacheTemplates::USER, ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 7200); // 2小时
```

### TTL 策略

```php
class CacheHelper {
    // 根据数据类型设置不同 TTL
    public static function getUser($userId) {
        return cache_kv_get(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            return getUserFromDatabase($userId);
        }, 3600); // 用户信息：1小时
    }
    
    public static function getProductPrice($productId) {
        return cache_kv_get(CacheTemplates::PRODUCT_PRICE, ['id' => $productId], function() use ($productId) {
            return getProductPrice($productId);
        }, 300); // 商品价格：5分钟
    }
    
    public static function getApiData($endpoint) {
        return cache_kv_get(CacheTemplates::API_DATA, ['endpoint' => $endpoint], function() use ($endpoint) {
            return callExternalAPI($endpoint);
        }, 1800); // API数据：30分钟
    }
}
```

## 7. 错误处理

### 异常处理
```php
try {
    $user = cache_kv_get(CacheTemplates::USER, ['id' => 123], function() {
        $user = getUserFromDatabase(123);
        if (!$user) {
            throw new UserNotFoundException('User not found');
        }
        return $user;
    });
} catch (UserNotFoundException $e) {
    // 处理用户不存在
    return null;
} catch (\Exception $e) {
    // 处理其他异常
    Log::error('Cache error: ' . $e->getMessage());
    return getUserFromDatabase(123); // 降级处理
}
```

### 降级策略
```php
class CacheHelper {
    public static function getUser($userId) {
        try {
            return cache_kv_get(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
                return getUserFromDatabase($userId);
            });
        } catch (\Exception $e) {
            // 缓存异常时直接查询数据库
            Log::warning("Cache failed for user {$userId}, fallback to database");
            return getUserFromDatabase($userId);
        }
    }
}
```

## 8. 性能监控

### 缓存统计
```php
class CacheMonitor {
    private static $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'errors' => 0
    ];
    
    public static function recordHit() {
        self::$stats['hits']++;
    }
    
    public static function recordMiss() {
        self::$stats['misses']++;
    }
    
    public static function getHitRate() {
        $total = self::$stats['hits'] + self::$stats['misses'];
        return $total > 0 ? self::$stats['hits'] / $total : 0;
    }
    
    public static function getStats() {
        return self::$stats;
    }
}
```

### 性能分析
```php
class CacheHelper {
    public static function getUser($userId) {
        $startTime = microtime(true);
        
        $result = cache_kv_get(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            CacheMonitor::recordMiss();
            return getUserFromDatabase($userId);
        });
        
        if ($result !== null) {
            CacheMonitor::recordHit();
        }
        
        $duration = microtime(true) - $startTime;
        
        // 记录慢查询
        if ($duration > 0.1) {
            Log::warning("Slow cache operation for user {$userId}: {$duration}s");
        }
        
        return $result;
    }
}
```

这些核心功能构成了 CacheKV 的完整功能体系，为开发者提供了强大而灵活的缓存解决方案。
