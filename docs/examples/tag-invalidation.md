# 基于标签的缓存失效管理

## 场景描述

在复杂的应用系统中，数据之间往往存在关联关系。当某个核心数据发生变化时，需要同时清除所有相关的缓存项。传统的单键删除方式无法有效处理这种场景，而标签系统提供了优雅的解决方案。

## 传统方案的问题

### ❌ 手动管理相关缓存
```php
// 用户信息更新时，需要手动清除所有相关缓存
function updateUser($userId, $data) {
    // 1. 更新数据库
    $database->update('users', $data, ['id' => $userId]);
    
    // 2. 手动清除相关缓存（容易遗漏）
    $cache->forget("user:{$userId}");
    $cache->forget("user_profile:{$userId}");
    $cache->forget("user_settings:{$userId}");
    $cache->forget("user_permissions:{$userId}");
    $cache->forget("user_stats:{$userId}");
    // ... 可能还有更多相关缓存
}
```

### 问题分析
- **维护困难**：新增缓存项时容易忘记更新清除逻辑
- **容易遗漏**：相关缓存分散在不同模块中
- **代码重复**：每个更新操作都要写相似的清除逻辑
- **不够灵活**：无法按业务维度批量管理缓存

## CacheKV + KeyManager + 标签系统

### ✅ 统一的标签管理
```php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 配置键管理器
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1'
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 123], $userData, ['users', 'user_123']);
$cache->setByTemplateWithTag('user_profile', ['id' => 123], $profileData, ['users', 'user_123', 'profiles']);

// 一行代码清除所有相关缓存
$cache->clearTag('user_123'); // 清除用户123的所有相关缓存
```

## 完整实现示例

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== 基于标签的缓存失效管理 ===\n\n";

// 1. 系统配置
$keyManager = new KeyManager([
    'app_prefix' => 'tagapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // 用户相关
        'user' => 'user:{id}',
        'user_profile' => 'user:profile:{id}',
        'user_settings' => 'user:settings:{id}',
        'user_permissions' => 'user:permissions:{id}',
        'user_stats' => 'user:stats:{id}:{date}',
        
        // 内容相关
        'post' => 'post:{id}',
        'post_comments' => 'post:comments:{id}:page:{page}',
        'post_likes' => 'post:likes:{id}',
        
        // 分类相关
        'category' => 'category:{id}',
        'category_posts' => 'category:posts:{id}:page:{page}',
        
        // 统计相关
        'daily_stats' => 'stats:daily:{date}',
        'user_activity' => 'activity:user:{id}:{date}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 2. 标签管理服务类
class TaggedCacheService
{
    private $cache;
    private $keyManager;
    
    public function __construct($cache, $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }
    
    /**
     * 用户数据管理
     */
    public function createUserCache($userId, $userData)
    {
        echo "📝 创建用户 {$userId} 的缓存数据\n";
        
        // 用户基本信息 - 标签：users, user_{id}
        $this->cache->setByTemplateWithTag('user', ['id' => $userId], [
            'id' => $userId,
            'name' => $userData['name'],
            'email' => $userData['email'],
            'created_at' => date('Y-m-d H:i:s')
        ], ['users', "user_{$userId}"]);
        
        // 用户资料 - 标签：users, user_{id}, profiles
        $this->cache->setByTemplateWithTag('user_profile', ['id' => $userId], [
            'user_id' => $userId,
            'bio' => $userData['bio'] ?? '',
            'avatar' => $userData['avatar'] ?? '',
            'location' => $userData['location'] ?? ''
        ], ['users', "user_{$userId}", 'profiles']);
        
        // 用户设置 - 标签：users, user_{id}, settings
        $this->cache->setByTemplateWithTag('user_settings', ['id' => $userId], [
            'user_id' => $userId,
            'theme' => 'light',
            'language' => 'en',
            'notifications' => true
        ], ['users', "user_{$userId}", 'settings']);
        
        // 用户权限 - 标签：users, user_{id}, permissions
        $this->cache->setByTemplateWithTag('user_permissions', ['id' => $userId], [
            'user_id' => $userId,
            'role' => $userData['role'] ?? 'user',
            'permissions' => ['read', 'write']
        ], ['users', "user_{$userId}", 'permissions']);
        
        echo "✅ 用户 {$userId} 缓存创建完成\n";
    }
    
    /**
     * 内容数据管理
     */
    public function createPostCache($postId, $postData)
    {
        echo "📝 创建文章 {$postId} 的缓存数据\n";
        
        $userId = $postData['user_id'];
        $categoryId = $postData['category_id'];
        
        // 文章基本信息 - 标签：posts, post_{id}, user_{user_id}, category_{category_id}
        $this->cache->setByTemplateWithTag('post', ['id' => $postId], [
            'id' => $postId,
            'title' => $postData['title'],
            'content' => $postData['content'],
            'user_id' => $userId,
            'category_id' => $categoryId,
            'created_at' => date('Y-m-d H:i:s')
        ], ['posts', "post_{$postId}", "user_{$userId}", "category_{$categoryId}"]);
        
        // 文章评论 - 标签：posts, post_{id}, comments
        $this->cache->setByTemplateWithTag('post_comments', ['id' => $postId, 'page' => 1], [
            'post_id' => $postId,
            'page' => 1,
            'comments' => [
                ['id' => 1, 'content' => 'Great post!', 'user_id' => 2],
                ['id' => 2, 'content' => 'Thanks for sharing', 'user_id' => 3]
            ],
            'total' => 2
        ], ['posts', "post_{$postId}", 'comments']);
        
        // 文章点赞 - 标签：posts, post_{id}, likes
        $this->cache->setByTemplateWithTag('post_likes', ['id' => $postId], [
            'post_id' => $postId,
            'likes_count' => rand(10, 100),
            'user_liked' => false
        ], ['posts', "post_{$postId}", 'likes']);
        
        echo "✅ 文章 {$postId} 缓存创建完成\n";
    }
    
    /**
     * 分类数据管理
     */
    public function createCategoryCache($categoryId, $categoryData)
    {
        echo "📝 创建分类 {$categoryId} 的缓存数据\n";
        
        // 分类基本信息 - 标签：categories, category_{id}
        $this->cache->setByTemplateWithTag('category', ['id' => $categoryId], [
            'id' => $categoryId,
            'name' => $categoryData['name'],
            'description' => $categoryData['description'],
            'post_count' => rand(50, 500)
        ], ['categories', "category_{$categoryId}"]);
        
        // 分类文章列表 - 标签：categories, category_{id}, posts
        $this->cache->setByTemplateWithTag('category_posts', ['id' => $categoryId, 'page' => 1], [
            'category_id' => $categoryId,
            'page' => 1,
            'posts' => [
                ['id' => 1, 'title' => 'Post 1', 'user_id' => 1],
                ['id' => 2, 'title' => 'Post 2', 'user_id' => 2]
            ],
            'total' => 50
        ], ['categories', "category_{$categoryId}", 'posts']);
        
        echo "✅ 分类 {$categoryId} 缓存创建完成\n";
    }
    
    /**
     * 统计数据管理
     */
    public function createStatsCache($date)
    {
        echo "📝 创建 {$date} 的统计缓存\n";
        
        // 每日统计 - 标签：stats, daily_stats, date_{date}
        $this->cache->setByTemplateWithTag('daily_stats', ['date' => $date], [
            'date' => $date,
            'total_users' => rand(1000, 5000),
            'total_posts' => rand(100, 500),
            'total_comments' => rand(500, 2000),
            'active_users' => rand(200, 1000)
        ], ['stats', 'daily_stats', "date_{$date}"]);
        
        // 用户活动统计 - 标签：stats, user_activity, user_{id}, date_{date}
        for ($userId = 1; $userId <= 3; $userId++) {
            $this->cache->setByTemplateWithTag('user_activity', ['id' => $userId, 'date' => $date], [
                'user_id' => $userId,
                'date' => $date,
                'posts_created' => rand(0, 5),
                'comments_made' => rand(0, 20),
                'likes_given' => rand(0, 50)
            ], ['stats', 'user_activity', "user_{$userId}", "date_{$date}"]);
        }
        
        echo "✅ {$date} 统计缓存创建完成\n";
    }
    
    /**
     * 业务场景：用户更新
     */
    public function updateUser($userId, $newData)
    {
        echo "\n🔄 更新用户 {$userId} 信息\n";
        echo "新数据: " . json_encode($newData) . "\n";
        
        // 1. 更新数据库（模拟）
        echo "💾 更新数据库中的用户信息...\n";
        
        // 2. 清除用户相关的所有缓存
        echo "🗑️  清除用户 {$userId} 的所有相关缓存...\n";
        $this->cache->clearTag("user_{$userId}");
        
        echo "✅ 用户 {$userId} 更新完成\n";
    }
    
    /**
     * 业务场景：文章更新
     */
    public function updatePost($postId, $newData)
    {
        echo "\n🔄 更新文章 {$postId}\n";
        
        // 1. 更新数据库
        echo "💾 更新数据库中的文章信息...\n";
        
        // 2. 清除文章相关缓存
        echo "🗑️  清除文章 {$postId} 的所有相关缓存...\n";
        $this->cache->clearTag("post_{$postId}");
        
        // 3. 如果分类发生变化，还需要清除相关分类缓存
        if (isset($newData['category_id'])) {
            echo "🗑️  清除分类相关缓存...\n";
            $this->cache->clearTag("category_{$newData['category_id']}");
        }
        
        echo "✅ 文章 {$postId} 更新完成\n";
    }
    
    /**
     * 业务场景：分类管理
     */
    public function deleteCategory($categoryId)
    {
        echo "\n🗑️  删除分类 {$categoryId}\n";
        
        // 1. 删除数据库记录
        echo "💾 从数据库删除分类...\n";
        
        // 2. 清除分类相关的所有缓存
        echo "🗑️  清除分类 {$categoryId} 的所有相关缓存...\n";
        $this->cache->clearTag("category_{$categoryId}");
        
        echo "✅ 分类 {$categoryId} 删除完成\n";
    }
    
    /**
     * 业务场景：批量清理
     */
    public function performMaintenance()
    {
        echo "\n🔧 执行系统维护\n";
        
        // 清除所有用户缓存
        echo "🗑️  清除所有用户缓存...\n";
        $this->cache->clearTag('users');
        
        // 清除所有文章缓存
        echo "🗑️  清除所有文章缓存...\n";
        $this->cache->clearTag('posts');
        
        // 清除统计缓存
        echo "🗑️  清除统计缓存...\n";
        $this->cache->clearTag('stats');
        
        echo "✅ 系统维护完成\n";
    }
    
    /**
     * 检查缓存状态
     */
    public function checkCacheStatus()
    {
        echo "\n📊 检查缓存状态\n";
        
        // 检查特定缓存是否存在
        $checks = [
            ['user', ['id' => 1]],
            ['user_profile', ['id' => 1]],
            ['post', ['id' => 1]],
            ['category', ['id' => 1]]
        ];
        
        foreach ($checks as [$template, $params]) {
            $exists = $this->cache->hasByTemplate($template, $params);
            $key = $this->keyManager->make($template, $params);
            echo "  - {$key}: " . ($exists ? '✅ 存在' : '❌ 不存在') . "\n";
        }
    }
}

// 3. 实际使用演示
echo "1. 初始化标签缓存服务\n";
echo "======================\n";
$tagService = new TaggedCacheService($cache, $keyManager);

echo "\n2. 创建用户缓存数据\n";
echo "==================\n";
$tagService->createUserCache(1, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'bio' => 'Software Developer',
    'role' => 'admin'
]);

$tagService->createUserCache(2, [
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
    'bio' => 'Product Manager',
    'role' => 'user'
]);

echo "\n3. 创建内容缓存数据\n";
echo "==================\n";
$tagService->createPostCache(1, [
    'title' => 'Introduction to CacheKV',
    'content' => 'CacheKV is a powerful caching library...',
    'user_id' => 1,
    'category_id' => 1
]);

$tagService->createPostCache(2, [
    'title' => 'Advanced Caching Strategies',
    'content' => 'In this post, we will explore...',
    'user_id' => 2,
    'category_id' => 1
]);

echo "\n4. 创建分类缓存数据\n";
echo "==================\n";
$tagService->createCategoryCache(1, [
    'name' => 'Technology',
    'description' => 'Posts about technology and programming'
]);

echo "\n5. 创建统计缓存数据\n";
echo "==================\n";
$tagService->createStatsCache('2024-01-01');

echo "\n6. 检查初始缓存状态\n";
echo "==================\n";
$tagService->checkCacheStatus();

echo "\n7. 用户更新场景\n";
echo "===============\n";
$tagService->updateUser(1, ['name' => 'John Updated', 'email' => 'john.updated@example.com']);

echo "\n8. 检查用户更新后的缓存状态\n";
echo "==========================\n";
$tagService->checkCacheStatus();

echo "\n9. 文章更新场景\n";
echo "===============\n";
$tagService->updatePost(1, ['title' => 'Updated Title', 'category_id' => 2]);

echo "\n10. 分类删除场景\n";
echo "================\n";
$tagService->deleteCategory(1);

echo "\n11. 检查删除后的缓存状态\n";
echo "========================\n";
$tagService->checkCacheStatus();

echo "\n12. 系统维护场景\n";
echo "================\n";
// 重新创建一些缓存用于演示
$tagService->createUserCache(3, ['name' => 'Test User', 'email' => 'test@example.com']);
$tagService->createPostCache(3, ['title' => 'Test Post', 'content' => 'Test', 'user_id' => 3, 'category_id' => 2]);

echo "\n维护前缓存状态:\n";
$tagService->checkCacheStatus();

$tagService->performMaintenance();

echo "\n维护后缓存状态:\n";
$tagService->checkCacheStatus();

echo "\n13. 缓存统计\n";
echo "============\n";
$stats = $cache->getStats();
echo "标签缓存统计:\n";
echo "  命中次数: {$stats['hits']}\n";
echo "  未命中次数: {$stats['misses']}\n";
echo "  命中率: {$stats['hit_rate']}%\n";

echo "\n=== 基于标签的缓存失效管理示例完成 ===\n";
```

## 标签设计最佳实践

### 1. 层次化标签设计
```php
// ✅ 好的标签设计
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

### 2. 标签命名规范
```php
// 推荐的标签命名规范
$tagPatterns = [
    // 实体类型
    'users', 'posts', 'categories', 'orders',
    
    // 特定实体
    'user_{id}', 'post_{id}', 'category_{id}',
    
    // 功能模块
    'profiles', 'settings', 'permissions', 'stats',
    
    // 业务分组
    'vip_users', 'featured_posts', 'hot_categories',
    
    // 时间维度
    'date_{date}', 'month_{month}', 'year_{year}'
];
```

### 3. 标签使用策略
```php
class SmartTagging
{
    public function setUserCache($userId, $data, $userType = 'normal')
    {
        $baseTags = ['users', "user_{$userId}"];
        
        // 根据用户类型添加额外标签
        if ($userType === 'vip') {
            $baseTags[] = 'vip_users';
        }
        
        // 根据数据类型添加功能标签
        if (isset($data['profile'])) {
            $baseTags[] = 'profiles';
        }
        
        $this->cache->setByTemplateWithTag('user', ['id' => $userId], $data, $baseTags);
    }
}
```

## 高级应用场景

### 1. 权限变更的级联清理
```php
public function updateUserRole($userId, $newRole)
{
    // 更新数据库
    $this->database->updateUserRole($userId, $newRole);
    
    // 清除用户相关缓存
    $this->cache->clearTag("user_{$userId}");
    
    // 清除权限相关缓存
    $this->cache->clearTag('permissions');
    
    // 如果是管理员权限变更，清除管理相关缓存
    if ($newRole === 'admin' || $this->isAdmin($userId)) {
        $this->cache->clearTag('admin_data');
    }
}
```

### 2. 内容发布的多维度清理
```php
public function publishPost($postId)
{
    $post = $this->getPost($postId);
    
    // 更新发布状态
    $this->database->publishPost($postId);
    
    // 清除相关缓存
    $this->cache->clearTag("post_{$postId}");           // 文章本身
    $this->cache->clearTag("user_{$post['user_id']}");  // 作者相关
    $this->cache->clearTag("category_{$post['category_id']}"); // 分类相关
    $this->cache->clearTag('featured_posts');           // 推荐文章
    $this->cache->clearTag('recent_posts');             // 最新文章
}
```

### 3. 定时任务的批量清理
```php
public function dailyMaintenance()
{
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // 清除昨天的统计缓存
    $this->cache->clearTag("date_{$yesterday}");
    
    // 清除过期的临时缓存
    $this->cache->clearTag('temp_data');
    
    // 清除搜索缓存（每日更新）
    $this->cache->clearTag('search_results');
}
```

## 性能监控

### 1. 标签使用统计
```php
public function getTagUsageStats()
{
    return [
        'users' => $this->countCachesByTag('users'),
        'posts' => $this->countCachesByTag('posts'),
        'categories' => $this->countCachesByTag('categories'),
        'stats' => $this->countCachesByTag('stats')
    ];
}
```

### 2. 清理效果监控
```php
public function monitorTagClearance($tag)
{
    $beforeCount = $this->countCachesByTag($tag);
    $this->cache->clearTag($tag);
    $afterCount = $this->countCachesByTag($tag);
    
    $this->logTagClearance($tag, $beforeCount, $afterCount);
}
```

## 总结

基于标签的缓存失效管理提供了：

- **关联管理**：轻松管理相关缓存的生命周期
- **批量操作**：一次清理多个相关缓存项
- **业务对齐**：标签设计与业务逻辑保持一致
- **维护简化**：减少手动管理缓存的复杂性
- **扩展性强**：支持复杂的多维度缓存管理

这种方案特别适合内容管理系统、电商平台、社交网络等具有复杂数据关联关系的应用。
