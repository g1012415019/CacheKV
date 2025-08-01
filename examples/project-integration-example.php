<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

echo "=== CacheKV 项目集成示例 ===\n\n";

// 1. 配置 KeyManager
$keyConfig = [
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
];

$keyManager = new KeyManager($keyConfig);

echo "1. 直接使用 CacheKV + KeyManager\n";
echo "================================\n";

// 创建缓存实例并设置 KeyManager
$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 使用模板方法获取产品详情
$productId = 123;
$product = $cache->getByTemplate('product_detail', ['id' => $productId], function() use ($productId) {
    echo "从数据库加载产品 {$productId} 详情...\n";
    return [
        'id' => $productId,
        'name' => 'iPhone 15 Pro',
        'price' => 999.99,
        'description' => 'Latest iPhone model with advanced features',
        'category_id' => 1,
        'stock' => 50,
        'rating' => 4.8
    ];
});

echo "产品详情: " . json_encode($product) . "\n\n";

// 使用模板方法获取用户购物车
$userId = 456;
$cart = $cache->getByTemplate('user_cart', ['user_id' => $userId], function() use ($userId) {
    echo "从数据库加载用户 {$userId} 购物车...\n";
    return [
        'user_id' => $userId,
        'items' => [
            ['product_id' => 123, 'quantity' => 2, 'price' => 999.99],
            ['product_id' => 124, 'quantity' => 1, 'price' => 1299.99]
        ],
        'total_amount' => 3299.97,
        'item_count' => 3,
        'updated_at' => date('Y-m-d H:i:s')
    ];
});

echo "用户购物车: " . json_encode($cart) . "\n\n";

echo "2. 使用门面 + KeyManager\n";
echo "========================\n";

// 配置服务提供者，包含 KeyManager
$serviceConfig = [
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => ArrayDriver::class
        ]
    ],
    'default_ttl' => 1800,
    'key_manager' => $keyConfig
];

CacheKVServiceProvider::register($serviceConfig);

// 使用门面的模板方法
$searchQuery = 'laptop';
$searchFilters = ['category' => 'electronics', 'price_min' => 500, 'price_max' => 2000];
$searchResults = CacheKVFacade::getByTemplate('search_results', [
    'query' => $searchQuery,
    'filters_hash' => md5(serialize($searchFilters)),
    'page' => 1
], function() use ($searchQuery, $searchFilters) {
    echo "执行搜索: {$searchQuery} with filters: " . json_encode($searchFilters) . "\n";
    return [
        'query' => $searchQuery,
        'filters' => $searchFilters,
        'results' => [
            ['id' => 201, 'name' => 'MacBook Pro', 'price' => 1999.99],
            ['id' => 202, 'name' => 'Dell XPS 13', 'price' => 1299.99],
            ['id' => 203, 'name' => 'ThinkPad X1', 'price' => 1599.99]
        ],
        'total_count' => 3,
        'page' => 1,
        'per_page' => 20
    ];
});

echo "搜索结果: " . json_encode($searchResults) . "\n\n";

echo "3. 业务场景集成示例\n";
echo "==================\n";

// 电商业务类示例
class EcommerceService
{
    private $cache;
    
    public function __construct($cache)
    {
        $this->cache = $cache;
    }
    
    public function getProductWithReviews($productId, $reviewPage = 1)
    {
        // 获取产品详情
        $product = $this->cache->getByTemplate('product_detail', ['id' => $productId], function() use ($productId) {
            return $this->loadProductFromDB($productId);
        });
        
        // 获取产品评论
        $reviews = $this->cache->getByTemplate('product_reviews', [
            'id' => $productId,
            'page' => $reviewPage
        ], function() use ($productId, $reviewPage) {
            return $this->loadProductReviewsFromDB($productId, $reviewPage);
        });
        
        return [
            'product' => $product,
            'reviews' => $reviews
        ];
    }
    
    public function getCategoryProducts($categoryId, $sort = 'popular', $page = 1)
    {
        return $this->cache->getByTemplate('category_products', [
            'id' => $categoryId,
            'sort' => $sort,
            'page' => $page
        ], function() use ($categoryId, $sort, $page) {
            return $this->loadCategoryProductsFromDB($categoryId, $sort, $page);
        });
    }
    
    public function getUserRecommendations($userId, $type = 'similar')
    {
        return $this->cache->getByTemplate('user_recommendations', [
            'user_id' => $userId,
            'type' => $type
        ], function() use ($userId, $type) {
            return $this->generateRecommendations($userId, $type);
        }, 7200); // 推荐结果缓存2小时
    }
    
    public function updateUserCart($userId, $items)
    {
        // 更新购物车数据
        $cartData = [
            'user_id' => $userId,
            'items' => $items,
            'total_amount' => array_sum(array_column($items, 'total')),
            'item_count' => array_sum(array_column($items, 'quantity')),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // 使用模板设置缓存
        $this->cache->setByTemplate('user_cart', ['user_id' => $userId], $cartData);
        
        return $cartData;
    }
    
    public function clearUserRelatedCache($userId)
    {
        // 清除用户相关的所有缓存
        $templates = ['user_cart', 'user_wishlist', 'user_recommendations'];
        
        foreach ($templates as $template) {
            $key = $this->cache->makeKey($template, ['user_id' => $userId]);
            $this->cache->forget($key);
        }
    }
    
    // 模拟数据库操作
    private function loadProductFromDB($productId)
    {
        echo "从数据库加载产品 {$productId}...\n";
        return [
            'id' => $productId,
            'name' => "Product {$productId}",
            'price' => rand(100, 2000) + 0.99,
            'category_id' => rand(1, 10),
            'stock' => rand(0, 100)
        ];
    }
    
    private function loadProductReviewsFromDB($productId, $page)
    {
        echo "从数据库加载产品 {$productId} 第 {$page} 页评论...\n";
        return [
            'product_id' => $productId,
            'page' => $page,
            'reviews' => [
                ['user' => 'User1', 'rating' => 5, 'comment' => 'Great product!'],
                ['user' => 'User2', 'rating' => 4, 'comment' => 'Good value for money.']
            ],
            'total_count' => 25
        ];
    }
    
    private function loadCategoryProductsFromDB($categoryId, $sort, $page)
    {
        echo "从数据库加载分类 {$categoryId} 产品 (排序: {$sort}, 页码: {$page})...\n";
        return [
            'category_id' => $categoryId,
            'sort' => $sort,
            'page' => $page,
            'products' => [
                ['id' => 301, 'name' => 'Product A', 'price' => 299.99],
                ['id' => 302, 'name' => 'Product B', 'price' => 399.99]
            ],
            'total_count' => 50
        ];
    }
    
    private function generateRecommendations($userId, $type)
    {
        echo "为用户 {$userId} 生成 {$type} 推荐...\n";
        return [
            'user_id' => $userId,
            'type' => $type,
            'recommendations' => [
                ['id' => 401, 'name' => 'Recommended Product 1', 'score' => 0.95],
                ['id' => 402, 'name' => 'Recommended Product 2', 'score' => 0.87]
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}

// 使用业务服务
$ecommerce = new EcommerceService($cache);

// 获取产品和评论
$productWithReviews = $ecommerce->getProductWithReviews(123, 1);
echo "产品和评论: " . json_encode($productWithReviews) . "\n\n";

// 获取分类产品
$categoryProducts = $ecommerce->getCategoryProducts(1, 'price_asc', 1);
echo "分类产品: " . json_encode($categoryProducts) . "\n\n";

// 获取用户推荐
$recommendations = $ecommerce->getUserRecommendations(456, 'similar');
echo "用户推荐: " . json_encode($recommendations) . "\n\n";

// 更新购物车
$newCartItems = [
    ['product_id' => 123, 'quantity' => 3, 'price' => 999.99, 'total' => 2999.97],
    ['product_id' => 124, 'quantity' => 1, 'price' => 1299.99, 'total' => 1299.99]
];
$updatedCart = $ecommerce->updateUserCart(456, $newCartItems);
echo "更新后的购物车: " . json_encode($updatedCart) . "\n\n";

echo "4. 缓存键管理和监控\n";
echo "==================\n";

// 显示生成的键
echo "生成的缓存键示例:\n";
$keys = [
    $cache->makeKey('product_detail', ['id' => 123]),
    $cache->makeKey('user_cart', ['user_id' => 456]),
    $cache->makeKey('search_results', ['query' => 'laptop', 'filters_hash' => 'abc123', 'page' => 1]),
    $cache->makeKey('category_products', ['id' => 1, 'sort' => 'price_asc', 'page' => 1])
];

foreach ($keys as $key) {
    echo "  - {$key}\n";
}

// 缓存统计
echo "\n缓存统计信息:\n";
$stats = $cache->getStats();
echo "  - 命中次数: {$stats['hits']}\n";
echo "  - 未命中次数: {$stats['misses']}\n";
echo "  - 命中率: {$stats['hit_rate']}%\n";

// 键解析示例
echo "\n键解析示例:\n";
$sampleKey = $cache->makeKey('product_detail', ['id' => 123]);
$parsed = $cache->getKeyManager()->parse($sampleKey);
echo "  - 原始键: {$sampleKey}\n";
echo "  - 应用前缀: {$parsed['app_prefix']}\n";
echo "  - 环境前缀: {$parsed['env_prefix']}\n";
echo "  - 版本: {$parsed['version']}\n";
echo "  - 业务键: {$parsed['business_key']}\n";

echo "\n5. 标签管理集成\n";
echo "===============\n";

// 使用标签管理相关缓存
$cache->setByTemplateWithTag('product_detail', ['id' => 123], $product, ['products', 'electronics']);
$cache->setByTemplateWithTag('product_detail', ['id' => 124], [
    'id' => 124,
    'name' => 'iPad Pro',
    'price' => 1099.99,
    'category_id' => 1
], ['products', 'electronics']);

echo "设置了带标签的产品缓存\n";

// 清除标签
echo "清除 'electronics' 标签下的所有缓存...\n";
$cache->clearTag('electronics');

// 验证缓存是否被清除
$productExists = $cache->hasByTemplate('product_detail', ['id' => 123]);
echo "产品 123 缓存是否存在: " . ($productExists ? 'Yes' : 'No') . "\n";

echo "\n=== 项目集成示例完成 ===\n";
echo "\n💡 这个示例展示了 CacheKV 与 KeyManager 的完整集成，\n";
echo "   包括直接使用、门面使用、业务场景应用等多种方式。\n";
