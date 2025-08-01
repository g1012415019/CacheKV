<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== CacheKV 工厂类使用示例 ===\n\n";

// 方式1：快速创建（推荐用于简单场景）
echo "1. 快速创建方式：\n";
$cache = CacheKVFactory::quick('test', 'dev', [
    'user' => 'user:{id}',
    'product' => 'product:{id}',
    'session' => 'session:{id}',
    'perf_test' => 'perf_test:{id}',
]);

// 直接使用
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    echo "  -> 从数据库获取用户 123\n";
    return ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
});

echo "用户信息: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n\n";

// 再次获取（应该从缓存获取）
$user2 = $cache->getByTemplate('user', ['id' => 123], function() {
    echo "  -> 这行不应该出现（应该从缓存获取）\n";
    return ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
});

echo "再次获取用户信息（从缓存）: " . json_encode($user2, JSON_UNESCAPED_UNICODE) . "\n\n";

// 方式2：配置式创建（推荐用于复杂场景）
echo "2. 配置式创建方式：\n";

CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new ArrayDriver(),
            'ttl' => 60
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'production',
        'version' => 'v2',
        'templates' => [
            'user' => 'user:{id}',
            'product' => 'product:{id}:{category}',
            'session' => 'session:{id}',
            'api_weather' => 'api:weather:{city}',
        ]
    ]
]);

$cache2 = CacheKVFactory::create();

$product = $cache2->getByTemplate('product', ['id' => 456, 'category' => 'electronics'], function() {
    echo "  -> 从API获取产品信息\n";
    return [
        'id' => 456, 
        'name' => 'iPhone 15', 
        'category' => 'electronics',
        'price' => 999
    ];
});

echo "产品信息: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n\n";

// 方式3：多实例管理
echo "3. 多实例管理：\n";

// 可以创建多个不同配置的实例
$userCache = CacheKVFactory::quick('userapp', 'prod', [
    'user' => 'user:{id}',
    'user_profile' => 'user:{id}:profile'
]);

$productCache = CacheKVFactory::quick('productapp', 'prod', [
    'product' => 'product:{id}',
    'product_reviews' => 'product:{id}:reviews'
]);

$userData = $userCache->getByTemplate('user', ['id' => 789], function() {
    echo "  -> 获取用户数据\n";
    return ['id' => 789, 'name' => 'Alice'];
});

$productData = $productCache->getByTemplate('product', ['id' => 101], function() {
    echo "  -> 获取产品数据\n";
    return ['id' => 101, 'name' => 'MacBook Pro'];
});

echo "用户数据: " . json_encode($userData, JSON_UNESCAPED_UNICODE) . "\n";
echo "产品数据: " . json_encode($productData, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== 使用完成 ===\n";
