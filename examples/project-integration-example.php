<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVServiceProvider;

echo "=== CacheKV 项目集成示例 ===\n\n";

// ========================================
// 方案1：工厂模式集成（推荐）
// ========================================
echo "1. 工厂模式集成\n";
echo "================================\n";

// 在应用启动时配置一次
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'ecommerce',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            // 电商业务模板
            'product_detail' => 'product:detail:{id}',
            'product_reviews' => 'product:reviews:{id}:page:{page}',
            'category_products' => 'category:products:{id}:sort:{sort}:page:{page}',
            'user_cart' => 'user:cart:{user_id}',
            'user_wishlist' => 'user:wishlist:{user_id}',
            'order_detail' => 'order:detail:{id}',
            'search_results' => 'search:{query}:filters:{filters_hash}:page:{page}',
            'hot_products' => 'hot:products:{category}:limit:{limit}',
            'user_recommendations' => 'recommendations:{user_id}:type:{type}',
        ]
    ]
]);

// 业务服务类示例
class ProductService {
    public function getProductDetail($productId) {
        return cache_kv_get('product_detail', ['id' => $productId], function() use ($productId) {
            echo "  -> 从数据库获取产品详情 {$productId}\n";
            return [
                'id' => $productId,
                'name' => "Product {$productId}",
                'price' => rand(100, 1000),
                'description' => "This is product {$productId}",
                'stock' => rand(0, 100)
            ];
        });
    }
    
    public function getProductReviews($productId, $page = 1) {
        return cache_kv_get('product_reviews', ['id' => $productId, 'page' => $page], function() use ($productId, $page) {
            echo "  -> 从数据库获取产品评论 {$productId} 第{$page}页\n";
            return [
                'page' => $page,
                'total' => 50,
                'reviews' => [
                    ['rating' => 5, 'comment' => 'Great product!', 'user' => 'User A'],
                    ['rating' => 4, 'comment' => 'Good value', 'user' => 'User B'],
                ]
            ];
        });
    }
    
    public function getCategoryProducts($categoryId, $sort = 'popular', $page = 1) {
        return cache_kv_get('category_products', [
            'id' => $categoryId, 
            'sort' => $sort, 
            'page' => $page
        ], function() use ($categoryId, $sort, $page) {
            echo "  -> 从数据库获取分类产品 {$categoryId} 排序:{$sort} 第{$page}页\n";
            return [
                'category_id' => $categoryId,
                'sort' => $sort,
                'page' => $page,
                'products' => [
                    ['id' => 1, 'name' => 'Product 1', 'price' => 299],
                    ['id' => 2, 'name' => 'Product 2', 'price' => 399],
                ]
            ];
        });
    }
}

class UserService {
    public function getUserCart($userId) {
        return cache_kv_get('user_cart', ['user_id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取用户购物车 {$userId}\n";
            return [
                'user_id' => $userId,
                'items' => [
                    ['product_id' => 1, 'quantity' => 2, 'price' => 299],
                    ['product_id' => 2, 'quantity' => 1, 'price' => 399],
                ],
                'total' => 997
            ];
        });
    }
    
    public function getUserWishlist($userId) {
        return cache_kv_get('user_wishlist', ['user_id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取用户愿望清单 {$userId}\n";
            return [
                'user_id' => $userId,
                'items' => [
                    ['product_id' => 3, 'added_at' => '2024-01-01'],
                    ['product_id' => 4, 'added_at' => '2024-01-02'],
                ]
            ];
        });
    }
    
    public function updateUserCart($userId, $items) {
        echo "  -> 更新用户购物车 {$userId}\n";
        // 更新数据库后清除缓存
        cache_kv_forget('user_cart', ['user_id' => $userId]);
        echo "  -> 清除购物车缓存\n";
    }
}

class SearchService {
    public function search($query, $filters = [], $page = 1) {
        $filtersHash = md5(json_encode($filters));
        return cache_kv_get('search_results', [
            'query' => $query,
            'filters_hash' => $filtersHash,
            'page' => $page
        ], function() use ($query, $filters, $page) {
            echo "  -> 执行搜索查询: {$query} 第{$page}页\n";
            return [
                'query' => $query,
                'filters' => $filters,
                'page' => $page,
                'total' => 100,
                'results' => [
                    ['id' => 10, 'name' => "Search Result 1 for {$query}"],
                    ['id' => 11, 'name' => "Search Result 2 for {$query}"],
                ]
            ];
        });
    }
    
    public function getHotProducts($category, $limit = 10) {
        return cache_kv_get('hot_products', ['category' => $category, 'limit' => $limit], function() use ($category, $limit) {
            echo "  -> 获取热门产品 分类:{$category} 限制:{$limit}\n";
            return [
                'category' => $category,
                'limit' => $limit,
                'products' => array_map(function($i) {
                    return ['id' => $i, 'name' => "Hot Product {$i}", 'sales' => rand(100, 1000)];
                }, range(1, $limit))
            ];
        });
    }
}

// 使用业务服务
echo "使用业务服务：\n";

$productService = new ProductService();
$userService = new UserService();
$searchService = new SearchService();

// 获取产品详情
$product = $productService->getProductDetail(123);
echo "产品详情: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n\n";

// 获取产品评论
$reviews = $productService->getProductReviews(123, 1);
echo "产品评论: " . json_encode($reviews, JSON_UNESCAPED_UNICODE) . "\n\n";

// 获取分类产品
$categoryProducts = $productService->getCategoryProducts(5, 'price', 1);
echo "分类产品: " . json_encode($categoryProducts, JSON_UNESCAPED_UNICODE) . "\n\n";

// 获取用户购物车
$cart = $userService->getUserCart(789);
echo "用户购物车: " . json_encode($cart, JSON_UNESCAPED_UNICODE) . "\n\n";

// 搜索产品
$searchResults = $searchService->search('iPhone', ['brand' => 'Apple'], 1);
echo "搜索结果: " . json_encode($searchResults, JSON_UNESCAPED_UNICODE) . "\n\n";

// 获取热门产品
$hotProducts = $searchService->getHotProducts('electronics', 5);
echo "热门产品: " . json_encode($hotProducts, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案2：门面模式集成
// ========================================
echo "2. 门面模式集成\n";
echo "================================\n";

// 注册服务提供者
CacheKVServiceProvider::register([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 1800
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'webapp',
        'env_prefix' => 'prod',
        'version' => 'v2',
        'templates' => [
            'user' => 'user:{id}',
            'post' => 'post:{id}',
            'comment' => 'comment:{id}',
        ]
    ]
]);

// 使用门面
$user = CacheKVFacade::getByTemplate('user', ['id' => 456], function() {
    echo "  -> 通过门面获取用户数据\n";
    return ['id' => 456, 'name' => 'Facade User', 'email' => 'facade@example.com'];
});

echo "门面用户: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案3：多环境配置
// ========================================
echo "3. 多环境配置\n";
echo "================================\n";

// 开发环境
$devCache = cache_kv_quick('myapp', 'dev', [
    'user' => 'user:{id}',
    'debug' => 'debug:{key}',
]);

// 生产环境
$prodCache = cache_kv_quick('myapp', 'prod', [
    'user' => 'user:{id}',
    'analytics' => 'analytics:{type}:{date}',
]);

$devUser = $devCache->getByTemplate('user', ['id' => 1], function() {
    echo "  -> 开发环境获取用户\n";
    return ['id' => 1, 'name' => 'Dev User', 'debug' => true];
});

$prodUser = $prodCache->getByTemplate('user', ['id' => 1], function() {
    echo "  -> 生产环境获取用户\n";
    return ['id' => 1, 'name' => 'Prod User', 'debug' => false];
});

echo "开发用户: " . json_encode($devUser, JSON_UNESCAPED_UNICODE) . "\n";
echo "生产用户: " . json_encode($prodUser, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案4：批量操作示例
// ========================================
echo "4. 批量操作示例\n";
echo "================================\n";

// 批量获取产品
$productIds = [100, 200, 300, 400, 500];
$productKeys = array_map(function($id) {
    return cache_kv()->getKeyManager()->make('product_detail', ['id' => $id]);
}, $productIds);

$products = cache_kv()->getMultiple($productKeys, function($missingKeys) {
    echo "  -> 批量获取缺失的产品数据\n";
    $result = [];
    foreach ($missingKeys as $key) {
        if (preg_match('/product:detail:(\d+)/', $key, $matches)) {
            $id = $matches[1];
            $result[$key] = [
                'id' => $id,
                'name' => "Batch Product {$id}",
                'price' => rand(100, 1000)
            ];
        }
    }
    return $result;
});

echo "批量产品数量: " . count($products) . "\n\n";

echo "=== 项目集成示例完成 ===\n";
echo "🎯 集成建议：\n";
echo "  • 小型项目：使用 cache_kv_quick() 快速创建\n";
echo "  • 中型项目：使用工厂模式 + 业务服务类\n";
echo "  • 大型项目：使用门面模式 + 依赖注入\n";
echo "  • 企业项目：结合配置文件 + 环境管理\n";
