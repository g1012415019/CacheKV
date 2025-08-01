# CacheKV 核心功能详解

## 概述

CacheKV 的核心价值在于简化"若无则从数据源获取并回填缓存"这一常见模式。本文档详细介绍三大核心功能的实现原理和使用方法。

## 1. 自动回填缓存（核心功能）

### 功能原理

传统的缓存使用需要开发者手动处理以下逻辑：
1. 检查缓存是否存在
2. 如果不存在，从数据源获取数据
3. 将数据写入缓存
4. 返回数据

CacheKV 将这个过程封装在一个方法中，通过回调函数实现自动回填。

### 实现机制

#### 单条数据获取

```php
public function get($key, $callback = null, $ttl = null)
{
    $value = $this->driver->get($key);

    // 缓存命中：直接返回并更新过期时间（滑动过期）
    if ($value !== null) {
        $this->driver->touch($key, $this->defaultTtl);
        return $value;
    }

    // 缓存未命中：执行回调并回填缓存
    if ($callback !== null) {
        $fetchedValue = call_user_func($callback);
        // 即使是 null 也会被缓存，防止缓存穿透
        $this->set($key, $fetchedValue, $ttl);
        return $fetchedValue;
    }

    return null;
}
```

#### 使用示例

```php
// 用户信息缓存
$user = $cache->get('user:123', function() {
    // 只在缓存未命中时执行
    return $userService->findById(123);
});

// API 数据缓存
$weather = $cache->get('weather:beijing', function() {
    return $weatherAPI->getCurrentWeather('beijing');
}, 1800); // 30分钟过期
```

#### 批量数据获取

```php
public function getMultiple($keys, $callback, $ttl = null)
{
    // 1. 批量获取现有缓存
    $cachedValues = $this->driver->getMultiple($keys);
    
    $missingKeys = [];
    $results = [];

    // 2. 分离命中和未命中的键
    foreach ($keys as $originalKey) {
        if (array_key_exists($originalKey, $cachedValues)) {
            $results[$originalKey] = $cachedValues[$originalKey];
        } else {
            $missingKeys[] = $originalKey;
        }
    }

    // 3. 批量获取缺失数据并回填
    if (!empty($missingKeys)) {
        $fetchedValues = call_user_func($callback, $missingKeys);
        if (!empty($fetchedValues)) {
            $this->driver->setMultiple($fetchedValues, $ttl ?? $this->defaultTtl);
        }
        $results = array_merge($results, $fetchedValues);
    }

    return $results;
}
```

#### 批量使用示例

```php
$userIds = [1, 2, 3, 4, 5];

$users = $cache->getMultiple($userIds, function($missingIds) {
    // 只查询缓存中不存在的用户
    return $userService->findByIds($missingIds);
});

// 结果：所有用户数据，部分来自缓存，部分来自数据库
```

### 优势分析

1. **代码简化**：从 6-8 行代码减少到 1 行
2. **防止穿透**：自动缓存 null 值
3. **性能优化**：批量操作减少数据库查询
4. **滑动过期**：访问时自动延长过期时间

## 2. 基于标签的缓存失效管理

### 功能原理

在复杂应用中，经常需要批量清除相关的缓存项。传统方式需要维护缓存键的列表，而标签系统提供了更优雅的解决方案。

### 实现机制

#### 标签关联存储

```php
public function setWithTag($key, $value, $tags, $ttl = null)
{
    // 1. 先设置缓存数据
    $result = $this->driver->set($key, $value, $ttl ?? $this->defaultTtl);
    
    // 2. 建立标签关联
    if ($result) {
        $this->driver->tag($key, (array) $tags);
    }
    
    return $result;
}
```

#### 标签清除机制

```php
public function clearTag($tag)
{
    return $this->driver->clearTag($tag);
}
```

#### Redis 驱动中的标签实现

```php
public function tag($key, array $tags)
{
    $pipeline = $this->redis->pipeline();

    foreach ($tags as $tag) {
        // 标签 -> 键的映射
        $pipeline->sadd('tag_keys:' . $tag, $key);
        // 键 -> 标签的映射（用于删除时清理）
        $pipeline->sadd('tags:' . $key, $tag);
    }
    
    $pipeline->exec();
    return true;
}

public function clearTag($tag)
{
    // 1. 获取标签下的所有键
    $keys = $this->redis->smembers('tag_keys:' . $tag);
    
    if (empty($keys)) {
        return false;
    }

    // 2. 批量删除缓存项和标签关联
    $pipeline = $this->redis->pipeline();
    foreach ($keys as $key) {
        $pipeline->del($key);                    // 删除缓存数据
        $pipeline->srem('tags:' . $key, $tag);  // 清理键的标签引用
    }
    $pipeline->del('tag_keys:' . $tag);         // 删除标签键列表
    $pipeline->exec();

    return true;
}
```

### 使用示例

#### 用户相关缓存管理

```php
$userId = 123;

// 设置用户相关的各种缓存，都打上用户标签
$cache->setWithTag("user:profile:{$userId}", $profile, ['users', "user_{$userId}"]);
$cache->setWithTag("user:settings:{$userId}", $settings, ['users', "user_{$userId}"]);
$cache->setWithTag("user:permissions:{$userId}", $permissions, ['users', "user_{$userId}"]);
$cache->setWithTag("user:posts:{$userId}", $posts, ['posts', "user_{$userId}"]);

// 用户信息更新时，一次性清除所有相关缓存
$cache->clearTag("user_{$userId}");

// 或者清除所有用户缓存
$cache->clearTag('users');
```

#### 内容管理系统示例

```php
// 文章缓存
$cache->setWithTag("post:{$postId}", $post, ['posts', "category_{$categoryId}", "author_{$authorId}"]);

// 分类页面缓存
$cache->setWithTag("category:list:{$categoryId}", $posts, ["category_{$categoryId}"]);

// 作者页面缓存  
$cache->setWithTag("author:posts:{$authorId}", $posts, ["author_{$authorId}"]);

// 当分类下有新文章时，清除分类相关缓存
$cache->clearTag("category_{$categoryId}");

// 当作者发布新文章时，清除作者相关缓存
$cache->clearTag("author_{$authorId}");
```

### 标签设计最佳实践

1. **层次化标签**：使用不同粒度的标签
   ```php
   ['global', 'users', 'user_123', 'user_profile']
   ```

2. **功能性标签**：按功能模块分组
   ```php
   ['posts', 'comments', 'categories', 'tags']
   ```

3. **时间性标签**：按时间维度分组
   ```php
   ['daily_stats', 'monthly_reports', 'yearly_summary']
   ```

## 3. 性能统计功能

### 功能原理

性能统计帮助开发者监控缓存效果，优化缓存策略。CacheKV 提供基础的命中率统计。

### 实现机制

#### 统计数据收集

```php
// 在驱动中维护统计计数器
protected $hits = 0;
protected $misses = 0;

public function get($key)
{
    $value = $this->redis->get($key);

    if ($value === false) {
        $this->misses++;  // 记录未命中
        return null;
    }

    $this->hits++;        // 记录命中
    return unserialize($value);
}
```

#### 统计信息获取

```php
public function getStats()
{
    $total = $this->hits + $this->misses;
    $hitRate = $total > 0 ? ($this->hits / $total) * 100 : 0;

    return [
        'hits' => $this->hits,
        'misses' => $this->misses,
        'hit_rate' => round($hitRate, 2)
    ];
}
```

### 使用示例

#### 基础统计监控

```php
// 执行一些缓存操作
$cache->get('user:1');
$cache->get('user:2');
$cache->get('user:3', function() { return ['name' => 'New User']; });

// 获取统计信息
$stats = $cache->getStats();
/*
[
    'hits' => 2,
    'misses' => 1,
    'hit_rate' => 66.67
]
*/
```

#### 性能监控和优化

```php
class CacheMonitor 
{
    private $cache;
    
    public function __construct($cache) 
    {
        $this->cache = $cache;
    }
    
    public function checkPerformance() 
    {
        $stats = $this->cache->getStats();
        
        if ($stats['hit_rate'] < 70) {
            // 命中率过低，需要优化
            $this->logWarning("Cache hit rate is low: {$stats['hit_rate']}%");
            $this->suggestOptimizations($stats);
        }
        
        return $stats;
    }
    
    private function suggestOptimizations($stats) 
    {
        $suggestions = [];
        
        if ($stats['hit_rate'] < 50) {
            $suggestions[] = "Consider increasing TTL values";
            $suggestions[] = "Review cache key patterns";
        }
        
        if ($stats['misses'] > $stats['hits']) {
            $suggestions[] = "Consider preloading frequently accessed data";
        }
        
        return $suggestions;
    }
}
```

#### 定期统计报告

```php
class CacheReporter 
{
    public function generateDailyReport($cache) 
    {
        $stats = $cache->getStats();
        
        $report = [
            'date' => date('Y-m-d'),
            'total_requests' => $stats['hits'] + $stats['misses'],
            'cache_hits' => $stats['hits'],
            'cache_misses' => $stats['misses'],
            'hit_rate' => $stats['hit_rate'],
            'performance_grade' => $this->getPerformanceGrade($stats['hit_rate'])
        ];
        
        // 保存报告或发送通知
        $this->saveReport($report);
        
        return $report;
    }
    
    private function getPerformanceGrade($hitRate) 
    {
        if ($hitRate >= 90) return 'A';
        if ($hitRate >= 80) return 'B';
        if ($hitRate >= 70) return 'C';
        if ($hitRate >= 60) return 'D';
        return 'F';
    }
}
```

## 核心功能协同工作

### 完整使用示例

```php
// 1. 初始化缓存
$cache = new CacheKV(new RedisDriver(), 3600);

// 2. 使用自动回填功能获取用户数据
$user = $cache->get("user:{$userId}", function() use ($userId) {
    return $userService->findById($userId);
});

// 3. 设置带标签的相关缓存
$cache->setWithTag("user:posts:{$userId}", $userPosts, ["user_{$userId}", 'posts']);
$cache->setWithTag("user:profile:{$userId}", $userProfile, ["user_{$userId}", 'profiles']);

// 4. 批量获取用户的朋友信息
$friendIds = $user['friend_ids'];
$friends = $cache->getMultiple($friendIds, function($missingIds) {
    return $userService->findByIds($missingIds);
});

// 5. 用户更新时清除相关缓存
$cache->clearTag("user_{$userId}");

// 6. 监控缓存性能
$stats = $cache->getStats();
if ($stats['hit_rate'] < 80) {
    // 记录性能警告
    error_log("Cache performance warning: hit rate = {$stats['hit_rate']}%");
}
```

这三大核心功能相互配合，为开发者提供了完整的缓存解决方案，大大简化了缓存的使用复杂度。
