<?php

require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVServiceProvider;

echo "=== CacheKV 完整功能示例 ===\n\n";

// ========================================
// 方案1：使用辅助函数（最简单）
// ========================================
echo "1. 使用辅助函数（推荐）\n";
echo "=================================\n";

// 一次性配置
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'demo',
        'env_prefix' => 'dev',
        'version' => 'v1',
        'templates' => [
            'user' => 'user:{id}',
            'order' => 'order:{id}',
            'cart' => 'cart:{user_id}',
            'product_reviews' => 'product:reviews:{id}:page:{page}',
        ]
    ]
]);

// 直接使用辅助函数
$user = cache_kv_get('user', ['id' => 123], function() {
    echo "  -> 从数据库获取用户 123\n";
    return ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
});

$order = cache_kv_get('order', ['id' => 456], function() {
    echo "  -> 从数据库获取订单 456\n";
    return ['id' => 456, 'user_id' => 123, 'total' => 299.99, 'status' => 'completed'];
});

echo "用户信息: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n";
echo "订单信息: " . json_encode($order, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案2：快速创建独立实例
// ========================================
echo "2. 快速创建独立实例\n";
echo "=================================\n";

$cache = cache_kv_quick('shop', 'prod', [
    'product' => 'product:{id}',
    'category' => 'category:{id}',
    'brand' => 'brand:{id}',
]);

$product = $cache->getByTemplate('product', ['id' => 789], function() {
    echo "  -> 从API获取产品 789\n";
    return [
        'id' => 789,
        'name' => 'iPhone 15 Pro',
        'price' => 999,
        'category' => 'Electronics',
        'brand' => 'Apple'
    ];
});

echo "产品信息: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案3：门面模式
// ========================================
echo "3. 门面模式\n";
echo "=================================\n";

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
        'version' => 'v1',
        'templates' => [
            'post' => 'post:{id}',
            'comment' => 'comment:{id}',
            'tag' => 'tag:{name}',
        ]
    ]
]);

// 使用门面
$post = CacheKVFacade::getByTemplate('post', ['id' => 100], function() {
    echo "  -> 通过门面获取文章 100\n";
    return [
        'id' => 100,
        'title' => 'CacheKV 使用指南',
        'content' => '这是一篇关于 CacheKV 的文章...',
        'author' => 'Developer',
        'created_at' => date('Y-m-d H:i:s')
    ];
});

echo "文章信息: " . json_encode($post, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案4：批量操作
// ========================================
echo "4. 批量操作\n";
echo "=================================\n";

// 批量获取用户
$userIds = [1, 2, 3, 4, 5];
$userKeys = array_map(function($id) {
    return cache_kv()->getKeyManager()->make('user', ['id' => $id]);
}, $userIds);

$users = cache_kv()->getMultiple($userKeys, function($missingKeys) {
    echo "  -> 批量获取缺失的用户数据\n";
    $result = [];
    foreach ($missingKeys as $key) {
        if (preg_match('/user:(\d+)/', $key, $matches)) {
            $id = $matches[1];
            $result[$key] = [
                'id' => $id,
                'name' => "User {$id}",
                'email' => "user{$id}@example.com"
            ];
        }
    }
    return $result;
});

echo "批量用户数量: " . count($users) . "\n\n";

// ========================================
// 方案5：标签管理
// ========================================
echo "5. 标签管理\n";
echo "=================================\n";

$cache = cache_kv();

// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 999], [
    'id' => 999,
    'name' => 'Tagged User',
    'role' => 'admin'
], ['users', 'admins']);

echo "设置带标签的用户缓存\n";

// 获取缓存
$taggedUser = $cache->getByTemplate('user', ['id' => 999], function() {
    echo "  -> 这行不应该出现（应该从缓存获取）\n";
    return [];
});

echo "带标签用户: " . json_encode($taggedUser, JSON_UNESCAPED_UNICODE) . "\n";

// 清除标签
$cache->clearTag('users');
echo "清除 'users' 标签的所有缓存\n\n";

// ========================================
// 方案6：实际业务场景
// ========================================
echo "6. 实际业务场景\n";
echo "=================================\n";

// 电商服务类
class EcommerceService {
    public function getUserProfile($userId) {
        return cache_kv_get('user', ['id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取用户档案 {$userId}\n";
            return [
                'id' => $userId,
                'name' => "User {$userId}",
                'email' => "user{$userId}@shop.com",
                'level' => 'VIP',
                'points' => rand(100, 1000)
            ];
        });
    }
    
    public function getUserCart($userId) {
        return cache_kv_get('cart', ['user_id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取购物车 {$userId}\n";
            return [
                'user_id' => $userId,
                'items' => [
                    ['product_id' => 1, 'name' => 'Product A', 'quantity' => 2, 'price' => 99.99],
                    ['product_id' => 2, 'name' => 'Product B', 'quantity' => 1, 'price' => 199.99],
                ],
                'total' => 399.97
            ];
        });
    }
    
    public function getProductReviews($productId, $page = 1) {
        return cache_kv_get('product_reviews', ['id' => $productId, 'page' => $page], function() use ($productId, $page) {
            echo "  -> 从数据库获取产品评论 {$productId} 第{$page}页\n";
            return [
                'product_id' => $productId,
                'page' => $page,
                'reviews' => [
                    ['rating' => 5, 'comment' => 'Excellent!', 'user' => 'Customer A'],
                    ['rating' => 4, 'comment' => 'Good quality', 'user' => 'Customer B'],
                    ['rating' => 5, 'comment' => 'Highly recommend', 'user' => 'Customer C'],
                ]
            ];
        });
    }
    
    public function updateUserProfile($userId, $data) {
        echo "  -> 更新用户档案 {$userId}\n";
        // 更新数据库后清除缓存
        cache_kv_forget('user', ['id' => $userId]);
        echo "  -> 清除用户缓存\n";
    }
}

// 使用业务服务
$ecommerce = new EcommerceService();

$userProfile = $ecommerce->getUserProfile(888);
echo "用户档案: " . json_encode($userProfile, JSON_UNESCAPED_UNICODE) . "\n";

$userCart = $ecommerce->getUserCart(888);
echo "用户购物车: " . json_encode($userCart, JSON_UNESCAPED_UNICODE) . "\n";

$reviews = $ecommerce->getProductReviews(555, 1);
echo "产品评论: " . json_encode($reviews, JSON_UNESCAPED_UNICODE) . "\n";

// 更新用户档案（会清除缓存）
$ecommerce->updateUserProfile(888, ['name' => 'Updated User']);

// 再次获取（会重新从数据库获取）
$updatedProfile = $ecommerce->getUserProfile(888);
echo "更新后档案: " . json_encode($updatedProfile, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== 完整功能示例完成 ===\n";
echo "🎯 使用建议：\n";
echo "  ✅ 简单场景：使用 cache_kv_get() 辅助函数\n";
echo "  ✅ 独立模块：使用 cache_kv_quick() 快速创建\n";
echo "  ✅ 大型项目：使用工厂模式 + 业务服务类\n";
echo "  ✅ 企业应用：使用门面模式 + 依赖注入\n";
echo "  ✅ 复杂业务：结合标签管理 + 批量操作\n";
