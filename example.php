<?php

require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

echo "=== CacheKV 完整功能示例 ===\n\n";

// 配置 KeyManager
$keyConfig = [
    'app_prefix' => 'demo',
    'env_prefix' => 'dev',
    'version' => 'v1',
    'templates' => [
        // 自定义业务模板
        'order' => 'order:{id}',
        'cart' => 'cart:{user_id}',
        'product_reviews' => 'product:reviews:{id}:page:{page}',
    ]
];

$keyManager = new KeyManager($keyConfig);

echo "1. 直接使用 CacheKV + KeyManager\n";
echo "=================================\n";

$driver = new ArrayDriver();
$cache = new CacheKV($driver, 3600, $keyManager);

// 使用模板方法获取用户信息
$user = $cache->getByTemplate('user', ['id' => 1], function() {
    echo "从数据库获取用户信息...\n";
    return ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30];
});
echo "用户信息: " . json_encode($user) . "\n";

// 使用模板方法获取产品信息
$product = $cache->getByTemplate('product', ['id' => 1], function() {
    echo "从数据库获取产品信息...\n";
    return ['id' => 1, 'name' => 'iPhone 15 Pro', 'price' => 999.99, 'category' => 'Electronics'];
});
echo "产品信息: " . json_encode($product) . "\n";

// 再次获取（应该从缓存获取）
$product2 = $cache->getByTemplate('product', ['id' => 1], function() {
    echo "这不应该被执行（缓存命中）\n";
    return null;
});
echo "产品信息（缓存）: " . json_encode($product2) . "\n\n";

echo "2. 标签管理示例\n";
echo "===============\n";

// 使用模板方法设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 1], $user, ['users', 'vip_users']);
$cache->setByTemplateWithTag('user', ['id' => 2], [
    'id' => 2, 
    'name' => 'Jane Smith', 
    'email' => 'jane@example.com'
], ['users', 'normal_users']);

echo "设置了带标签的用户缓存\n";

// 验证缓存存在
echo "用户1缓存存在: " . ($cache->hasByTemplate('user', ['id' => 1]) ? 'Yes' : 'No') . "\n";
echo "用户2缓存存在: " . ($cache->hasByTemplate('user', ['id' => 2]) ? 'Yes' : 'No') . "\n";

// 清除标签
echo "清除 'users' 标签下的所有缓存...\n";
$cache->clearTag('users');

echo "清除后用户1缓存存在: " . ($cache->hasByTemplate('user', ['id' => 1]) ? 'Yes' : 'No') . "\n";
echo "清除后用户2缓存存在: " . ($cache->hasByTemplate('user', ['id' => 2]) ? 'Yes' : 'No') . "\n\n";

echo "3. 批量操作示例\n";
echo "===============\n";

// 批量获取用户数据
$userIds = [101, 102, 103];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) use ($keyManager) {
    echo "批量获取缺失的用户: " . implode(', ', $missingKeys) . "\n";
    
    $userData = [];
    foreach ($missingKeys as $key) {
        // 从键中解析用户ID
        $parsed = $keyManager->parse($key);
        $userId = explode(':', $parsed['business_key'])[1];
        
        $userData[$key] = [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com",
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    return $userData;
});

echo "批量获取结果: " . count($users) . " 个用户\n\n";

echo "4. 使用门面模式\n";
echo "==============\n";

// 配置服务提供者
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
$order = CacheKVFacade::getByTemplate('order', ['id' => 'ORD001'], function() {
    echo "从数据库获取订单信息...\n";
    return [
        'id' => 'ORD001',
        'user_id' => 1,
        'total' => 999.99,
        'status' => 'completed',
        'created_at' => '2024-01-01 10:00:00'
    ];
});
echo "订单信息: " . json_encode($order) . "\n";

// 使用门面获取购物车
$cart = CacheKVFacade::getByTemplate('cart', ['user_id' => 1], function() {
    echo "从数据库获取购物车信息...\n";
    return [
        'user_id' => 1,
        'items' => [
            ['product_id' => 1, 'quantity' => 2, 'price' => 999.99],
            ['product_id' => 2, 'quantity' => 1, 'price' => 599.99]
        ],
        'total' => 2599.97,
        'updated_at' => date('Y-m-d H:i:s')
    ];
});
echo "购物车信息: " . json_encode($cart) . "\n\n";

echo "5. 键管理和解析\n";
echo "===============\n";

// 显示生成的键
$generatedKeys = [
    CacheKVFacade::makeKey('user', ['id' => 1]),
    CacheKVFacade::makeKey('product', ['id' => 1]),
    CacheKVFacade::makeKey('order', ['id' => 'ORD001']),
    CacheKVFacade::makeKey('cart', ['user_id' => 1])
];

echo "生成的缓存键:\n";
foreach ($generatedKeys as $key) {
    echo "  - {$key}\n";
}

// 键解析示例
$sampleKey = CacheKVFacade::makeKey('user_profile', ['id' => 123]);
$parsed = CacheKVFacade::getInstance()->getKeyManager()->parse($sampleKey);

echo "\n键解析示例:\n";
echo "  原始键: {$sampleKey}\n";
echo "  应用前缀: {$parsed['app_prefix']}\n";
echo "  环境前缀: {$parsed['env_prefix']}\n";
echo "  版本: {$parsed['version']}\n";
echo "  业务键: {$parsed['business_key']}\n\n";

echo "6. 缓存统计\n";
echo "===========\n";

$stats = CacheKVFacade::getStats();
echo "缓存统计信息:\n";
echo "  命中次数: {$stats['hits']}\n";
echo "  未命中次数: {$stats['misses']}\n";
echo "  命中率: {$stats['hit_rate']}%\n\n";

echo "=== 示例完成 ===\n";
echo "\n💡 提示:\n";
echo "  - 查看 examples/ 目录了解更多专项示例\n";
echo "  - 查看 docs/ 目录了解详细文档\n";
echo "  - 运行 test-project-integration.php 进行完整测试\n";
