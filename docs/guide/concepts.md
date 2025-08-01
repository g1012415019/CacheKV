# 基础概念

理解 CacheKV 的核心概念是高效使用的关键。本指南将帮助您掌握这些重要概念。

## 核心理念

### 自动回填缓存

CacheKV 的核心理念是**"若无则从数据源获取并回填缓存"**。这意味着您只需要专注于业务逻辑，缓存管理完全自动化。

```php
// 传统方式：手动管理缓存
if ($cache->has('user:123')) {
    $user = $cache->get('user:123');
} else {
    $user = getUserFromDatabase(123);
    $cache->set('user:123', $user, 3600);
}

// CacheKV 方式：自动管理
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
});
```

## 核心组件

### 1. CacheKV 主类

CacheKV 是核心缓存管理类，提供所有缓存操作的统一接口。

```php
use Asfop\CacheKV\CacheKV;

$cache = new CacheKV($driver, $defaultTtl, $keyManager);
```

**主要职责：**
- 缓存数据的存取
- 自动回填机制
- 批量操作处理
- 统计信息收集

### 2. KeyManager 键管理器

KeyManager 负责统一的缓存键命名和管理。

```php
use Asfop\CacheKV\Cache\KeyManager;

$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod', 
    'version' => 'v1'
]);
```

**主要职责：**
- 标准化键命名
- 环境和版本隔离
- 模板化键生成
- 键解析和验证

### 3. CacheDriver 驱动接口

CacheDriver 定义了底层存储的统一接口，支持多种存储后端。

```php
// Array 驱动（内存存储）
$driver = new ArrayDriver();

// Redis 驱动（持久化存储）
$driver = new RedisDriver();
```

**主要职责：**
- 数据存储和检索
- 过期时间管理
- 标签系统支持

## 键命名规范

### 键结构

CacheKV 使用层次化的键命名结构：

```
{app_prefix}:{env_prefix}:{version}:{business_key}
```

**示例：**
```
myapp:prod:v1:user:123
myapp:dev:v1:product:456
ecommerce:test:v2:order:ORD001
```

### 键组成部分

| 部分 | 作用 | 示例 |
|------|------|------|
| `app_prefix` | 应用标识，区分不同应用 | `myapp`, `ecommerce` |
| `env_prefix` | 环境标识，隔离不同环境 | `dev`, `test`, `prod` |
| `version` | 版本标识，支持数据结构升级 | `v1`, `v2` |
| `business_key` | 业务键，实际的数据标识 | `user:123`, `product:456` |

### 键模板

使用模板简化键的生成：

```php
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        'user' => 'user:{id}',
        'user_profile' => 'user:profile:{id}',
        'product' => 'product:{id}',
        'order' => 'order:{id}',
    ]
]);

// 生成键
$userKey = $keyManager->make('user', ['id' => 123]);
// 结果: myapp:prod:v1:user:123
```

## 缓存操作模式

### 1. 单条数据操作

```php
// 获取单条数据
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 设置单条数据
$cache->setByTemplate('user', ['id' => 123], $userData);

// 检查是否存在
$exists = $cache->hasByTemplate('user', ['id' => 123]);

// 删除单条数据
$cache->forgetByTemplate('user', ['id' => 123]);
```

### 2. 批量数据操作

```php
// 批量获取
$userIds = [1, 2, 3, 4, 5];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    // 只查询缓存中不存在的数据
    return getUsersFromDatabase($missingKeys);
});
```

### 3. 标签管理操作

```php
// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 123], $userData, ['users', 'vip_users']);

// 批量清除标签下的所有缓存
$cache->clearTag('users');
```

## 数据流转过程

### 缓存命中流程

```
1. 调用 getByTemplate()
2. KeyManager 生成标准键名
3. 检查缓存是否存在
4. 缓存命中 → 直接返回数据
5. 更新访问统计
```

### 缓存未命中流程

```
1. 调用 getByTemplate()
2. KeyManager 生成标准键名  
3. 检查缓存是否存在
4. 缓存未命中 → 执行回调函数
5. 获取数据源数据
6. 自动写入缓存
7. 返回数据
8. 更新访问统计
```

### 批量操作流程

```
1. 调用 getMultiple()
2. 批量检查缓存状态
3. 分离命中和未命中的键
4. 对未命中的键执行回调
5. 批量写入新数据到缓存
6. 合并命中和新获取的数据
7. 返回完整结果集
```

## 生命周期管理

### TTL（生存时间）

每个缓存项都有生存时间，到期后自动清除：

```php
// 设置 1 小时过期
$cache->getByTemplate('user', ['id' => 123], $callback, 3600);

// 设置 30 分钟过期
$cache->getByTemplate('session', ['id' => 'sess_123'], $callback, 1800);

// 使用默认过期时间
$cache->getByTemplate('product', ['id' => 456], $callback);
```

### 滑动过期

对于频繁访问的数据，可以启用滑动过期：

```php
// 每次访问都重新计算过期时间
$cache->getByTemplate('hot_data', ['id' => 123], $callback, 3600, true);
```

### 标签失效

通过标签批量管理相关缓存的生命周期：

```php
// 设置标签
$cache->setByTemplateWithTag('user', ['id' => 123], $data, ['users', 'vip_users']);

// 批量清除
$cache->clearTag('users'); // 清除所有用户相关缓存
```

## 性能特性

### 防穿透机制

CacheKV 自动缓存空值，防止缓存穿透：

```php
$user = $cache->getByTemplate('user', ['id' => 999999], function() {
    return getUserFromDatabase(999999); // 返回 null
});

// 第二次查询相同ID，直接从缓存返回 null，不会查询数据库
```

### 统计信息

自动收集缓存使用统计：

```php
$stats = $cache->getStats();
// 返回: ['hits' => 85, 'misses' => 15, 'hit_rate' => 85.0]
```

### 批量优化

智能处理批量操作，避免 N+1 查询问题：

```php
// 自动优化：只查询缓存中不存在的数据
$products = $cache->getMultiple($productKeys, function($missingKeys) {
    return getProductsFromDatabase($missingKeys); // 批量查询
});
```

## 使用模式

### 门面模式

通过静态方法简化调用：

```php
use Asfop\CacheKV\CacheKVFacade;

// 配置门面
CacheKVServiceProvider::register($config);

// 使用门面
$user = CacheKVFacade::getByTemplate('user', ['id' => 123], $callback);
```

### 服务注入模式

在框架中作为服务使用：

```php
// Laravel 示例
class UserController extends Controller
{
    public function show($id, CacheKV $cache)
    {
        $user = $cache->getByTemplate('user', ['id' => $id], function() use ($id) {
            return User::find($id);
        });
        
        return response()->json($user);
    }
}
```

## 最佳实践原则

### 1. 键命名一致性

```php
// ✅ 好的命名
'user' => 'user:{id}',
'user_profile' => 'user:profile:{id}',
'user_settings' => 'user:settings:{id}',

// ❌ 避免的命名  
'u' => 'u:{id}',
'userInfo' => 'user_info:{id}',
'user-profile' => 'user-profile:{id}',
```

### 2. 合理的过期时间

```php
// 根据数据特性设置不同的过期时间
$cache->getByTemplate('user', ['id' => $id], $callback, 3600);      // 用户信息：1小时
$cache->getByTemplate('product', ['id' => $id], $callback, 7200);   // 商品信息：2小时  
$cache->getByTemplate('price', ['id' => $id], $callback, 600);      // 价格信息：10分钟
```

### 3. 标签分组管理

```php
// 按业务维度设置标签
$cache->setByTemplateWithTag('user', ['id' => $id], $data, ['users', 'user_' . $id]);
$cache->setByTemplateWithTag('product', ['id' => $id], $data, ['products', 'category_' . $categoryId]);
```

## 常见概念误区

### ❌ 误区1：手动管理缓存键

```php
// 错误做法
$key = "myapp_prod_user_" . $userId;
$cache->set($key, $data);
```

```php
// 正确做法
$cache->setByTemplate('user', ['id' => $userId], $data);
```

### ❌ 误区2：忽略环境隔离

```php
// 错误做法：所有环境使用相同配置
$keyManager = new KeyManager(['app_prefix' => 'myapp']);
```

```php
// 正确做法：区分不同环境
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => $_ENV['APP_ENV'], // dev/test/prod
    'version' => 'v1'
]);
```

### ❌ 误区3：不使用批量操作

```php
// 错误做法：循环单次查询
foreach ($userIds as $id) {
    $users[] = $cache->getByTemplate('user', ['id' => $id], $callback);
}
```

```php
// 正确做法：使用批量操作
$users = $cache->getMultiple($userKeys, $batchCallback);
```

## 下一步

掌握了基础概念后，建议您：

1. 查看 [第一个示例](first-example.md) 动手实践
2. 学习 [核心功能](core-features.md) 深入了解特性
3. 阅读 [实战案例](../examples/) 了解实际应用

---

**现在您已经理解了 CacheKV 的核心概念！** 🎓
