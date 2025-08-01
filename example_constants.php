<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;

// 1. 定义缓存模板常量
class CacheTemplates {
    const USER = 'user_profile';
    const PRODUCT = 'product_info';
    const ORDER = 'order_detail';
    const USER_PERMISSIONS = 'user_permissions';
    const CATEGORY = 'category';
}

// 2. 配置 CacheKV
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
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            // 使用常量作为键，模板作为值
            CacheTemplates::USER => 'user:{id}',
            CacheTemplates::PRODUCT => 'product:{id}',
            CacheTemplates::ORDER => 'order:{id}',
            CacheTemplates::USER_PERMISSIONS => 'user_perms:{user_id}',
            CacheTemplates::CATEGORY => 'category:{id}',
        ]
    ]
]);

// 3. 获取 CacheKV 实例
$cache = CacheKVFactory::store();

// 4. 使用常量进行缓存操作
echo "=== 使用常量定义的模板名称 ===\n";

// 用户信息缓存
$user = $cache->getByTemplate(CacheTemplates::USER, ['id' => 123], function() {
    echo "从数据库获取用户 123\n";
    return [
        'id' => 123,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
});
echo "用户信息: " . json_encode($user) . "\n\n";

// 商品信息缓存
$product = $cache->getByTemplate(CacheTemplates::PRODUCT, ['id' => 456], function() {
    echo "从数据库获取商品 456\n";
    return [
        'id' => 456,
        'name' => 'iPhone 15',
        'price' => 999.99
    ];
});
echo "商品信息: " . json_encode($product) . "\n\n";

// 用户权限缓存
$permissions = $cache->getByTemplate(CacheTemplates::USER_PERMISSIONS, ['user_id' => 123], function() {
    echo "从数据库获取用户 123 的权限\n";
    return ['read', 'write', 'admin'];
});
echo "用户权限: " . json_encode($permissions) . "\n\n";

// 5. 使用辅助函数（更简洁）
echo "=== 使用辅助函数 ===\n";

$user2 = cache_kv_get(CacheTemplates::USER, ['id' => 456], function() {
    echo "从数据库获取用户 456\n";
    return [
        'id' => 456,
        'name' => 'Jane Smith',
        'email' => 'jane@example.com'
    ];
});
echo "用户信息: " . json_encode($user2) . "\n\n";

// 6. 批量操作
echo "=== 批量操作 ===\n";

$keyManager = CacheKVFactory::getKeyManager();
$userIds = [123, 456, 789];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make(CacheTemplates::USER, ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    echo "批量从数据库获取用户: " . implode(', ', $missingKeys) . "\n";
    $result = [];
    foreach ($missingKeys as $key) {
        // 从 key 中提取用户 ID
        preg_match('/user:(\d+)$/', $key, $matches);
        $userId = $matches[1];
        $result[$key] = [
            'id' => (int)$userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com"
        ];
    }
    return $result;
});

echo "批量获取的用户: " . json_encode($users) . "\n\n";

// 7. 标签管理
echo "=== 标签管理 ===\n";

// 设置带标签的缓存
$cache->setByTemplateWithTag(CacheTemplates::USER, ['id' => 999], [
    'id' => 999,
    'name' => 'VIP User',
    'email' => 'vip@example.com'
], ['users', 'vip_users']);

echo "设置了带标签的用户缓存\n";

// 清除标签相关的所有缓存
$cache->clearTag('users');
echo "清除了所有 users 标签的缓存\n\n";

// 8. 封装成辅助函数
class CacheHelper {
    private static $cache;
    
    private static function getCache() {
        if (!self::$cache) {
            self::$cache = CacheKVFactory::store();
        }
        return self::$cache;
    }
    
    public static function getUser($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
            // 模拟数据库查询
            return [
                'id' => $userId,
                'name' => "User {$userId}",
                'email' => "user{$userId}@example.com"
            ];
        });
    }
    
    public static function getProduct($productId) {
        return self::getCache()->getByTemplate(CacheTemplates::PRODUCT, ['id' => $productId], function() use ($productId) {
            // 模拟数据库查询
            return [
                'id' => $productId,
                'name' => "Product {$productId}",
                'price' => rand(10, 1000)
            ];
        });
    }
    
    public static function getUserPermissions($userId) {
        return self::getCache()->getByTemplate(CacheTemplates::USER_PERMISSIONS, ['user_id' => $userId], function() use ($userId) {
            // 模拟权限查询
            return $userId == 123 ? ['admin', 'read', 'write'] : ['read'];
        });
    }
}

// 9. 使用封装的辅助函数
echo "=== 使用封装的辅助函数 ===\n";

$user = CacheHelper::getUser(123);
echo "用户: " . json_encode($user) . "\n";

$product = CacheHelper::getProduct(789);
echo "商品: " . json_encode($product) . "\n";

$permissions = CacheHelper::getUserPermissions(123);
echo "权限: " . json_encode($permissions) . "\n";

echo "\n=== 完成 ===\n";
