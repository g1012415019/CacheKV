# CacheKV Key 管理指南

CacheKV 的 KeyManager 提供了统一的缓存键命名规范和管理功能，帮助您构建可维护、可扩展的缓存架构。

## 核心价值

### 解决的问题
- ❌ 缓存键命名不规范，难以维护
- ❌ 键名冲突和重复
- ❌ 批量操作时键管理复杂
- ❌ 不同环境间的键隔离困难
- ❌ 缓存键的解析和验证繁琐

### KeyManager 的优势
- ✅ **统一命名规范**：标准化的键命名格式
- ✅ **模板化管理**：预定义和自定义键模板
- ✅ **环境隔离**：自动添加应用、环境、版本前缀
- ✅ **批量操作支持**：模式匹配和批量键生成
- ✅ **键解析验证**：完整的键解析和验证功能

## 快速开始

### 基本使用

```php
use Asfop\CacheKV\Cache\KeyManager;

// 创建 KeyManager 实例
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1'
]);

// 生成标准化的缓存键
$userKey = $keyManager->make('user', ['id' => 123]);
// 结果: myapp:prod:v1:user:123

// 在缓存中使用
$user = $cache->get($userKey, function() {
    return getUserFromDatabase(123);
});
```

### 配置选项

```php
$config = [
    'app_prefix' => 'myapp',        // 应用前缀
    'env_prefix' => 'prod',         // 环境前缀 (dev/test/prod)
    'version' => 'v1',              // 版本号
    'separator' => ':',             // 分隔符
    'templates' => [                // 自定义模板
        'order' => 'order:{id}',
        'cart' => 'cart:{user_id}'
    ]
];

$keyManager = new KeyManager($config);
```

## 核心功能

### 1. 键生成

#### 基本键生成
```php
// 使用预定义模板
$userKey = $keyManager->make('user', ['id' => 123]);
// 结果: myapp:prod:v1:user:123

$productKey = $keyManager->make('product_detail', ['id' => 456]);
// 结果: myapp:prod:v1:product:detail:456
```

#### 复杂参数键生成
```php
// 分页数据
$listKey = $keyManager->make('category_products', [
    'id' => 'electronics',
    'page' => 1
]);
// 结果: myapp:prod:v1:category:products:electronics:page:1

// 搜索结果
$searchKey = $keyManager->make('search', [
    'query' => 'iphone',
    'page' => 2
]);
// 结果: myapp:prod:v1:search:iphone:page:2
```

#### 哈希参数处理
```php
// 处理复杂参数（自动哈希）
$apiKey = $keyManager->makeWithHash('api_response', [
    'endpoint' => 'products',
    'params_hash' => ['category' => 'electronics', 'sort' => 'price']
], ['params_hash']);
// 结果: myapp:prod:v1:api:products:a1b2c3d4e5f6...
```

### 2. 模板管理

#### 预定义模板
KeyManager 内置了常用的键模板：

```php
// 用户相关
'user' => 'user:{id}'
'user_profile' => 'user:profile:{id}'
'user_settings' => 'user:settings:{id}'
'user_permissions' => 'user:permissions:{id}'

// 商品相关
'product' => 'product:{id}'
'product_detail' => 'product:detail:{id}'
'product_price' => 'product:price:{id}'

// 列表相关
'list' => 'list:{type}:{id}'
'page' => 'page:{type}:{page}:size:{size}'
'search' => 'search:{query}:page:{page}'

// API 相关
'api_response' => 'api:{endpoint}:{params_hash}'
'api_token' => 'api:token:{user_id}'

// 系统相关
'config' => 'config:{key}'
'stats' => 'stats:{type}:{date}'
'lock' => 'lock:{resource}:{id}'
```

#### 自定义模板
```php
// 单个添加
$keyManager->addTemplate('order', 'order:{id}');
$keyManager->addTemplate('cart', 'cart:{user_id}');

// 批量添加
$keyManager->addTemplates([
    'notification' => 'notification:{user_id}:{type}:{id}',
    'report' => 'report:{type}:{date}:{format}',
    'log' => 'log:{level}:{date}:{hour}'
]);
```

### 3. 键解析

```php
$key = 'myapp:prod:v1:user:settings:789';
$parsed = $keyManager->parse($key);

/*
结果:
[
    'full_key' => 'myapp:prod:v1:user:settings:789',
    'parts' => ['myapp', 'prod', 'v1', 'user', 'settings', '789'],
    'has_prefix' => true,
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'business_key' => 'user:settings:789'
]
*/
```

### 4. 模式匹配

用于批量操作和清理：

```php
// 所有用户键
$userPattern = $keyManager->pattern('user', ['id' => '*']);
// 结果: myapp:prod:v1:user:*

// 特定用户的所有资料键
$userProfilePattern = $keyManager->pattern('user_profile', ['id' => 123]);
// 结果: myapp:prod:v1:user:profile:123

// 部分匹配
$categoryPattern = $keyManager->pattern('category_products', [
    'id' => 'electronics',
    'page' => '*'
]);
// 结果: myapp:prod:v1:category:products:electronics:page:*
```

### 5. 键验证和清理

```php
// 键验证
$validKey = 'myapp:prod:v1:user:123';
$invalidKey = 'invalid key with spaces!@#';

$isValid1 = $keyManager->validate($validKey);   // true
$isValid2 = $keyManager->validate($invalidKey); // false

// 键清理
$sanitized = $keyManager->sanitize($invalidKey);
// 结果: 'invalid_key_with_spaces___'
```

## 实际应用场景

### 场景1：用户数据缓存

```php
class UserService
{
    private $cache;
    private $keyManager;
    
    public function __construct($cache, $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }
    
    public function getUser($userId)
    {
        $key = $this->keyManager->make('user', ['id' => $userId]);
        
        return $this->cache->get($key, function() use ($userId) {
            return $this->loadUserFromDatabase($userId);
        });
    }
    
    public function getUserProfile($userId)
    {
        $key = $this->keyManager->make('user_profile', ['id' => $userId]);
        
        return $this->cache->get($key, function() use ($userId) {
            return $this->loadUserProfileFromDatabase($userId);
        });
    }
    
    public function clearUserCache($userId)
    {
        // 清除用户相关的所有缓存
        $patterns = [
            $this->keyManager->pattern('user', ['id' => $userId]),
            $this->keyManager->pattern('user_profile', ['id' => $userId]),
            $this->keyManager->pattern('user_settings', ['id' => $userId])
        ];
        
        foreach ($patterns as $pattern) {
            $this->cache->clearPattern($pattern);
        }
    }
}
```

### 场景2：电商产品缓存

```php
class ProductService
{
    private $cache;
    private $keyManager;
    
    public function getProducts($categoryId, $page = 1, $size = 20)
    {
        $key = $this->keyManager->make('category_products', [
            'id' => $categoryId,
            'page' => $page
        ]);
        
        return $this->cache->get($key, function() use ($categoryId, $page, $size) {
            return $this->loadProductsFromDatabase($categoryId, $page, $size);
        }, 1800); // 30分钟缓存
    }
    
    public function getProductDetail($productId)
    {
        $key = $this->keyManager->make('product_detail', ['id' => $productId]);
        
        return $this->cache->get($key, function() use ($productId) {
            return $this->loadProductDetailFromDatabase($productId);
        });
    }
    
    public function updateProductPrice($productId, $newPrice)
    {
        // 更新数据库
        $this->updatePriceInDatabase($productId, $newPrice);
        
        // 清除相关缓存
        $keys = [
            $this->keyManager->make('product', ['id' => $productId]),
            $this->keyManager->make('product_detail', ['id' => $productId]),
            $this->keyManager->make('product_price', ['id' => $productId])
        ];
        
        foreach ($keys as $key) {
            $this->cache->forget($key);
        }
    }
}
```

### 场景3：API 响应缓存

```php
class ApiCacheService
{
    private $cache;
    private $keyManager;
    
    public function getCachedApiResponse($endpoint, $params = [])
    {
        $key = $this->keyManager->makeWithHash('api_response', [
            'endpoint' => $endpoint,
            'params_hash' => $params
        ], ['params_hash']);
        
        return $this->cache->get($key, function() use ($endpoint, $params) {
            return $this->callExternalApi($endpoint, $params);
        }, 300); // 5分钟缓存
    }
    
    private function callExternalApi($endpoint, $params)
    {
        // 实际的 API 调用逻辑
        // ...
    }
}
```

### 场景4：批量数据操作

```php
class BatchUserService
{
    private $cache;
    private $keyManager;
    
    public function getUsers($userIds)
    {
        // 生成所有用户的键
        $keys = array_map(function($id) {
            return $this->keyManager->make('user', ['id' => $id]);
        }, $userIds);
        
        // 批量获取，自动处理缓存未命中
        return $this->cache->getMultiple($keys, function($missingKeys) {
            $missingIds = [];
            
            // 从键中解析出用户ID
            foreach ($missingKeys as $key) {
                $parsed = $this->keyManager->parse($key);
                $userId = explode(':', $parsed['business_key'])[1];
                $missingIds[] = $userId;
            }
            
            // 批量从数据库获取
            $users = $this->loadUsersFromDatabase($missingIds);
            
            // 重新组织数据，键为缓存键
            $result = [];
            foreach ($users as $user) {
                $key = $this->keyManager->make('user', ['id' => $user['id']]);
                $result[$key] = $user;
            }
            
            return $result;
        });
    }
}
```

## 最佳实践

### 1. 命名规范

```php
// ✅ 好的做法
$keyManager->addTemplates([
    'user' => 'user:{id}',
    'user_profile' => 'user:profile:{id}',
    'product_detail' => 'product:detail:{id}',
    'order_items' => 'order:items:{order_id}'
]);

// ❌ 避免的做法
$keyManager->addTemplates([
    'u' => 'u:{id}',                    // 名称太短
    'userProfileData' => 'upd:{id}',    // 不一致的命名
    'product-detail' => 'pd:{id}'       // 使用连字符
]);
```

### 2. 参数验证

```php
public function make($template, $params = [], $withPrefix = true)
{
    // 验证必需参数
    if ($template === 'user' && !isset($params['id'])) {
        throw new \InvalidArgumentException('User ID is required');
    }
    
    // 验证参数类型
    if (isset($params['id']) && !is_numeric($params['id'])) {
        throw new \InvalidArgumentException('User ID must be numeric');
    }
    
    return $this->keyManager->make($template, $params, $withPrefix);
}
```

### 3. 环境配置

```php
// 开发环境
$devKeyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'dev',
    'version' => 'v1'
]);

// 生产环境
$prodKeyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1'
]);

// 测试环境
$testKeyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'test',
    'version' => 'v1'
]);
```

### 4. 版本管理

```php
// 当需要更新缓存结构时，增加版本号
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v2'  // 从 v1 升级到 v2
]);

// 这样新旧版本的缓存不会冲突
// v1: myapp:prod:v1:user:123
// v2: myapp:prod:v2:user:123
```

### 5. 性能优化

```php
// 缓存 KeyManager 实例
class KeyManagerFactory
{
    private static $instances = [];
    
    public static function getInstance($config)
    {
        $key = md5(serialize($config));
        
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new KeyManager($config);
        }
        
        return self::$instances[$key];
    }
}
```

## API 参考

### 构造方法
```php
public function __construct($config = [])
```

### 核心方法

| 方法 | 说明 | 参数 | 返回值 |
|------|------|------|--------|
| `make($template, $params, $withPrefix)` | 生成缓存键 | 模板名、参数数组、是否包含前缀 | string |
| `makeWithHash($template, $params, $hashParams, $withPrefix)` | 生成带哈希的缓存键 | 模板名、参数数组、哈希参数、是否包含前缀 | string |
| `pattern($template, $params, $withPrefix)` | 生成模式匹配键 | 模板名、参数数组、是否包含前缀 | string |
| `parse($key)` | 解析缓存键 | 缓存键 | array |
| `validate($key)` | 验证键格式 | 缓存键 | bool |
| `sanitize($key)` | 清理键名 | 原始键名 | string |
| `addTemplate($name, $pattern)` | 添加模板 | 模板名、模板模式 | void |
| `addTemplates($templates)` | 批量添加模板 | 模板数组 | void |
| `getTemplates()` | 获取所有模板 | 无 | array |

## 常见问题

### Q: 如何处理键名过长的问题？
A: 使用 `makeWithHash` 方法对复杂参数进行哈希处理：

```php
$key = $keyManager->makeWithHash('search', [
    'query' => 'very long search query...',
    'filters' => ['category' => 'electronics', 'brand' => 'apple', ...]
], ['query', 'filters']);
```

### Q: 如何在不同环境间迁移缓存？
A: 通过修改环境前缀来隔离不同环境的缓存：

```php
// 从开发环境迁移到生产环境时，只需修改配置
$config['env_prefix'] = 'prod'; // 从 'dev' 改为 'prod'
```

### Q: 如何批量清理相关缓存？
A: 使用模式匹配功能：

```php
// 清理某个用户的所有缓存
$patterns = [
    $keyManager->pattern('user', ['id' => $userId]),
    $keyManager->pattern('user_profile', ['id' => $userId]),
    $keyManager->pattern('user_settings', ['id' => $userId])
];

foreach ($patterns as $pattern) {
    $cache->clearPattern($pattern);
}
```

### Q: 如何处理键冲突？
A: 使用合适的前缀和模板设计：

```php
// ✅ 好的设计
'user_profile' => 'user:profile:{id}'
'company_profile' => 'company:profile:{id}'

// ❌ 可能冲突的设计
'profile' => 'profile:{id}'  // 不明确是用户还是公司
```

---

通过 KeyManager，您可以构建一个标准化、可维护的缓存键管理系统，大大简化缓存操作的复杂性。
