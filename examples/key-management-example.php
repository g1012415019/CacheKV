<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

echo "=== CacheKV Key 管理使用示例 ===\n\n";

// 创建 KeyManager 实例
$keyConfig = [
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v2',
    'separator' => ':',
    'templates' => [
        // 自定义模板
        'order' => 'order:{id}',
        'order_items' => 'order:items:{order_id}',
        'cart' => 'cart:{user_id}',
        'wishlist' => 'wishlist:{user_id}:page:{page}',
    ]
];

$keyManager = new KeyManager($keyConfig);

// 创建缓存实例
$driver = new ArrayDriver();
$cache = new CacheKV($driver, 3600);

echo "1. 基本 Key 生成\n";
echo "================\n";

// 使用预定义模板生成键
$userKey = $keyManager->make('user', ['id' => 123]);
echo "用户键: {$userKey}\n";

$productKey = $keyManager->make('product', ['id' => 456]);
echo "产品键: {$productKey}\n";

$userProfileKey = $keyManager->make('user_profile', ['id' => 123]);
echo "用户资料键: {$userProfileKey}\n";

// 使用自定义模板
$orderKey = $keyManager->make('order', ['id' => 'ORD001']);
echo "订单键: {$orderKey}\n";

$cartKey = $keyManager->make('cart', ['user_id' => 123]);
echo "购物车键: {$cartKey}\n";

echo "\n2. 复杂参数的 Key 生成\n";
echo "=====================\n";

// 分页列表键
$categoryProductsKey = $keyManager->make('category_products', [
    'id' => 'electronics',
    'page' => 1
]);
echo "分类产品列表键: {$categoryProductsKey}\n";

// 搜索结果键
$searchKey = $keyManager->make('search', [
    'query' => 'iphone',
    'page' => 2
]);
echo "搜索结果键: {$searchKey}\n";

// API 响应键（使用哈希处理复杂参数）
$apiParams = [
    'endpoint' => 'products',
    'params_hash' => ['category' => 'electronics', 'sort' => 'price', 'filters' => ['brand' => 'apple']]
];
$apiKey = $keyManager->makeWithHash('api_response', $apiParams, ['params_hash']);
echo "API 响应键: {$apiKey}\n";

echo "\n3. 在缓存操作中使用 Key 管理\n";
echo "============================\n";

// 用户数据缓存
$userId = 123;
$userCacheKey = $keyManager->make('user', ['id' => $userId]);

$userData = $cache->get($userCacheKey, function() use ($userId) {
    echo "从数据库获取用户 {$userId} 的信息...\n";
    return [
        'id' => $userId,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'created_at' => '2024-01-01'
    ];
});

echo "用户数据: " . json_encode($userData) . "\n";

// 用户资料缓存
$userProfileCacheKey = $keyManager->make('user_profile', ['id' => $userId]);
$userProfile = $cache->get($userProfileCacheKey, function() use ($userId) {
    echo "从数据库获取用户 {$userId} 的资料...\n";
    return [
        'user_id' => $userId,
        'avatar' => 'avatar.jpg',
        'bio' => 'Software Developer',
        'location' => 'San Francisco'
    ];
});

echo "用户资料: " . json_encode($userProfile) . "\n";

// 产品详情缓存
$productId = 456;
$productCacheKey = $keyManager->make('product_detail', ['id' => $productId]);
$productDetail = $cache->get($productCacheKey, function() use ($productId) {
    echo "从数据库获取产品 {$productId} 的详情...\n";
    return [
        'id' => $productId,
        'name' => 'iPhone 15 Pro',
        'price' => 999.99,
        'description' => 'Latest iPhone model',
        'stock' => 50
    ];
});

echo "产品详情: " . json_encode($productDetail) . "\n";

echo "\n4. 批量 Key 操作\n";
echo "===============\n";

// 批量生成用户相关的键
$userIds = [101, 102, 103];
$userKeys = [];
foreach ($userIds as $id) {
    $userKeys[] = $keyManager->make('user', ['id' => $id]);
}

echo "批量用户键: " . implode(', ', $userKeys) . "\n";

// 使用批量获取
$users = $cache->getMultiple($userKeys, function($missingKeys) use ($keyManager) {
    echo "需要从数据库获取的用户键: " . implode(', ', $missingKeys) . "\n";
    
    $userData = [];
    foreach ($missingKeys as $key) {
        // 解析键获取用户ID
        $parsed = $keyManager->parse($key);
        $parts = explode(':', $parsed['business_key']);
        $userId = end($parts);
        
        $userData[$key] = [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com"
        ];
    }
    
    return $userData;
});

echo "批量用户数据: " . json_encode($users) . "\n";

echo "\n5. Key 模式匹配（用于批量清理）\n";
echo "==============================\n";

// 生成模式匹配键
$userPattern = $keyManager->pattern('user', ['id' => '*']);
echo "用户键模式: {$userPattern}\n";

$productPattern = $keyManager->pattern('product_detail', ['id' => '*']);
echo "产品详情键模式: {$productPattern}\n";

// 特定用户的所有相关键模式
$specificUserPattern = $keyManager->pattern('user_profile', ['id' => 123]);
echo "特定用户资料键: {$specificUserPattern}\n";

echo "\n6. Key 解析和验证\n";
echo "================\n";

// 解析键
$sampleKey = $keyManager->make('user_settings', ['id' => 789]);
$parsed = $keyManager->parse($sampleKey);

echo "原始键: {$sampleKey}\n";
echo "解析结果:\n";
echo "  - 完整键: {$parsed['full_key']}\n";
echo "  - 有前缀: " . ($parsed['has_prefix'] ? 'Yes' : 'No') . "\n";
echo "  - 应用前缀: {$parsed['app_prefix']}\n";
echo "  - 环境前缀: {$parsed['env_prefix']}\n";
echo "  - 版本: {$parsed['version']}\n";
echo "  - 业务键: {$parsed['business_key']}\n";

// 键验证
$validKey = 'myapp:prod:v2:user:123';
$invalidKey = 'invalid key with spaces!@#';

echo "\n键验证:\n";
echo "  - '{$validKey}' 是否有效: " . ($keyManager->validate($validKey) ? 'Yes' : 'No') . "\n";
echo "  - '{$invalidKey}' 是否有效: " . ($keyManager->validate($invalidKey) ? 'Yes' : 'No') . "\n";

// 键清理
$sanitizedKey = $keyManager->sanitize($invalidKey);
echo "  - 清理后的键: '{$sanitizedKey}'\n";

echo "\n7. 动态模板管理\n";
echo "===============\n";

// 添加新模板
$keyManager->addTemplate('notification', 'notification:{user_id}:{type}:{id}');
$keyManager->addTemplate('report', 'report:{type}:{date}:{format}');

// 使用新模板
$notificationKey = $keyManager->make('notification', [
    'user_id' => 123,
    'type' => 'email',
    'id' => 'welcome'
]);
echo "通知键: {$notificationKey}\n";

$reportKey = $keyManager->make('report', [
    'type' => 'sales',
    'date' => '2024-01-01',
    'format' => 'pdf'
]);
echo "报告键: {$reportKey}\n";

// 批量添加模板
$keyManager->addTemplates([
    'log' => 'log:{level}:{date}:{hour}',
    'metric' => 'metric:{name}:{timestamp}',
    'session' => 'session:{id}:{device}'
]);

$logKey = $keyManager->make('log', [
    'level' => 'error',
    'date' => '2024-01-01',
    'hour' => '14'
]);
echo "日志键: {$logKey}\n";

echo "\n8. 实际业务场景示例\n";
echo "==================\n";

// 电商场景：用户购物车
function getUserCart($userId, $cache, $keyManager) {
    $cartKey = $keyManager->make('cart', ['user_id' => $userId]);
    
    return $cache->get($cartKey, function() use ($userId) {
        echo "从数据库加载用户 {$userId} 的购物车...\n";
        return [
            'user_id' => $userId,
            'items' => [
                ['product_id' => 1, 'quantity' => 2, 'price' => 99.99],
                ['product_id' => 2, 'quantity' => 1, 'price' => 149.99]
            ],
            'total' => 349.97,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    });
}

$cart = getUserCart(123, $cache, $keyManager);
echo "购物车数据: " . json_encode($cart) . "\n";

// API 缓存场景
function getCachedApiResponse($endpoint, $params, $cache, $keyManager) {
    $apiKey = $keyManager->makeWithHash('api_response', [
        'endpoint' => $endpoint,
        'params_hash' => $params
    ], ['params_hash']);
    
    return $cache->get($apiKey, function() use ($endpoint, $params) {
        echo "调用外部 API: {$endpoint} with params: " . json_encode($params) . "\n";
        // 模拟 API 调用
        return [
            'status' => 'success',
            'data' => ['result' => 'API response data'],
            'timestamp' => time()
        ];
    }, 300); // API 响应缓存 5 分钟
}

$apiResponse = getCachedApiResponse('products/search', [
    'query' => 'laptop',
    'category' => 'electronics',
    'sort' => 'price_asc',
    'page' => 1
], $cache, $keyManager);

echo "API 响应: " . json_encode($apiResponse) . "\n";

echo "\n9. 缓存统计和监控\n";
echo "================\n";

$stats = $cache->getStats();
echo "缓存统计: " . json_encode($stats) . "\n";

// 显示所有可用模板
echo "\n可用的键模板:\n";
$templates = $keyManager->getTemplates();
foreach ($templates as $name => $pattern) {
    echo "  - {$name}: {$pattern}\n";
}

echo "\n=== Key 管理示例完成 ===\n";
