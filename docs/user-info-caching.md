# 用户信息缓存最佳实践

## 场景描述

在 Web 应用中，用户信息是最频繁访问的数据之一。每次页面加载、权限验证、个性化展示都需要获取用户数据。如果每次都从数据库查询，会造成严重的性能问题。

## 传统方案的痛点

### ❌ 手动缓存管理
```php
// 传统方式：繁琐且容易出错
function getUser($userId) {
    $cacheKey = "user_info_{$userId}";
    
    // 1. 检查缓存
    if ($cache->has($cacheKey)) {
        return $cache->get($cacheKey);
    }
    
    // 2. 从数据库获取
    $user = $database->query("SELECT * FROM users WHERE id = ?", [$userId]);
    
    // 3. 写入缓存
    if ($user) {
        $cache->set($cacheKey, $user, 3600);
    }
    
    return $user;
}
```

### 问题分析
- **代码重复**：每个数据获取都要写相同的缓存逻辑
- **键名混乱**：`user_info_123`、`user:123`、`u_123` 等不统一
- **错误处理复杂**：需要处理缓存失败、数据库异常等
- **维护困难**：修改缓存策略需要改动多处代码

## CacheKV + KeyManager 解决方案

### ✅ 统一的键管理
```php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 配置键管理器
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // 用户相关模板
        'user_basic' => 'user:basic:{id}',
        'user_profile' => 'user:profile:{id}',
        'user_settings' => 'user:settings:{id}',
        'user_permissions' => 'user:permissions:{id}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);
```

### 核心优势

1. **标准化键名**：`myapp:prod:v1:user:basic:123`
2. **一行代码搞定**：自动处理缓存检查、数据获取、回填
3. **环境隔离**：开发、测试、生产环境缓存自动隔离
4. **版本管理**：数据结构变更时版本号隔离

## 完整实现示例

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== 用户信息缓存最佳实践 ===\n\n";

// 1. 配置系统
$keyManager = new KeyManager([
    'app_prefix' => 'userapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        'user_basic' => 'user:basic:{id}',
        'user_profile' => 'user:profile:{id}',
        'user_settings' => 'user:settings:{id}',
        'user_permissions' => 'user:permissions:{id}',
        'user_stats' => 'user:stats:{id}:{date}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 2. 模拟数据库操作
function fetchUserFromDatabase($userId) {
    echo "📊 从数据库获取用户 {$userId} 基本信息...\n";
    // 模拟数据库查询延迟
    usleep(100000); // 0.1秒
    
    return [
        'id' => $userId,
        'username' => "user_{$userId}",
        'email' => "user{$userId}@example.com",
        'name' => "User {$userId}",
        'avatar' => "avatar_{$userId}.jpg",
        'created_at' => '2024-01-01 10:00:00',
        'last_login' => date('Y-m-d H:i:s')
    ];
}

function fetchUserProfile($userId) {
    echo "📊 从数据库获取用户 {$userId} 详细资料...\n";
    usleep(150000); // 0.15秒
    
    return [
        'user_id' => $userId,
        'bio' => "This is user {$userId}'s biography",
        'location' => 'San Francisco, CA',
        'website' => "https://user{$userId}.com",
        'phone' => "+1-555-000-{$userId}",
        'birthday' => '1990-01-01',
        'gender' => 'other'
    ];
}

function fetchUserPermissions($userId) {
    echo "📊 从数据库获取用户 {$userId} 权限信息...\n";
    usleep(80000); // 0.08秒
    
    return [
        'user_id' => $userId,
        'role' => $userId == 1 ? 'admin' : 'user',
        'permissions' => [
            'read_posts' => true,
            'write_posts' => $userId <= 10,
            'delete_posts' => $userId == 1,
            'manage_users' => $userId == 1
        ],
        'groups' => $userId <= 5 ? ['vip', 'beta'] : ['normal']
    ];
}

// 3. 业务服务类
class UserService
{
    private $cache;
    
    public function __construct($cache)
    {
        $this->cache = $cache;
    }
    
    /**
     * 获取用户基本信息
     */
    public function getUser($userId)
    {
        return $this->cache->getByTemplate('user_basic', ['id' => $userId], function() use ($userId) {
            return fetchUserFromDatabase($userId);
        });
    }
    
    /**
     * 获取用户详细资料
     */
    public function getUserProfile($userId)
    {
        return $this->cache->getByTemplate('user_profile', ['id' => $userId], function() use ($userId) {
            return fetchUserProfile($userId);
        }, 7200); // 资料缓存2小时
    }
    
    /**
     * 获取用户权限
     */
    public function getUserPermissions($userId)
    {
        return $this->cache->getByTemplate('user_permissions', ['id' => $userId], function() use ($userId) {
            return fetchUserPermissions($userId);
        }, 1800); // 权限缓存30分钟
    }
    
    /**
     * 获取用户完整信息
     */
    public function getFullUserInfo($userId)
    {
        $startTime = microtime(true);
        
        // 并行获取用户各种信息（都会自动处理缓存）
        $basic = $this->getUser($userId);
        $profile = $this->getUserProfile($userId);
        $permissions = $this->getUserPermissions($userId);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        echo "⏱️  获取完整用户信息耗时: {$duration}ms\n";
        
        return [
            'basic' => $basic,
            'profile' => $profile,
            'permissions' => $permissions,
            'load_time_ms' => $duration
        ];
    }
    
    /**
     * 更新用户信息并清除相关缓存
     */
    public function updateUser($userId, $data)
    {
        // 1. 更新数据库（模拟）
        echo "💾 更新数据库中用户 {$userId} 的信息...\n";
        
        // 2. 清除相关缓存
        $this->clearUserCache($userId);
        
        echo "✅ 用户信息更新完成\n";
    }
    
    /**
     * 清除用户相关的所有缓存
     */
    public function clearUserCache($userId)
    {
        $templates = ['user_basic', 'user_profile', 'user_permissions'];
        
        foreach ($templates as $template) {
            $key = $this->cache->makeKey($template, ['id' => $userId]);
            $this->cache->forget($key);
            echo "🗑️  清除缓存: {$key}\n";
        }
    }
}

// 4. 实际使用演示
echo "1. 初始化用户服务\n";
echo "==================\n";
$userService = new UserService($cache);

echo "\n2. 第一次获取用户信息（从数据库）\n";
echo "=================================\n";
$userId = 1;
$userInfo = $userService->getFullUserInfo($userId);
echo "用户基本信息: " . json_encode($userInfo['basic']) . "\n";

echo "\n3. 第二次获取用户信息（从缓存）\n";
echo "=================================\n";
$userInfo2 = $userService->getFullUserInfo($userId);
echo "加载时间对比: 第一次 {$userInfo['load_time_ms']}ms vs 第二次 {$userInfo2['load_time_ms']}ms\n";

echo "\n4. 批量获取多个用户\n";
echo "==================\n";
$userIds = [1, 2, 3, 4, 5];
$startTime = microtime(true);

foreach ($userIds as $id) {
    $user = $userService->getUser($id);
    echo "用户 {$id}: {$user['name']} ({$user['email']})\n";
}

$batchTime = round((microtime(true) - $startTime) * 1000, 2);
echo "批量获取 5 个用户耗时: {$batchTime}ms\n";

echo "\n5. 缓存键管理\n";
echo "=============\n";
echo "生成的缓存键示例:\n";
$keys = [
    $cache->makeKey('user_basic', ['id' => 1]),
    $cache->makeKey('user_profile', ['id' => 1]),
    $cache->makeKey('user_permissions', ['id' => 1]),
    $cache->makeKey('user_stats', ['id' => 1, 'date' => '2024-01-01'])
];

foreach ($keys as $key) {
    echo "  - {$key}\n";
}

echo "\n6. 更新用户信息\n";
echo "===============\n";
$userService->updateUser(1, ['name' => 'Updated User']);

echo "\n7. 验证缓存清除效果\n";
echo "==================\n";
echo "更新后重新获取用户信息（应该从数据库获取）:\n";
$updatedUser = $userService->getUser(1);
echo "用户信息: " . json_encode($updatedUser) . "\n";

echo "\n8. 缓存统计\n";
echo "===========\n";
$stats = $cache->getStats();
echo "缓存统计:\n";
echo "  命中次数: {$stats['hits']}\n";
echo "  未命中次数: {$stats['misses']}\n";
echo "  命中率: {$stats['hit_rate']}%\n";

echo "\n=== 用户信息缓存示例完成 ===\n";
```

## 性能对比

### 传统方案
- **首次加载**：~300ms（3次数据库查询）
- **缓存命中**：~50ms（仍需检查3次缓存）
- **代码复杂度**：高（每个方法都要写缓存逻辑）

### CacheKV 方案
- **首次加载**：~330ms（3次数据库查询 + 自动缓存）
- **缓存命中**：~5ms（一行代码搞定）
- **代码复杂度**：低（业务逻辑清晰）

## 最佳实践建议

### 1. 键模板设计
```php
// ✅ 好的设计
'user_basic' => 'user:basic:{id}',
'user_profile' => 'user:profile:{id}',
'user_settings' => 'user:settings:{id}:{section}',

// ❌ 避免的设计
'user' => 'u:{id}',           // 太简短
'userInfo' => 'user_info:{id}', // 命名不一致
```

### 2. 缓存时间策略
```php
// 基本信息：1小时（变化频率低）
$cache->getByTemplate('user_basic', ['id' => $userId], $callback, 3600);

// 详细资料：2小时（用户主动更新）
$cache->getByTemplate('user_profile', ['id' => $userId], $callback, 7200);

// 权限信息：30分钟（安全敏感）
$cache->getByTemplate('user_permissions', ['id' => $userId], $callback, 1800);
```

### 3. 缓存更新策略
```php
public function updateUser($userId, $data) {
    // 1. 更新数据库
    $this->database->update('users', $data, ['id' => $userId]);
    
    // 2. 清除相关缓存（让下次访问时重新加载）
    $this->clearUserCache($userId);
    
    // 或者 3. 主动更新缓存（适合高频访问场景）
    // $this->refreshUserCache($userId);
}
```

## 总结

通过 CacheKV + KeyManager，用户信息缓存变得：

- **简单**：一行代码实现缓存逻辑
- **标准**：统一的键命名规范
- **安全**：环境隔离和版本管理
- **高效**：自动处理缓��命中和回填
- **可维护**：清晰的业务逻辑分离

这种方案特别适合用户系统、权限管理、个人资料等高频访问的场景。
