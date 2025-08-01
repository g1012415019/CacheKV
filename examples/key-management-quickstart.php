<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== CacheKV Key 管理快速入门 ===\n\n";

// 方式1：快速创建（最简单）
echo "1. 快速创建方式：\n";

$cache = cache_kv_quick('shop', 'dev', [
    'user' => 'user:{id}',
    'product' => 'product:{id}',
    'cart' => 'cart:{user_id}',
]);

// 直接使用
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    echo "  -> 获取用户数据\n";
    return ['id' => 123, 'name' => 'Alice', 'email' => 'alice@example.com'];
});

$product = $cache->getByTemplate('product', ['id' => 456], function() {
    echo "  -> 获取产品数据\n";
    return ['id' => 456, 'name' => 'iPhone 15', 'price' => 999];
});

echo "用户: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n";
echo "产品: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n\n";

// 方式2：全局配置（推荐用于大项目）
echo "2. 全局配置方式：\n";

use Asfop\CacheKV\CacheKVFactory;

CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 1800
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'ecommerce',
        'env_prefix' => 'production',
        'version' => 'v1',
        'templates' => [
            'user' => 'user:{id}',
            'user_profile' => 'user:{id}:profile',
            'product' => 'product:{id}',
            'product_reviews' => 'product:{id}:reviews',
            'category' => 'category:{id}',
            'cart' => 'cart:{user_id}',
            'order' => 'order:{id}',
            'session' => 'session:{id}',
        ]
    ]
]);

// 使用辅助函数
$userProfile = cache_kv_get('user_profile', ['id' => 789], function() {
    echo "  -> 获取用户档案\n";
    return [
        'user_id' => 789,
        'bio' => 'Software Developer',
        'location' => 'San Francisco',
        'preferences' => ['theme' => 'dark', 'language' => 'en']
    ];
});

$productReviews = cache_kv_get('product_reviews', ['id' => 456], function() {
    echo "  -> 获取产品评论\n";
    return [
        ['rating' => 5, 'comment' => 'Excellent product!'],
        ['rating' => 4, 'comment' => 'Good value for money'],
        ['rating' => 5, 'comment' => 'Highly recommended']
    ];
});

echo "用户档案: " . json_encode($userProfile, JSON_UNESCAPED_UNICODE) . "\n";
echo "产品评论: " . json_encode($productReviews, JSON_UNESCAPED_UNICODE) . "\n\n";

// 方式3：键管理详细示例
echo "3. 键管理详细示例：\n";

$cache = cache_kv();

// 查看生成的键
$userKey = $cache->getKeyManager()->make('user', ['id' => 123]);
$productKey = $cache->getKeyManager()->make('product', ['id' => 456]);
$cartKey = $cache->getKeyManager()->make('cart', ['user_id' => 789]);

echo "生成的键:\n";
echo "  用户键: {$userKey}\n";
echo "  产品键: {$productKey}\n";
echo "  购物车键: {$cartKey}\n\n";

// 批量操作
echo "4. 批量操作示例：\n";

$productIds = [100, 200, 300];
$productKeys = array_map(function($id) use ($cache) {
    return $cache->getKeyManager()->make('product', ['id' => $id]);
}, $productIds);

$products = $cache->getMultiple($productKeys, function($missingKeys) {
    echo "  -> 批量获取缺失的产品数据\n";
    $result = [];
    foreach ($missingKeys as $key) {
        if (preg_match('/product:(\d+)/', $key, $matches)) {
            $id = $matches[1];
            $result[$key] = [
                'id' => $id,
                'name' => "Product {$id}",
                'price' => rand(50, 500)
            ];
        }
    }
    return $result;
});

echo "批量产品: " . json_encode($products, JSON_UNESCAPED_UNICODE) . "\n\n";

// 实际使用场景
echo "5. 实际使用场景：\n";

// 用户服务
function getUserWithCache($userId) {
    return cache_kv_get('user', ['id' => $userId], function() use ($userId) {
        echo "  -> 从数据库获取用户 {$userId}\n";
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com",
            'created_at' => date('Y-m-d H:i:s')
        ];
    });
}

// 产品服务
function getProductWithCache($productId) {
    return cache_kv_get('product', ['id' => $productId], function() use ($productId) {
        echo "  -> 从API获取产品 {$productId}\n";
        return [
            'id' => $productId,
            'name' => "Product {$productId}",
            'price' => rand(100, 1000),
            'stock' => rand(0, 100)
        ];
    });
}

// 使用服务
$user = getUserWithCache(555);
$product = getProductWithCache(666);

echo "服务用户: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n";
echo "服务产品: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== 快速入门完成 ===\n";
echo "🎯 选择适合你的方式：\n";
echo "  • 简单项目：使用 cache_kv_quick()\n";
echo "  • 复杂项目：使用全局配置 + 辅助函数\n";
echo "  • 企业项目：使用工厂模式 + 服务类\n";
