# 快速开始

5分钟快速上手 CacheKV，体验简洁高效的缓存操作。

## 📦 安装

```bash
composer require asfop1/cache-kv
```

## ⚡ 基础配置

### 1. 配置 Redis 连接

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 配置Redis连接
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});
```

### 2. 开始使用

```php
// 单个数据获取 - 一行代码搞定缓存逻辑
$user = kv_get('user.profile', ['id' => 123], function() {
    // 只在缓存未命中时执行
    return getUserFromDatabase(123);
});

echo "用户名: " . $user['name'];
```

## 🚀 核心功能演示

### 单个缓存操作

```php
// 获取用户资料
$user = kv_get('user.profile', ['id' => 123], function() {
    return [
        'id' => 123,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
});

// 获取用户设置
$settings = kv_get('user.settings', ['id' => 123], function() {
    return [
        'theme' => 'dark',
        'language' => 'zh-CN',
        'notifications' => true
    ];
});
```

### 批量缓存操作

```php
// 批量获取多个用户资料
$users = kv_get_multi('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
], function($missedKeys) {
    // 只查询缓存中没有的用户
    $results = [];
    foreach ($missedKeys as $cacheKey) {
        $params = $cacheKey->getParams();
        $userId = $params['id'];
        
        // 从数据库获取用户数据
        $userData = getUserFromDatabase($userId);
        $results[(string)$cacheKey] = $userData;
    }
    return $results;
});

// 使用结果
foreach ($users as $keyString => $userData) {
    echo "用户: " . $userData['name'] . "\n";
}
```

### 键管理

```php
// 生成缓存键字符串
$key = kv_key('user.profile', ['id' => 123]);
echo $key; // 输出: app:user:v1:123

// 批量生成键
$keys = kv_keys('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
]);
// 输出: ["app:user:v1:1", "app:user:v1:2", "app:user:v1:3"]
```

### 缓存删除

```php
// 删除特定用户的缓存
$deleted = kv_delete_prefix('user.profile', ['id' => 123]);
echo "删除了 {$deleted} 个缓存项";

// 删除所有用户资料缓存
$deleted = kv_delete_prefix('user.profile');

// 删除整个用户组的缓存
$deleted = kv_delete_prefix('user');
```

## 📊 性能监控

```php
// 获取缓存统计信息
$stats = kv_stats();
echo "命中率: " . $stats['hit_rate'] . "\n";
echo "总请求: " . $stats['total_requests'] . "\n";

// 获取热点键
$hotKeys = kv_hot_keys(5);
foreach ($hotKeys as $key => $count) {
    echo "热点键: {$key} (访问 {$count} 次)\n";
}

// 清空统计数据
kv_clear_stats();
```

## 🎯 实际应用示例

### 用户系统缓存

```php
class UserService 
{
    public function getUser($userId) 
    {
        return kv_get('user.profile', ['id' => $userId], function() use ($userId) {
            // 从数据库查询用户
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        });
    }
    
    public function getUsers($userIds) 
    {
        $paramsList = array_map(function($id) {
            return ['id' => $id];
        }, $userIds);
        
        return kv_get_multi('user.profile', $paramsList, function($missedKeys) {
            $missedIds = [];
            foreach ($missedKeys as $key) {
                $missedIds[] = $key->getParams()['id'];
            }
            
            // 批量查询数据库
            $placeholders = str_repeat('?,', count($missedIds) - 1) . '?';
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id IN ({$placeholders})");
            $stmt->execute($missedIds);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 组织返回数据
            $results = [];
            foreach ($missedKeys as $key) {
                $userId = $key->getParams()['id'];
                foreach ($users as $user) {
                    if ($user['id'] == $userId) {
                        $results[(string)$key] = $user;
                        break;
                    }
                }
            }
            
            return $results;
        });
    }
    
    public function updateUser($userId, $userData) 
    {
        // 更新数据库
        $this->updateUserInDatabase($userId, $userData);
        
        // 清理相关缓存
        kv_delete_prefix('user.profile', ['id' => $userId]);
        kv_delete_prefix('user.settings', ['id' => $userId]);
    }
}
```

### 商品系统缓存

```php
class ProductService 
{
    public function getProduct($productId) 
    {
        return kv_get('product.info', ['id' => $productId], function() use ($productId) {
            return $this->fetchProductFromDatabase($productId);
        }, 1800); // 30分钟缓存
    }
    
    public function getProductsByCategory($categoryId) 
    {
        return kv_get('product.category', ['category_id' => $categoryId], function() use ($categoryId) {
            return $this->fetchProductsByCategoryFromDatabase($categoryId);
        }, 600); // 10分钟缓存
    }
}
```

## 🔧 配置优化

### 自定义配置文件

创建 `config/cache_kv.php`：

```php
<?php
return [
    'cache' => [
        'ttl' => 3600,                    // 默认1小时
        'enable_stats' => true,           // 启用统计
        'hot_key_auto_renewal' => true,   // 启用热点键自动续期
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',          // 应用前缀
        'groups' => [
            'user' => [
                'prefix' => 'user',
                'version' => 'v2',        // 版本升级
                'cache' => [
                    'ttl' => 7200,        // 用户数据缓存2小时
                ],
                'keys' => [
                    'profile' => ['template' => '{id}'],
                    'settings' => ['template' => '{id}'],
                ]
            ]
        ]
    ]
];
```

使用自定义配置：

```php
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
}, 'config/cache_kv.php');
```

## 🎉 完成！

现在你已经掌握了 CacheKV 的基本用法：

- ✅ 单个和批量缓存操作
- ✅ 自动回填机制
- ✅ 缓存删除和管理
- ✅ 性能监控
- ✅ 实际应用场景

## 📚 下一步

- 查看 [完整文档](README.md) 了解高级功能
- 阅读 [配置参考](CONFIG.md) 优化性能
- 学习 [API 参考](API.md) 掌握所有接口
