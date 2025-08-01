# 最佳实践

## 项目结构建议

```
src/
├── Cache/
│   ├── CacheTemplates.php      # 缓存模板常量定义
│   ├── CacheHelper.php         # 缓存辅助类
│   └── CacheConfig.php         # 缓存配置
├── Services/
│   ├── UserService.php         # 业务服务
│   └── ProductService.php
└── Models/
    ├── User.php
    └── Product.php

config/
└── cache.php                   # 缓存配置文件
```

## 1. 模板名称管理

### ✅ 推荐：使用常量类

```php
// src/Cache/CacheTemplates.php
class CacheTemplates {
    // 用户相关
    const USER = 'user_profile';
    const USER_PERMISSIONS = 'user_permissions';
    const USER_SETTINGS = 'user_settings';
    
    // 商品相关
    const PRODUCT = 'product_info';
    const PRODUCT_PRICE = 'product_price';
    const PRODUCT_INVENTORY = 'product_inventory';
    
    // 订单相关
    const ORDER = 'order_detail';
    const ORDER_ITEMS = 'order_items';
    
    // API 缓存
    const API_WEATHER = 'api_weather';
    const API_EXCHANGE_RATE = 'api_exchange_rate';
}
```

### ❌ 避免：硬编码字符串

```php
// 不推荐
$user = cache_kv_get('user', ['id' => 123], $callback);
$product = cache_kv_get('product_info', ['id' => 456], $callback);
```

## 2. 缓存辅助类设计

### ✅ 推荐：按业务领域分组

```php
// src/Cache/CacheHelper.php
class CacheHelper {
    private static $cache;
    
    private static function getCache() {
        if (!self::$cache) {
            self::$cache = CacheKVFactory::store();
        }
        return self::$cache;
    }
    
    // 用户相关缓存
    public static function getUser($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            return UserService::findById($userId);
        });
    }
    
    public static function getUserPermissions($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER_PERMISSIONS, ['user_id' => $userId], function() use ($userId) {
            return PermissionService::getUserPermissions($userId);
        });
    }
    
    public static function clearUserCache($userId) {
        $cache = self::getCache();
        $cache->deleteByTemplate(CacheTemplates::USER, ['id' => $userId]);
        $cache->deleteByTemplate(CacheTemplates::USER_PERMISSIONS, ['user_id' => $userId]);
        $cache->deleteByTemplate(CacheTemplates::USER_SETTINGS, ['user_id' => $userId]);
    }
    
    // 商品相关缓存
    public static function getProduct($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT, ['id' => $productId], function() use ($productId) {
            return ProductService::findById($productId);
        });
    }
    
    public static function getProductPrice($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT_PRICE, ['id' => $productId], function() use ($productId) {
            return PriceService::getPrice($productId);
        }, 300); // 价格缓存5分钟
    }
    
    public static function clearProductCache($productId) {
        $cache = self::getCache();
        $cache->deleteByTemplate(CacheTemplates::PRODUCT, ['id' => $productId]);
        $cache->deleteByTemplate(CacheTemplates::PRODUCT_PRICE, ['id' => $productId]);
        $cache->deleteByTemplate(CacheTemplates::PRODUCT_INVENTORY, ['id' => $productId]);
    }
}
```

## 3. 配置管理

### ✅ 推荐：环境分离配置

```php
// config/cache.php
return [
    'default' => env('CACHE_DRIVER', 'redis'),
    
    'stores' => [
        'redis' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\RedisDriver(
                new \Predis\Client([
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', 6379),
                    'database' => env('REDIS_DB', 0),
                ])
            ),
            'ttl' => env('CACHE_TTL', 3600)
        ],
        
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    
    'key_manager' => [
        'app_prefix' => env('APP_NAME', 'myapp'),
        'env_prefix' => env('APP_ENV', 'prod'),
        'version' => env('CACHE_VERSION', 'v1'),
        'templates' => [
            CacheTemplates::USER => 'user:{id}',
            CacheTemplates::PRODUCT => 'product:{id}',
            // ... 更多模板
        ]
    ]
];
```

## 4. 缓存时间策略

### ✅ 推荐：根据数据特性设置不同的 TTL

```php
class CacheHelper {
    // 用户基本信息：变化较少，缓存时间长
    public static function getUser($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            return UserService::findById($userId);
        }, 3600); // 1小时
    }
    
    // 商品价格：变化频繁，缓存时间短
    public static function getProductPrice($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT_PRICE, ['id' => $productId], function() use ($productId) {
            return PriceService::getPrice($productId);
        }, 300); // 5分钟
    }
    
    // 库存信息：实时性要求高，缓存时间很短
    public static function getProductInventory($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT_INVENTORY, ['id' => $productId], function() use ($productId) {
            return InventoryService::getInventory($productId);
        }, 60); // 1分钟
    }
    
    // API 响应：外部依赖，缓存时间适中
    public static function getWeather($city) {
        return self::getCache()->getByTemplate(CacheTemplates::API_WEATHER, ['city' => $city], function() use ($city) {
            return WeatherAPI::getCurrentWeather($city);
        }, 1800); // 30分钟
    }
}
```

## 5. 标签管理策略

### ✅ 推荐：合理使用标签进行批量管理

```php
class CacheHelper {
    public static function setUserCache($userId, $userData) {
        $cache = self::getCache();
        
        // 设置用户缓存时添加标签
        $cache->setByTemplateWithTag(
            CacheTemplates::USER, 
            ['id' => $userId], 
            $userData,
            ['users', "user_{$userId}", 'user_profiles']
        );
    }
    
    public static function setProductCache($productId, $productData) {
        $cache = self::getCache();
        $categoryId = $productData['category_id'];
        
        // 设置商品缓存时添加分类标签
        $cache->setByTemplateWithTag(
            CacheTemplates::PRODUCT, 
            ['id' => $productId], 
            $productData,
            ['products', "product_{$productId}", "category_{$categoryId}"]
        );
    }
    
    // 批量清除操作
    public static function clearAllUsers() {
        self::getCache()->clearTag('users');
    }
    
    public static function clearCategoryProducts($categoryId) {
        self::getCache()->clearTag("category_{$categoryId}");
    }
}
```

## 6. 错误处理

### ✅ 推荐：优雅的错误处理

```php
class CacheHelper {
    public static function getUser($userId) {
        try {
            return self::getCache()->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
                $user = UserService::findById($userId);
                if (!$user) {
                    throw new UserNotFoundException("User {$userId} not found");
                }
                return $user;
            });
        } catch (UserNotFoundException $e) {
            // 用户不存在，返回 null 或抛出异常
            return null;
        } catch (\Exception $e) {
            // 缓存或数据库异常，记录日志但不影响业务
            error_log("Cache error for user {$userId}: " . $e->getMessage());
            
            // 降级处理：直接查询数据库
            return UserService::findById($userId);
        }
    }
}
```

## 7. 性能优化

### ✅ 推荐：批量操作优化

```php
class CacheHelper {
    public static function getUsers($userIds) {
        $cache = self::getCache();
        $keyManager = CacheKVFactory::getKeyManager();
        
        // 生成所有用户的缓存键
        $userKeys = array_map(function($id) use ($keyManager) {
            return $keyManager->make(CacheTemplates::USER, ['id' => $id]);
        }, $userIds);
        
        // 批量获取，自动处理缓存命中和未命中
        return $cache->getMultiple($userKeys, function($missingKeys) {
            // 只查询缓存未命中的用户
            $missingUserIds = array_map(function($key) {
                preg_match('/user:(\d+)$/', $key, $matches);
                return (int)$matches[1];
            }, $missingKeys);
            
            $users = UserService::findByIds($missingUserIds);
            
            // 转换为键值对格式
            $result = [];
            foreach ($users as $user) {
                $key = CacheKVFactory::getKeyManager()->make(CacheTemplates::USER, ['id' => $user['id']]);
                $result[$key] = $user;
            }
            
            return $result;
        });
    }
}
```

## 8. 监控和调试

### ✅ 推荐：添加缓存监控

```php
class CacheHelper {
    private static $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];
    
    public static function getUser($userId) {
        $startTime = microtime(true);
        
        $result = self::getCache()->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            self::$stats['misses']++;
            return UserService::findById($userId);
        });
        
        if ($result !== null) {
            self::$stats['hits']++;
        }
        
        $duration = microtime(true) - $startTime;
        
        // 记录慢查询
        if ($duration > 0.1) {
            error_log("Slow cache operation for user {$userId}: {$duration}s");
        }
        
        return $result;
    }
    
    public static function getStats() {
        return self::$stats;
    }
}
```

## 9. 版本管理和迁移

### ✅ 推荐：版本化缓存键

```php
// 数据结构变更时，升级版本号
CacheKVFactory::setDefaultConfig([
    'key_manager' => [
        'version' => 'v2', // 从 v1 升级到 v2
        'templates' => [
            CacheTemplates::USER => 'user:{id}', // 新的数据结构
        ]
    ]
]);

// 平滑迁移函数
function migrateUserCacheV1ToV2() {
    $cache = CacheKVFactory::store();
    
    // 查找所有 v1 版本的用户缓存
    $oldPattern = "myapp:prod:v1:user_profile:*";
    $oldKeys = $cache->keys($oldPattern);
    
    foreach ($oldKeys as $oldKey) {
        // 提取用户 ID
        preg_match('/user_profile:(\d+)$/', $oldKey, $matches);
        $userId = (int)$matches[1];
        
        // 获取旧数据
        $oldData = $cache->get($oldKey);
        
        // 转换数据结构
        $newData = transformUserDataV1ToV2($oldData);
        
        // 写入新版本缓存
        $cache->setByTemplate(CacheTemplates::USER, ['id' => $userId], $newData);
        
        // 删除旧版本缓存
        $cache->delete($oldKey);
    }
}
```

## 10. 测试建议

### ✅ 推荐：编写缓存相关测试

```php
class CacheHelperTest extends PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        // 使用 Array 驱动进行测试
        CacheKVFactory::setDefaultConfig([
            'default' => 'array',
            'stores' => [
                'array' => [
                    'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
                    'ttl' => 3600
                ]
            ],
            'key_manager' => [
                'app_prefix' => 'test',
                'env_prefix' => 'test',
                'version' => 'v1',
                'templates' => [
                    CacheTemplates::USER => 'user:{id}',
                ]
            ]
        ]);
    }
    
    public function testGetUserFromCache() {
        // 第一次调用，应该执行回调函数
        $user = CacheHelper::getUser(123);
        $this->assertEquals(123, $user['id']);
        
        // 第二次调用，应该从缓存获取
        $cachedUser = CacheHelper::getUser(123);
        $this->assertEquals($user, $cachedUser);
    }
    
    public function testClearUserCache() {
        // 设置缓存
        CacheHelper::getUser(123);
        
        // 清除缓存
        CacheHelper::clearUserCache(123);
        
        // 验证缓存已清除
        $cache = CacheKVFactory::store();
        $key = CacheKVFactory::getKeyManager()->make(CacheTemplates::USER, ['id' => 123]);
        $this->assertNull($cache->get($key));
    }
}
```

这些最佳实践将帮助你在生产环境中更好地使用 CacheKV，提升应用性能和可维护性。
