# 核心功能

CacheKV 提供四大核心功能，让缓存管理变得简单高效。

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

// 获取商品信息，自定义过期时间
$product = $cache->getByTemplate('product', ['id' => 456], function() {
    return getProductFromDatabase(456);
}, 1800); // 30分钟过期
```

### 防穿透机制

CacheKV 自动缓存空值，防止缓存穿透：

```php
$user = $cache->getByTemplate('user', ['id' => 999999], function() {
    return getUserFromDatabase(999999); // 返回 null
});

// 即使返回 null，也会被缓存，防止重复查询数据库
```

## 2. 批量操作

### 解决 N+1 查询问题

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

### 智能处理

批量操作自动处理：
- **缓存命中**：直接返回缓存数据
- **缓存未命中**：批量查询数据源
- **自动回填**：将新数据写入缓存

### 性能对比

| 场景 | 传统方式 | 批量操作 | 性能提升 |
|------|----------|----------|----------|
| 10个商品 | 10次数据库查询 | 1次批量查询 | 10x |
| 100个用户 | 100次数据库查询 | 1次批量查询 | 100x |
| 混合命中 | 部分查询+部分缓存 | 智能批量处理 | 5-50x |

## 3. 标签管理

### 解决相关缓存清理问题

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

## 4. 统一键管理

### 解决键命名混乱问题

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

### 环境隔离

```php
// 开发环境
$devKeyManager = new KeyManager(['env_prefix' => 'dev']);
$devKey = $devKeyManager->make('user', ['id' => 123]);   // myapp:dev:v1:user:123

// 生产环境
$prodKeyManager = new KeyManager(['env_prefix' => 'prod']);
$prodKey = $prodKeyManager->make('user', ['id' => 123]); // myapp:prod:v1:user:123
```

### 版本管理

```php
// 数据结构升级时使用新版本
$v1KeyManager = new KeyManager(['version' => 'v1']);
$v2KeyManager = new KeyManager(['version' => 'v2']);

// 新旧版本的缓存不会冲突
$v1Key = $v1KeyManager->make('user', ['id' => 123]); // myapp:prod:v1:user:123
$v2Key = $v2KeyManager->make('user', ['id' => 123]); // myapp:prod:v2:user:123
```

## 功能组合使用

### 完整的业务场景

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
    
    // 1. 自动回填 + Key管理
    public function getUser($userId)
    {
        return $this->cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
            return $this->userRepository->find($userId);
        });
    }
    
    // 2. 批量操作 + Key管理
    public function getUsers($userIds)
    {
        $userKeys = array_map(function($id) {
            return $this->keyManager->make('user', ['id' => $id]);
        }, $userIds);
        
        return $this->cache->getMultiple($userKeys, function($missingKeys) {
            $missingIds = $this->extractUserIds($missingKeys);
            return $this->userRepository->findByIds($missingIds);
        });
    }
    
    // 3. 标签管理 + Key管理
    public function updateUser($userId, $data)
    {
        // 更新数据库
        $this->userRepository->update($userId, $data);
        
        // 清除相关缓存
        $this->cache->clearTag("user_{$userId}");
    }
    
    // 4. 四大功能综合使用
    public function getUserWithProfile($userId)
    {
        // 使用Key管理生成键
        $user = $this->cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
            // 自动回填：从数据库获取数据
            $userData = $this->userRepository->find($userId);
            
            // 设置标签：便于后续批量清理
            $this->cache->setByTemplateWithTag('user', ['id' => $userId], 
                $userData, ['users', "user_{$userId}"]);
            
            return $userData;
        });
        
        return $user;
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

## 最佳实践

### 1. 合理设置 TTL

```php
// 根据数据特性设置不同的过期时间
$cache->getByTemplate('user', ['id' => $id], $callback, 3600);      // 用户信息：1小时
$cache->getByTemplate('product', ['id' => $id], $callback, 7200);   // 商品信息：2小时
$cache->getByTemplate('price', ['id' => $id], $callback, 600);      // 价格信息：10分钟
```

### 2. 使用批量操作

```php
// ✅ 推荐：批量获取
$users = $cache->getMultiple($userKeys, $batchCallback);

// ❌ 避免：循环单次获取
foreach ($userIds as $id) {
    $users[] = $cache->getByTemplate('user', ['id' => $id], $callback);
}
```

### 3. 合理使用标签

```php
// 按业务维度设置标签
$cache->setByTemplateWithTag('user', ['id' => $id], $data, ['users', "user_{$id}"]);
$cache->setByTemplateWithTag('product', ['id' => $id], $data, ['products', "category_{$categoryId}"]);
```

### 4. 监控缓存效果

```php
$stats = $cache->getStats();
if ($stats['hit_rate'] < 70) {
    // 优化缓存策略
    $this->optimizeCacheStrategy();
}
```

---

**通过这四大核心功能，CacheKV 让缓存管理变得简单而高效！** 🚀
