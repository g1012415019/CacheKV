<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;

echo "=== CacheKV 最佳实践指南 ===\n\n";

// ========================================
// 方案1：全局配置 + 辅助函数（推荐）
// ========================================
echo "【推荐方案】全局配置 + 辅助函数：\n";

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
        'app_prefix' => 'myapp',
        'env_prefix' => 'production',
        'version' => 'v1',
        'templates' => [
            'user' => 'user:{id}',
            'product' => 'product:{id}',
            'order' => 'order:{id}',
            'session' => 'session:{id}',
            'api_weather' => 'api:weather:{city}',
        ]
    ]
]);

// 之后在任何地方都可以直接使用
$user = cache_kv_get('user', ['id' => 123], function() {
    echo "  -> 获取用户 123\n";
    return ['id' => 123, 'name' => 'John Doe'];
});

$product = cache_kv_get('product', ['id' => 456], function() {
    echo "  -> 获取产品 456\n";
    return ['id' => 456, 'name' => 'iPhone 15'];
});

echo "用户: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n";
echo "产品: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案2：快速创建（适合简单场景）
// ========================================
echo "【简单场景】快速创建：\n";

$cache = cache_kv_quick('testapp', 'dev', [
    'user' => 'user:{id}',
    'post' => 'post:{id}',
]);

$post = $cache->getByTemplate('post', ['id' => 789], function() {
    echo "  -> 获取文章 789\n";
    return ['id' => 789, 'title' => 'Hello World', 'content' => 'This is a test post'];
});

echo "文章: " . json_encode($post, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案3：多环境配置
// ========================================
echo "【多环境】不同环境配置：\n";

// 开发环境
$devCache = cache_kv_quick('myapp', 'dev', [
    'user' => 'user:{id}',
    'debug' => 'debug:{key}',
]);

// 生产环境
$prodCache = cache_kv_quick('myapp', 'prod', [
    'user' => 'user:{id}',
    'stats' => 'stats:{type}:{date}',
]);

$devUser = $devCache->getByTemplate('user', ['id' => 1], function() {
    echo "  -> 开发环境获取用户\n";
    return ['id' => 1, 'name' => 'Dev User'];
});

$prodUser = $prodCache->getByTemplate('user', ['id' => 1], function() {
    echo "  -> 生产环境获取用户\n";
    return ['id' => 1, 'name' => 'Prod User'];
});

echo "开发用户: " . json_encode($devUser, JSON_UNESCAPED_UNICODE) . "\n";
echo "生产用户: " . json_encode($prodUser, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 实际业务场景示例
// ========================================
echo "【实际业务】电商系统示例：\n";

class UserService {
    public function getUser($userId) {
        return cache_kv_get('user', ['id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取用户 {$userId}\n";
            return [
                'id' => $userId,
                'name' => "User {$userId}",
                'email' => "user{$userId}@example.com"
            ];
        });
    }
    
    public function updateUser($userId, $data) {
        // 更新数据库
        echo "  -> 更新数据库用户 {$userId}\n";
        
        // 清除缓存
        cache_kv_forget('user', ['id' => $userId]);
        echo "  -> 清除用户 {$userId} 缓存\n";
    }
}

class ProductService {
    public function getProduct($productId) {
        return cache_kv_get('product', ['id' => $productId], function() use ($productId) {
            echo "  -> 从API获取产品 {$productId}\n";
            return [
                'id' => $productId,
                'name' => "Product {$productId}",
                'price' => rand(100, 1000)
            ];
        });
    }
}

$userService = new UserService();
$productService = new ProductService();

// 使用服务
$user = $userService->getUser(100);
$product = $productService->getProduct(200);

echo "业务用户: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n";
echo "业务产品: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n";

// 更新用户（会清除缓存）
$userService->updateUser(100, ['name' => 'Updated User']);

// 再次获取（会重新从数据库获取）
$updatedUser = $userService->getUser(100);
echo "更新后用户: " . json_encode($updatedUser, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== 总结 ===\n";
echo "✅ 不再需要重复创建 KeyManager 和 CacheKV 实例\n";
echo "✅ 一次配置，全局使用\n";
echo "✅ 支持多环境、多实例\n";
echo "✅ 提供辅助函数，使用更简单\n";
echo "✅ 完全符合 Composer PSR-4 标准\n";
