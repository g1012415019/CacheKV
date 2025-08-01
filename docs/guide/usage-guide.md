# CacheKV 使用指南

## 概述

本指南将通过实际示例，详细介绍如何在项目中使用 CacheKV 来简化缓存操作。我们将从基础使用开始，逐步介绍高级功能和最佳实践。

## 快速开始

### 安装

```bash
composer require asfop/cache-kv
```

### 基础设置

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 创建缓存实例（开发环境）
$cache = new CacheKV(new ArrayDriver(), 3600);
```

## 核心功能使用

### 1. 自动回填缓存

这是 CacheKV 的核心功能，让你告别繁琐的缓存检查逻辑。

#### 传统方式 vs CacheKV 方式

```php
// ❌ 传统方式（繁琐）
function getUserTraditional($userId, $cache, $userService) {
    $key = "user:{$userId}";
    
    if ($cache->has($key)) {
        return $cache->get($key);
    } else {
        $user = $userService->findById($userId);
        $cache->set($key, $user, 3600);
        return $user;
    }
}

// ✅ CacheKV 方式（简洁）
function getUser($userId, $cache, $userService) {
    return $cache->get("user:{$userId}", function() use ($userId, $userService) {
        return $userService->findById($userId);
    });
}
```

#### 实际应用示例

```php
class UserController 
{
    private $cache;
    private $userService;
    
    public function __construct($cache, $userService) {
        $this->cache = $cache;
        $this->userService = $userService;
    }
    
    public function getProfile($userId) {
        // 用户基础信息（1小时缓存）
        $user = $this->cache->get("user:{$userId}", function() use ($userId) {
            return $this->userService->findById($userId);
        }, 3600);
        
        // 用户统计信息（10分钟缓存）
        $stats = $this->cache->get("user:stats:{$userId}", function() use ($userId) {
            return $this->userService->getUserStats($userId);
        }, 600);
        
        // 用户权限信息（30分钟缓存）
        $permissions = $this->cache->get("user:permissions:{$userId}", function() use ($userId) {
            return $this->userService->getUserPermissions($userId);
        }, 1800);
        
        return [
            'user' => $user,
            'stats' => $stats,
            'permissions' => $permissions
        ];
    }
}
```

### 2. 批量数据操作

批量操作可以显著提高性能，特别是在需要获取多个相关数据时。

#### 批量用户查询示例

```php
class UserService 
{
    private $cache;
    private $database;
    
    public function getUsersByIds(array $userIds) {
        return $this->cache->getMultiple($userIds, function($missingIds) {
            // 只查询缓存中不存在的用户
            $users = $this->database->select('users')
                ->whereIn('id', $missingIds)
                ->get();
            
            // 转换为键值对格式
            $result = [];
            foreach ($users as $user) {
                $result[$user['id']] = $user;
            }
            
            return $result;
        });
    }
    
    public function getFriendsList($userId) {
        // 先获取好友ID列表
        $friendIds = $this->cache->get("user:friends:{$userId}", function() use ($userId) {
            return $this->database->getFriendIds($userId);
        });
        
        // 批量获取好友信息
        return $this->getUsersByIds($friendIds);
    }
}
```

#### 商品信息批量查询

```php
class ProductService 
{
    private $cache;
    
    public function getProductsForCart(array $productIds) {
        $products = $this->cache->getMultiple($productIds, function($missingIds) {
            // 从数据库批量获取商品信息
            return $this->fetchProductsFromDatabase($missingIds);
        });
        
        // 计算总价
        $totalPrice = 0;
        foreach ($products as $product) {
            $totalPrice += $product['price'];
        }
        
        return [
            'products' => $products,
            'total_price' => $totalPrice
        ];
    }
    
    private function fetchProductsFromDatabase($productIds) {
        $products = $this->database->select('products')
            ->whereIn('id', $productIds)
            ->get();
            
        $result = [];
        foreach ($products as $product) {
            $result[$product['id']] = $product;
        }
        
        return $result;
    }
}
```

### 3. 基于标签的缓存管理

标签系统让你可以轻松管理相关的缓存项。

#### 用户相关缓存管理

```php
class UserCacheManager 
{
    private $cache;
    
    public function cacheUserData($userId, $userData) {
        $userTag = "user_{$userId}";
        
        // 缓存用户基础信息
        $this->cache->setWithTag(
            "user:profile:{$userId}", 
            $userData['profile'], 
            ['users', $userTag, 'profiles']
        );
        
        // 缓存用户设置
        $this->cache->setWithTag(
            "user:settings:{$userId}", 
            $userData['settings'], 
            ['users', $userTag, 'settings']
        );
        
        // 缓存用户权限
        $this->cache->setWithTag(
            "user:permissions:{$userId}", 
            $userData['permissions'], 
            ['users', $userTag, 'permissions']
        );
    }
    
    public function clearUserCache($userId) {
        // 一次性清除用户的所有相关缓存
        $this->cache->clearTag("user_{$userId}");
    }
    
    public function clearAllUserCaches() {
        // 清除所有用户缓存
        $this->cache->clearTag('users');
    }
}
```

#### 内容管理系统示例

```php
class CMSCacheManager 
{
    private $cache;
    
    public function cachePost($post) {
        $postId = $post['id'];
        $categoryId = $post['category_id'];
        $authorId = $post['author_id'];
        
        // 缓存文章详情
        $this->cache->setWithTag(
            "post:{$postId}", 
            $post, 
            ['posts', "category_{$categoryId}", "author_{$authorId}"]
        );
        
        // 缓存文章摘要（用于列表页）
        $summary = $this->createPostSummary($post);
        $this->cache->setWithTag(
            "post:summary:{$postId}", 
            $summary, 
            ['post_summaries', "category_{$categoryId}", "author_{$authorId}"]
        );
    }
    
    public function invalidateCategory($categoryId) {
        // 分类更新时，清除相关缓存
        $this->cache->clearTag("category_{$categoryId}");
    }
    
    public function invalidateAuthor($authorId) {
        // 作者信息更新时，清除相关缓存
        $this->cache->clearTag("author_{$authorId}");
    }
    
    private function createPostSummary($post) {
        return [
            'id' => $post['id'],
            'title' => $post['title'],
            'excerpt' => substr($post['content'], 0, 200),
            'created_at' => $post['created_at']
        ];
    }
}
```

## 驱动配置

### Redis 驱动配置

```php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 配置 Redis 连接
RedisDriver::setRedisFactory(function() {
    $redis = new \Predis\Client([
        'scheme' => 'tcp',
        'host'   => env('REDIS_HOST', '127.0.0.1'),
        'port'   => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
        'password' => env('REDIS_PASSWORD', null),
    ]);
    
    return $redis;
});

// 创建缓存实例
$cache = new CacheKV(new RedisDriver(), 3600);
```

### 使用服务提供者

```php
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\CacheKVFacade;

// 配置
$config = [
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\RedisDriver::class
        ],
        'array' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\ArrayDriver::class
        ]
    ],
    'default_ttl' => 3600
];

// 注册服务
CacheKVServiceProvider::register($config);

// 使用门面
$user = CacheKVFacade::get('user:123', function() {
    return fetchUserFromDatabase(123);
});
```

## 实际应用场景

### 1. 电商系统

```php
class EcommerceCache 
{
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    // 商品详情页缓存
    public function getProductDetail($productId) {
        return $this->cache->get("product:detail:{$productId}", function() use ($productId) {
            $product = $this->getProductFromDB($productId);
            $reviews = $this->getProductReviews($productId);
            $recommendations = $this->getRecommendations($productId);
            
            return [
                'product' => $product,
                'reviews' => $reviews,
                'recommendations' => $recommendations
            ];
        }, 1800); // 30分钟缓存
    }
    
    // 分类商品列表缓存
    public function getCategoryProducts($categoryId, $page = 1) {
        $key = "category:products:{$categoryId}:page:{$page}";
        
        return $this->cache->get($key, function() use ($categoryId, $page) {
            return $this->fetchCategoryProducts($categoryId, $page);
        }, 600); // 10分钟缓存
    }
    
    // 购物车商品信息
    public function getCartProducts($productIds) {
        return $this->cache->getMultiple($productIds, function($missingIds) {
            return $this->fetchProductsForCart($missingIds);
        });
    }
    
    // 商品更新时清除相关缓存
    public function invalidateProduct($productId, $categoryId) {
        $this->cache->clearTag("product_{$productId}");
        $this->cache->clearTag("category_{$categoryId}");
    }
}
```

### 2. 社交媒体系统

```php
class SocialMediaCache 
{
    private $cache;
    
    // 用户动态流
    public function getUserFeed($userId, $page = 1) {
        $key = "user:feed:{$userId}:page:{$page}";
        
        return $this->cache->get($key, function() use ($userId, $page) {
            // 获取用户关注的人
            $followingIds = $this->getFollowingIds($userId);
            
            // 获取动态
            return $this->getFeedPosts($followingIds, $page);
        }, 300); // 5分钟缓存
    }
    
    // 热门内容
    public function getTrendingPosts() {
        return $this->cache->get('trending:posts', function() {
            return $this->calculateTrendingPosts();
        }, 900); // 15分钟缓存
    }
    
    // 用户发布新动态时的缓存更新
    public function onUserPost($userId, $post) {
        // 清除用户自己的动态流缓存
        $this->cache->clearTag("user_feed_{$userId}");
        
        // 清除关注者的动态流缓存
        $followers = $this->getFollowerIds($userId);
        foreach ($followers as $followerId) {
            $this->cache->clearTag("user_feed_{$followerId}");
        }
        
        // 如果是热门内容，清除热门缓存
        if ($this->isPopularPost($post)) {
            $this->cache->forget('trending:posts');
        }
    }
}
```

### 3. API 响应缓存

```php
class APICache 
{
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    // 外部 API 调用缓存
    public function getWeatherData($city) {
        $key = "weather:{$city}";
        
        return $this->cache->get($key, function() use ($city) {
            // 调用外部天气 API
            $response = $this->callWeatherAPI($city);
            
            if (!$response) {
                // API 调用失败时返回默认数据
                return $this->getDefaultWeatherData();
            }
            
            return $response;
        }, 1800); // 30分钟缓存
    }
    
    // 汇率数据缓存
    public function getExchangeRates() {
        return $this->cache->get('exchange:rates', function() {
            try {
                return $this->callExchangeRateAPI();
            } catch (Exception $e) {
                // API 失败时使用缓存的旧数据
                error_log("Exchange rate API failed: " . $e->getMessage());
                return $this->getLastKnownRates();
            }
        }, 3600); // 1小时缓存
    }
    
    // 批量获取多个城市的天气
    public function getMultipleCitiesWeather($cities) {
        return $this->cache->getMultiple($cities, function($missingCities) {
            $results = [];
            foreach ($missingCities as $city) {
                $results[$city] = $this->callWeatherAPI($city);
            }
            return $results;
        });
    }
}
```

## 性能监控和优化

### 缓存性能监控

```php
class CacheMonitor 
{
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function getPerformanceReport() {
        $stats = $this->cache->getStats();
        
        $report = [
            'timestamp' => time(),
            'hits' => $stats['hits'],
            'misses' => $stats['misses'],
            'total_requests' => $stats['hits'] + $stats['misses'],
            'hit_rate' => $stats['hit_rate'],
            'performance_grade' => $this->calculateGrade($stats['hit_rate']),
            'recommendations' => $this->getRecommendations($stats)
        ];
        
        return $report;
    }
    
    private function calculateGrade($hitRate) {
        if ($hitRate >= 90) return 'A';
        if ($hitRate >= 80) return 'B';
        if ($hitRate >= 70) return 'C';
        if ($hitRate >= 60) return 'D';
        return 'F';
    }
    
    private function getRecommendations($stats) {
        $recommendations = [];
        
        if ($stats['hit_rate'] < 70) {
            $recommendations[] = "考虑增加缓存时间 (TTL)";
            $recommendations[] = "检查缓存键的命名规范";
        }
        
        if ($stats['misses'] > $stats['hits']) {
            $recommendations[] = "考虑预加载热点数据";
            $recommendations[] = "优化数据获取逻辑";
        }
        
        return $recommendations;
    }
}
```

### 缓存预热

```php
class CacheWarmer 
{
    private $cache;
    
    public function warmupUserCache($userId) {
        // 预加载用户基础数据
        $this->cache->get("user:{$userId}", function() use ($userId) {
            return $this->userService->findById($userId);
        });
        
        // 预加载用户权限
        $this->cache->get("user:permissions:{$userId}", function() use ($userId) {
            return $this->userService->getUserPermissions($userId);
        });
        
        // 预加载用户设置
        $this->cache->get("user:settings:{$userId}", function() use ($userId) {
            return $this->userService->getUserSettings($userId);
        });
    }
    
    public function warmupPopularContent() {
        // 预加载热门商品
        $popularProducts = $this->getPopularProductIds();
        $this->cache->getMultiple($popularProducts, function($missingIds) {
            return $this->productService->findByIds($missingIds);
        });
        
        // 预加载热门分类
        $popularCategories = $this->getPopularCategoryIds();
        foreach ($popularCategories as $categoryId) {
            $this->cache->get("category:products:{$categoryId}:page:1", function() use ($categoryId) {
                return $this->categoryService->getProducts($categoryId, 1);
            });
        }
    }
}
```

## 错误处理和降级策略

### 缓存失败时的降级处理

```php
class ResilientCacheService 
{
    private $cache;
    private $logger;
    
    public function __construct($cache, $logger) {
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    public function getDataWithFallback($key, $dataFetcher, $fallbackData = null) {
        try {
            return $this->cache->get($key, $dataFetcher);
        } catch (Exception $e) {
            // 记录缓存错误
            $this->logger->error("Cache operation failed", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // 尝试直接获取数据
            try {
                return call_user_func($dataFetcher);
            } catch (Exception $dataError) {
                // 数据获取也失败，使用降级数据
                $this->logger->error("Data fetching failed", [
                    'key' => $key,
                    'error' => $dataError->getMessage()
                ]);
                
                return $fallbackData;
            }
        }
    }
}
```

## 最佳实践总结

### 1. 键命名规范
- 使用冒号分隔的层次结构：`user:profile:123`
- 包含版本信息：`api:v1:user:123`
- 使用有意义的前缀：`session:`, `cache:`, `temp:`

### 2. TTL 设置策略
- 用户会话：30分钟 - 2小时
- 用户资料：1-4小时
- 商品信息：4-24小时
- 配置数据：1-7天
- 静态内容：1周-1个月

### 3. 标签使用规范
- 使用层次化标签：`['global', 'users', 'user_123']`
- 按功能模块分组：`['posts', 'comments', 'categories']`
- 便于批量清理：`['user_123', 'user_posts_123']`

### 4. 性能优化
- 优先使用批量操作
- 合理设置缓存时间
- 监控缓存命中率
- 实施缓存预热策略

### 5. 错误处理
- 总是提供降级方案
- 记录缓存相关错误
- 避免缓存失败影响核心业务

通过遵循这些最佳实践，你可以充分发挥 CacheKV 的优势，构建高性能、可靠的缓存系统。
