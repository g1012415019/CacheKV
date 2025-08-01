# 核心功能

CacheKV 提供四大核心功能，让缓存管理变得简单高效。本指南将详细介绍每个功能的使用方法。

## 🎯 功能概览

| 功能 | 核心价值 | 使用场景 |
|------|----------|----------|
| **自动回填缓存** | 一行代码搞定缓存逻辑 | 所有缓存场景 |
| **批量操作** | 避免 N+1 查询问题 | 列表页、批量查询 |
| **标签管理** | 批量失效相关缓存 | 数据更新、缓存清理 |
| **Key 管理** | 统一键命名规范 | 大型项目、团队协作 |

## 1. 自动回填缓存

### 核心理念

**"若无则从数据源获取并回填缓存"** - 这是 CacheKV 最核心的功能。

### 传统方式 vs CacheKV

```php
// ❌ 传统方式：手动管理缓存
function getUser($userId) {
    $cacheKey = "user:{$userId}";
    
    if ($cache->has($cacheKey)) {
        return $cache->get($cacheKey);
    }
    
    $user = getUserFromDatabase($userId);
    if ($user) {
        $cache->set($cacheKey, $user, 3600);
    }
    
    return $user;
}

// ✅ CacheKV 方式：自动管理
function getUser($userId) {
    return $cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
        return getUserFromDatabase($userId);
    });
}
```

### 基本用法

```php
// 获取用户信息
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 获取商品信息
$product = $cache->getByTemplate('product', ['id' => 456], function() {
    return getProductFromDatabase(456);
}, 1800); // 自定义30分钟过期时间
```

### 工作流程

```
1. 检查缓存是否存在
   ├─ 存在 → 直接返回缓存数据
   └─ 不存在 → 执行回调函数
       ├─ 获取数据源数据
       ├─ 自动写入缓存
       └─ 返回数据
```

### 高级特性

#### 空值缓存（防穿透）

```php
$user = $cache->getByTemplate('user', ['id' => 999999], function() {
    return getUserFromDatabase(999999); // 返回 null
});

// 即使返回 null，也会被缓存，防止重复查询数据库
```

#### 自定义过期时间

```php
// 不同类型数据使用不同过期时间
$userInfo = $cache->getByTemplate('user', ['id' => $id], $callback, 3600);    // 1小时
$productPrice = $cache->getByTemplate('price', ['id' => $id], $callback, 600); // 10分钟
$apiResponse = $cache->getByTemplate('api', ['key' => $key], $callback, 300);  // 5分钟
```

## 2. 批量操作

### 解决的问题

批量操作解决了经典的 **N+1 查询问题**：

```php
// ❌ N+1 查询问题
$users = [];
foreach ($userIds as $id) {
    $users[] = $cache->getByTemplate('user', ['id' => $id], function() use ($id) {
        return getUserFromDatabase($id); // 每个ID都查询一次数据库
    });
}

// ✅ 批量操作解决方案
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    // 只查询缓存中不存在的用户
    $missingIds = extractIdsFromKeys($missingKeys);
    return getUsersFromDatabase($missingIds); // 一次批量查询
});
```

### 基本用法

```php
// 1. 生成批量键
$productIds = [1, 2, 3, 4, 5];
$productKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('product', ['id' => $id]);
}, $productIds);

// 2. 批量获取
$products = $cache->getMultiple($productKeys, function($missingKeys) use ($keyManager) {
    // 解析出需要查询的ID
    $missingIds = [];
    foreach ($missingKeys as $key) {
        $parsed = $keyManager->parse($key);
        $missingIds[] = explode(':', $parsed['business_key'])[1];
    }
    
    // 批量查询数据库
    $dbProducts = getProductsFromDatabase($missingIds);
    
    // 重新组织数据，键为缓存键
    $results = [];
    foreach ($dbProducts as $product) {
        $key = $keyManager->make('product', ['id' => $product['id']]);
        $results[$key] = $product;
    }
    
    return $results;
});
```

### 性能对比

| 场景 | 传统方式 | 批量操作 | 性能提升 |
|------|----------|----------|----------|
| 10个商品 | 10次数据库查询 | 1次批量查询 | 10x |
| 100个用户 | 100次数据库查询 | 1次批量查询 | 100x |
| 混合命中 | 部分查询+部分缓存 | 智能批量处理 | 5-50x |

### 实际应用示例

```php
// 电商商品列表页
class ProductListService
{
    public function getProductList($productIds)
    {
        $productKeys = array_map(function($id) {
            return $this->keyManager->make('product', ['id' => $id]);
        }, $productIds);
        
        return $this->cache->getMultiple($productKeys, function($missingKeys) {
            $missingIds = $this->extractProductIds($missingKeys);
            return $this->productRepository->findByIds($missingIds);
        });
    }
}
```

## 3. 标签管理

### 解决的问题

当数据更新时，需要清除所有相关的缓存项：

```php
// ❌ 手动管理相关缓存
function updateUser($userId, $data) {
    updateUserInDatabase($userId, $data);
    
    // 需要手动清除所有相关缓存
    $cache->forget("user:{$userId}");
    $cache->forget("user_profile:{$userId}");
    $cache->forget("user_settings:{$userId}");
    $cache->forget("user_permissions:{$userId}");
    // ... 可能还有更多
}

// ✅ 标签管理解决方案
function updateUser($userId, $data) {
    updateUserInDatabase($userId, $data);
    
    // 一行代码清除所有相关缓存
    $cache->clearTag("user_{$userId}");
}
```

### 基本用法

#### 设置带标签的缓存

```php
// 设置用户基本信息，标签：users, user_123
$cache->setByTemplateWithTag('user', ['id' => 123], $userData, ['users', 'user_123']);

// 设置用户资料，标签：users, user_123, profiles
$cache->setByTemplateWithTag('user_profile', ['id' => 123], $profileData, 
    ['users', 'user_123', 'profiles']);

// 设置用户权限，标签：users, user_123, permissions
$cache->setByTemplateWithTag('user_permissions', ['id' => 123], $permissionData,
    ['users', 'user_123', 'permissions']);
```

#### 批量清除缓存

```php
// 清除特定用户的所有缓存
$cache->clearTag('user_123');

// 清除所有用户缓存
$cache->clearTag('users');

// 清除所有权限相关缓存
$cache->clearTag('permissions');
```

### 标签设计最佳实践

#### 层次化标签设计

```php
// ✅ 推荐的标签设计
$tags = [
    'users',           // 全局用户标签
    'user_123',        // 特定用户标签
    'profiles',        // 功能模块标签
    'vip_users'        // 业务分组标签
];

// ❌ 避免的设计
$tags = [
    'u',               // 太简短
    'user_profile_123', // 太具体
    'all_data'         // 太宽泛
];
```

#### 标签命名规范

```php
$tagPatterns = [
    // 实体类型
    'users', 'products', 'orders', 'posts',
    
    // 特定实体
    'user_{id}', 'product_{id}', 'order_{id}',
    
    // 功能模块
    'profiles', 'settings', 'permissions', 'stats',
    
    // 业务分组
    'vip_users', 'hot_products', 'featured_posts',
    
    // 时间维度
    'date_{date}', 'month_{month}', 'year_{year}'
];
```

### 实际应用场景

#### 用户信息更新

```php
class UserService
{
    public function updateUser($userId, $data)
    {
        // 1. 更新数据库
        $this->userRepository->update($userId, $data);
        
        // 2. 清除用户相关的所有缓存
        $this->cache->clearTag("user_{$userId}");
        
        // 3. 如果是权限变更，还需要清除权限相关缓存
        if (isset($data['role'])) {
            $this->cache->clearTag('permissions');
        }
    }
}
```

#### 内容发布系统

```php
class PostService
{
    public function publishPost($postId)
    {
        $post = $this->getPost($postId);
        
        // 更新发布状态
        $this->postRepository->publish($postId);
        
        // 清除相关缓存
        $this->cache->clearTag("post_{$postId}");           // 文章本身
        $this->cache->clearTag("user_{$post['user_id']}");  // 作者相关
        $this->cache->clearTag("category_{$post['category_id']}"); // 分类相关
        $this->cache->clearTag('featured_posts');           // 推荐文章
        $this->cache->clearTag('recent_posts');             // 最新文章
    }
}
```

## 4. Key 管理

### 解决的问题

统一的缓存键命名和管理：

```php
// ❌ 混乱的键命名
$cache->set('user_123', $data);
$cache->set('u:456', $data);
$cache->set('user_info_789', $data);
$cache->set('myapp_prod_user_101112', $data);

// ✅ 统一的键管理
$cache->setByTemplate('user', ['id' => 123], $data);
$cache->setByTemplate('user', ['id' => 456], $data);
$cache->setByTemplate('user', ['id' => 789], $data);
$cache->setByTemplate('user', ['id' => 101112], $data);
```

### 键命名规范

```
{app_prefix}:{env_prefix}:{version}:{business_key}
```

**示例：**
- `myapp:prod:v1:user:123` - 生产环境用户数据
- `myapp:dev:v1:product:456` - 开发环境商品数据
- `ecommerce:test:v2:order:ORD001` - 测试环境订单数据

### 基本配置

```php
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // 用户相关
        'user' => 'user:{id}',
        'user_profile' => 'user:profile:{id}',
        'user_settings' => 'user:settings:{id}',
        
        // 商品相关
        'product' => 'product:{id}',
        'product_detail' => 'product:detail:{id}',
        'product_price' => 'product:price:{id}',
        
        // 订单相关
        'order' => 'order:{id}',
        'order_items' => 'order:items:{order_id}',
    ]
]);
```

### 核心功能

#### 键生成

```php
// 基本键生成
$userKey = $keyManager->make('user', ['id' => 123]);
// 结果: myapp:prod:v1:user:123

// 复杂参数键生成
$listKey = $keyManager->make('category_products', [
    'id' => 'electronics',
    'page' => 1
]);
// 结果: myapp:prod:v1:category:products:electronics:page:1
```

#### 键解析

```php
$key = 'myapp:prod:v1:user:settings:789';
$parsed = $keyManager->parse($key);

/*
结果:
[
    'full_key' => 'myapp:prod:v1:user:settings:789',
    'has_prefix' => true,
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'business_key' => 'user:settings:789'
]
*/
```

#### 模式匹配

```php
// 生成模式匹配键（用于批量操作）
$userPattern = $keyManager->pattern('user', ['id' => '*']);
// 结果: myapp:prod:v1:user:*

$categoryPattern = $keyManager->pattern('category_products', [
    'id' => 'electronics',
    'page' => '*'
]);
// 结果: myapp:prod:v1:category:products:electronics:page:*
```

### 环境隔离

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

// 同样的模板，不同的环境前缀
$devKey = $devKeyManager->make('user', ['id' => 123]);   // myapp:dev:v1:user:123
$prodKey = $prodKeyManager->make('user', ['id' => 123]); // myapp:prod:v1:user:123
```

### 版本管理

```php
// 当需要更新缓存结构时，增加版本号
$v1KeyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1'
]);

$v2KeyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v2'  // 新版本
]);

// 新旧版本的缓存不会冲突
$v1Key = $v1KeyManager->make('user', ['id' => 123]); // myapp:prod:v1:user:123
$v2Key = $v2KeyManager->make('user', ['id' => 123]); // myapp:prod:v2:user:123
```

## 功能组合使用

### 完整的业务场景

```php
class EcommerceService
{
    private $cache;
    private $keyManager;
    
    public function __construct($cache, $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }
    
    // 1. 自动回填 + Key管理
    public function getProduct($productId)
    {
        return $this->cache->getByTemplate('product', ['id' => $productId], function() use ($productId) {
            return $this->productRepository->find($productId);
        });
    }
    
    // 2. 批量操作 + Key管理
    public function getProducts($productIds)
    {
        $productKeys = array_map(function($id) {
            return $this->keyManager->make('product', ['id' => $id]);
        }, $productIds);
        
        return $this->cache->getMultiple($productKeys, function($missingKeys) {
            $missingIds = $this->extractProductIds($missingKeys);
            return $this->productRepository->findByIds($missingIds);
        });
    }
    
    // 3. 标签管理 + Key管理
    public function updateProduct($productId, $data)
    {
        // 更新数据库
        $this->productRepository->update($productId, $data);
        
        // 清除相关缓存
        $this->cache->clearTag("product_{$productId}");
        
        // 如果分类发生变化，清除分类缓存
        if (isset($data['category_id'])) {
            $this->cache->clearTag("category_{$data['category_id']}");
        }
    }
    
    // 4. 四大功能综合使用
    public function getProductsWithCache($categoryId, $page = 1)
    {
        // 使用Key管理生成列表键
        return $this->cache->getByTemplate('category_products', [
            'id' => $categoryId,
            'page' => $page
        ], function() use ($categoryId, $page) {
            // 自动回填：从数据库获取数据
            $products = $this->productRepository->getByCategory($categoryId, $page);
            
            // 设置标签：便于后续批量清理
            $this->cache->setByTemplateWithTag('category_products', [
                'id' => $categoryId,
                'page' => $page
            ], $products, ["category_{$categoryId}", 'product_lists']);
            
            return $products;
        });
    }
}
```

## 性能监控

### 缓存统计

```php
$stats = $cache->getStats();

echo "缓存性能统计:\n";
echo "  命中次数: {$stats['hits']}\n";
echo "  未命中次数: {$stats['misses']}\n";
echo "  命中率: {$stats['hit_rate']}%\n";

// 性能分析
if ($stats['hit_rate'] > 80) {
    echo "✅ 缓存效果优秀\n";
} elseif ($stats['hit_rate'] > 60) {
    echo "⚠️  缓存效果良好，可以优化\n";
} else {
    echo "❌ 缓存效果较差，需要检查策略\n";
}
```

### 功能使用建议

| 功能 | 适用场景 | 性能提升 | 复杂度 |
|------|----------|----------|--------|
| 自动回填 | 所有缓存场景 | 10-100x | 低 |
| 批量操作 | 列表页、批量查询 | 10-1000x | 中 |
| 标签管理 | 数据更新频繁 | 维护性提升 | 中 |
| Key管理 | 大型项目 | 可维护性提升 | 低 |

## 下一步

掌握了核心功能后，建议您：

1. 学习 [Key 管理详细指南](key-management.md)
2. 查看 [实战案例](../examples/) 了解实际应用
3. 阅读 [高级特性](advanced-features.md) 了解更多功能

---

**现在您已经掌握了 CacheKV 的四大核心功能！** 🚀
