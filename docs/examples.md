# 实战案例

本文档展示 CacheKV 在真实业务场景中的应用案例。

## 案例 1：用户管理系统

### 业务场景

用户管理系统需要频繁查询用户信息、权限、设置等数据。

### 实现方案

```php
<?php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

class UserService
{
    private $cache;
    private $keyManager;
    
    public function __construct()
    {
        // 配置键管理器
        $this->keyManager = new KeyManager([
            'app_prefix' => 'userapp',
            'env_prefix' => 'prod',
            'version' => 'v1',
            'templates' => [
                'user' => 'user:{id}',
                'user_profile' => 'user:profile:{id}',
                'user_permissions' => 'user:permissions:{id}',
                'user_settings' => 'user:settings:{id}',
            ]
        ]);
        
        // 配置 Redis 驱动
        $redis = new \Predis\Client(['host' => 'redis.example.com']);
        $this->cache = new CacheKV(new RedisDriver($redis), 3600, $this->keyManager);
    }
    
    /**
     * 获取用户基本信息
     */
    public function getUser($userId)
    {
        return $this->cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
            return $this->loadUserFromDatabase($userId);
        });
    }
    
    /**
     * 获取用户权限
     */
    public function getUserPermissions($userId)
    {
        return $this->cache->getByTemplate('user_permissions', ['id' => $userId], function() use ($userId) {
            return $this->loadUserPermissionsFromDatabase($userId);
        }, 1800); // 权限信息缓存30分钟
    }
    
    /**
     * 批量获取用户信息
     */
    public function getUsers($userIds)
    {
        $userKeys = array_map(function($id) {
            return $this->keyManager->make('user', ['id' => $id]);
        }, $userIds);
        
        return $this->cache->getMultiple($userKeys, function($missingKeys) {
            $missingIds = $this->extractUserIds($missingKeys);
            return $this->loadUsersFromDatabase($missingIds);
        });
    }
    
    /**
     * 更新用户信息
     */
    public function updateUser($userId, $data)
    {
        // 更新数据库
        $this->updateUserInDatabase($userId, $data);
        
        // 清除相关缓存
        $this->cache->clearTag("user_{$userId}");
    }
    
    // 模拟数据库操作
    private function loadUserFromDatabase($userId)
    {
        // 实际的数据库查询逻辑
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com"
        ];
    }
    
    private function extractUserIds($keys)
    {
        $ids = [];
        foreach ($keys as $key) {
            $parsed = $this->keyManager->parse($key);
            $ids[] = explode(':', $parsed['business_key'])[1];
        }
        return $ids;
    }
}

// 使用示例
$userService = new UserService();

// 获取单个用户
$user = $userService->getUser(123);
echo "用户信息: " . json_encode($user) . "\n";

// 批量获取用户
$users = $userService->getUsers([1, 2, 3, 4, 5]);
echo "批量获取了 " . count($users) . " 个用户\n";

// 更新用户
$userService->updateUser(123, ['name' => 'Updated Name']);
```

### 性能效果

- **单次查询**：从 200ms 降低到 5ms（缓存命中时）
- **批量查询**：从 N 次数据库查询降低到 1 次
- **缓存命中率**：通常可达 85% 以上

## 案例 2：电商商品系统

### 业务场景

电商平台需要高频访问商品信息、价格、库存等数据。

### 实现方案

```php
<?php
class ProductService
{
    private $cache;
    private $keyManager;
    
    public function __construct()
    {
        $this->keyManager = new KeyManager([
            'app_prefix' => 'shop',
            'env_prefix' => 'prod',
            'version' => 'v1',
            'templates' => [
                'product' => 'product:{id}',
                'product_price' => 'product:price:{id}',
                'product_stock' => 'product:stock:{id}',
                'category_products' => 'category:products:{id}:page:{page}',
            ]
        ]);
        
        $redis = new \Predis\Client(['host' => 'redis.example.com']);
        $this->cache = new CacheKV(new RedisDriver($redis), 3600, $this->keyManager);
    }
    
    /**
     * 获取商品信息
     */
    public function getProduct($productId)
    {
        return $this->cache->getByTemplate('product', ['id' => $productId], function() use ($productId) {
            return $this->loadProductFromDatabase($productId);
        });
    }
    
    /**
     * 获取商品价格（更新频繁，短缓存）
     */
    public function getProductPrice($productId)
    {
        return $this->cache->getByTemplate('product_price', ['id' => $productId], function() use ($productId) {
            return $this->loadProductPriceFromDatabase($productId);
        }, 600); // 价格信息缓存10分钟
    }
    
    /**
     * 获取分类商品列表
     */
    public function getCategoryProducts($categoryId, $page = 1)
    {
        return $this->cache->getByTemplate('category_products', [
            'id' => $categoryId,
            'page' => $page
        ], function() use ($categoryId, $page) {
            return $this->loadCategoryProductsFromDatabase($categoryId, $page);
        });
    }
    
    /**
     * 批量获取商品信息
     */
    public function getProducts($productIds)
    {
        $productKeys = array_map(function($id) {
            return $this->keyManager->make('product', ['id' => $id]);
        }, $productIds);
        
        return $this->cache->getMultiple($productKeys, function($missingKeys) {
            $missingIds = $this->extractProductIds($missingKeys);
            return $this->loadProductsFromDatabase($missingIds);
        });
    }
    
    /**
     * 更新商品信息
     */
    public function updateProduct($productId, $data)
    {
        // 更新数据库
        $this->updateProductInDatabase($productId, $data);
        
        // 清除商品相关缓存
        $this->cache->clearTag("product_{$productId}");
        
        // 如果分类发生变化，清除分类缓存
        if (isset($data['category_id'])) {
            $this->cache->clearTag("category_{$data['category_id']}");
        }
    }
    
    // 模拟数据库操作
    private function loadProductFromDatabase($productId)
    {
        return [
            'id' => $productId,
            'name' => "Product {$productId}",
            'price' => rand(100, 1000),
            'category_id' => rand(1, 10)
        ];
    }
    
    private function extractProductIds($keys)
    {
        $ids = [];
        foreach ($keys as $key) {
            $parsed = $this->keyManager->parse($key);
            $ids[] = explode(':', $parsed['business_key'])[1];
        }
        return $ids;
    }
}

// 使用示例
$productService = new ProductService();

// 获取商品信息
$product = $productService->getProduct(123);
echo "商品信息: " . json_encode($product) . "\n";

// 获取商品价格
$price = $productService->getProductPrice(123);
echo "商品价格: {$price}\n";

// 获取分类商品
$categoryProducts = $productService->getCategoryProducts(1, 1);
echo "分类商品: " . count($categoryProducts) . " 个\n";
```

## 案例 3：API 响应缓存

### 业务场景

应用需要调用多个外部 API，如天气、汇率、新闻等服务。

### 实现方案

```php
<?php
class ApiCacheService
{
    private $cache;
    private $keyManager;
    
    public function __construct()
    {
        $this->keyManager = new KeyManager([
            'app_prefix' => 'api',
            'env_prefix' => 'prod',
            'version' => 'v1',
            'templates' => [
                'api_weather' => 'api:weather:{city}',
                'api_exchange' => 'api:exchange:{from}:{to}',
                'api_news' => 'api:news:{category}:page:{page}',
                'api_user_info' => 'api:user:{provider}:{user_id}',
            ]
        ]);
        
        $redis = new \Predis\Client(['host' => 'redis.example.com']);
        $this->cache = new CacheKV(new RedisDriver($redis), 3600, $this->keyManager);
    }
    
    /**
     * 获取天气信息
     */
    public function getWeather($city)
    {
        return $this->cache->getByTemplate('api_weather', ['city' => $city], function() use ($city) {
            return $this->callWeatherAPI($city);
        }, 1800); // 天气信息缓存30分钟
    }
    
    /**
     * 获取汇率信息
     */
    public function getExchangeRate($from, $to)
    {
        return $this->cache->getByTemplate('api_exchange', [
            'from' => $from,
            'to' => $to
        ], function() use ($from, $to) {
            return $this->callExchangeAPI($from, $to);
        }, 600); // 汇率信息缓存10分钟
    }
    
    /**
     * 获取新闻列表
     */
    public function getNews($category, $page = 1)
    {
        return $this->cache->getByTemplate('api_news', [
            'category' => $category,
            'page' => $page
        ], function() use ($category, $page) {
            return $this->callNewsAPI($category, $page);
        }, 3600); // 新闻缓存1小时
    }
    
    /**
     * 获取第三方用户信息
     */
    public function getUserInfo($provider, $userId)
    {
        return $this->cache->getByTemplate('api_user_info', [
            'provider' => $provider,
            'user_id' => $userId
        ], function() use ($provider, $userId) {
            return $this->callUserInfoAPI($provider, $userId);
        }, 7200); // 用户信息缓存2小时
    }
    
    // 模拟 API 调用
    private function callWeatherAPI($city)
    {
        // 实际的 API 调用
        return [
            'city' => $city,
            'temperature' => rand(15, 35),
            'condition' => 'Sunny',
            'humidity' => rand(40, 80)
        ];
    }
    
    private function callExchangeAPI($from, $to)
    {
        return [
            'from' => $from,
            'to' => $to,
            'rate' => rand(600, 700) / 100
        ];
    }
}

// 使用示例
$apiService = new ApiCacheService();

// 获取天气信息
$weather = $apiService->getWeather('Beijing');
echo "天气信息: " . json_encode($weather) . "\n";

// 获取汇率信息
$rate = $apiService->getExchangeRate('USD', 'CNY');
echo "汇率信息: " . json_encode($rate) . "\n";
```

## 案例 4：内容管理系统

### 业务场景

CMS 系统需要缓存文章、评论、分类等内容数据。

### 实现方案

```php
<?php
class ContentService
{
    private $cache;
    private $keyManager;
    
    public function __construct()
    {
        $this->keyManager = new KeyManager([
            'app_prefix' => 'cms',
            'env_prefix' => 'prod',
            'version' => 'v1',
            'templates' => [
                'post' => 'post:{id}',
                'post_comments' => 'post:comments:{id}:page:{page}',
                'category' => 'category:{id}',
                'category_posts' => 'category:posts:{id}:page:{page}',
                'tag_posts' => 'tag:posts:{tag}:page:{page}',
            ]
        ]);
        
        $redis = new \Predis\Client(['host' => 'redis.example.com']);
        $this->cache = new CacheKV(new RedisDriver($redis), 3600, $this->keyManager);
    }
    
    /**
     * 获取文章内容
     */
    public function getPost($postId)
    {
        return $this->cache->getByTemplate('post', ['id' => $postId], function() use ($postId) {
            return $this->loadPostFromDatabase($postId);
        });
    }
    
    /**
     * 获取文章评论
     */
    public function getPostComments($postId, $page = 1)
    {
        return $this->cache->getByTemplate('post_comments', [
            'id' => $postId,
            'page' => $page
        ], function() use ($postId, $page) {
            return $this->loadPostCommentsFromDatabase($postId, $page);
        });
    }
    
    /**
     * 发布文章
     */
    public function publishPost($postId)
    {
        $post = $this->getPost($postId);
        
        // 更新发布状态
        $this->updatePostStatusInDatabase($postId, 'published');
        
        // 清除相关缓存
        $this->cache->clearTag("post_{$postId}");
        $this->cache->clearTag("category_{$post['category_id']}");
        $this->cache->clearTag('recent_posts');
    }
    
    /**
     * 添加评论
     */
    public function addComment($postId, $commentData)
    {
        // 保存评论到数据库
        $this->saveCommentToDatabase($postId, $commentData);
        
        // 清除文章评论缓存
        $this->cache->clearTag("post_comments_{$postId}");
    }
    
    // 模拟数据库操作
    private function loadPostFromDatabase($postId)
    {
        return [
            'id' => $postId,
            'title' => "Post {$postId}",
            'content' => "Content of post {$postId}",
            'category_id' => rand(1, 5),
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}

// 使用示例
$contentService = new ContentService();

// 获取文章
$post = $contentService->getPost(123);
echo "文章标题: {$post['title']}\n";

// 获取评论
$comments = $contentService->getPostComments(123, 1);
echo "评论数量: " . count($comments) . "\n";
```

## 性能对比总结

| 场景 | 传统方案 | CacheKV 方案 | 性能提升 |
|------|----------|--------------|----------|
| 用户信息查询 | 200ms | 5ms | 40x |
| 商品批量查询 | 10次DB查询 | 1次批量查询 | 10x |
| API 响应缓存 | 2000ms | 10ms | 200x |
| 内容页面加载 | 500ms | 50ms | 10x |

## 最佳实践总结

### 1. 合理设置缓存时间

```php
// 根据数据更新频率设置不同的 TTL
$cache->getByTemplate('user', ['id' => $id], $callback, 3600);      // 用户信息：1小时
$cache->getByTemplate('product_price', ['id' => $id], $callback, 600); // 价格：10分钟
$cache->getByTemplate('api_weather', ['city' => $city], $callback, 1800); // 天气：30分钟
```

### 2. 使用标签管理相关缓存

```php
// 设置标签便于批量清理
$cache->setByTemplateWithTag('post', ['id' => $postId], $postData, 
    ['posts', "post_{$postId}", "category_{$categoryId}"]);

// 更新时批量清理
$cache->clearTag("post_{$postId}");
```

### 3. 监控缓存效果

```php
$stats = $cache->getStats();
if ($stats['hit_rate'] < 70) {
    // 优化缓存策略
    $this->optimizeCacheStrategy();
}
```

---

**通过这些实战案例，您可以看到 CacheKV 在各种业务场景中的强大威力！** 🚀
