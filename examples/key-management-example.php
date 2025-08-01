<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;

echo "=== CacheKV Key 管理使用示例 ===\n\n";

// 使用工厂模式创建缓存实例
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
        'version' => 'v2',
        'separator' => ':',
        'templates' => [
            // 自定义模板
            'order' => 'order:{id}',
            'order_items' => 'order:items:{order_id}',
            'cart' => 'cart:{user_id}',
            'wishlist' => 'wishlist:{user_id}:page:{page}',
        ]
    ]
]);

// 获取缓存实例
$cache = cache_kv();

echo "1. 基本键生成示例：\n";

// 生成订单键
$orderKey = $cache->getKeyManager()->make('order', ['id' => 12345]);
echo "订单键: {$orderKey}\n";

// 生成订单项键
$orderItemsKey = $cache->getKeyManager()->make('order_items', ['order_id' => 12345]);
echo "订单项键: {$orderItemsKey}\n";

// 生成购物车键
$cartKey = $cache->getKeyManager()->make('cart', ['user_id' => 789]);
echo "购物车键: {$cartKey}\n";

// 生成愿望清单键（多参数）
$wishlistKey = $cache->getKeyManager()->make('wishlist', ['user_id' => 789, 'page' => 2]);
echo "愿望清单键: {$wishlistKey}\n\n";

echo "2. 使用模板进行缓存操作：\n";

// 使用辅助函数进行缓存操作
$orderData = cache_kv_get('order', ['id' => 12345], function() {
    echo "  -> 从数据库获取订单 12345\n";
    return [
        'id' => 12345,
        'user_id' => 789,
        'total' => 299.99,
        'status' => 'completed',
        'items' => ['item1', 'item2']
    ];
});

echo "订单数据: " . json_encode($orderData, JSON_UNESCAPED_UNICODE) . "\n\n";

// 获取订单项
$orderItems = cache_kv_get('order_items', ['order_id' => 12345], function() {
    echo "  -> 从数据库获取订单项\n";
    return [
        ['id' => 1, 'name' => 'iPhone 15', 'price' => 999],
        ['id' => 2, 'name' => 'AirPods', 'price' => 199]
    ];
});

echo "订单项: " . json_encode($orderItems, JSON_UNESCAPED_UNICODE) . "\n\n";

// 获取购物车
$cartData = cache_kv_get('cart', ['user_id' => 789], function() {
    echo "  -> 从数据库获取购物车\n";
    return [
        'user_id' => 789,
        'items' => [
            ['id' => 3, 'name' => 'MacBook Pro', 'price' => 1999]
        ],
        'total' => 1999
    ];
});

echo "购物车: " . json_encode($cartData, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "3. 键管理高级功能：\n";

// 批量键生成
$userIds = [100, 200, 300];
$orderKeys = [];
foreach ($userIds as $userId) {
    $orderKeys[] = $cache->getKeyManager()->make('order', ['id' => $userId]);
}

echo "批量订单键: " . implode(', ', $orderKeys) . "\n";

// 键解析（展示键的结构）
$keyManager = $cache->getKeyManager();
$sampleKey = $keyManager->make('user', ['id' => 1]);
echo "示例键结构: {$sampleKey}\n";

// 模板验证
$templates = ['order', 'cart', 'wishlist', 'order_items'];
echo "可用模板: " . implode(', ', $templates) . "\n\n";

echo "4. 实际业务场景：\n";

// 电商订单管理
class OrderService {
    public function getOrder($orderId) {
        return cache_kv_get('order', ['id' => $orderId], function() use ($orderId) {
            echo "  -> 从数据库查询订单 {$orderId}\n";
            return [
                'id' => $orderId,
                'status' => 'processing',
                'total' => rand(100, 1000)
            ];
        });
    }
    
    public function getOrderItems($orderId) {
        return cache_kv_get('order_items', ['order_id' => $orderId], function() use ($orderId) {
            echo "  -> 从数据库查询订单项 {$orderId}\n";
            return [
                ['name' => 'Product A', 'qty' => 2],
                ['name' => 'Product B', 'qty' => 1]
            ];
        });
    }
    
    public function updateOrder($orderId, $data) {
        echo "  -> 更新订单 {$orderId}\n";
        // 清除相关缓存
        cache_kv_forget('order', ['id' => $orderId]);
        cache_kv_forget('order_items', ['order_id' => $orderId]);
        echo "  -> 清除订单相关缓存\n";
    }
}

$orderService = new OrderService();

// 获取订单
$order = $orderService->getOrder(999);
echo "订单信息: " . json_encode($order, JSON_UNESCAPED_UNICODE) . "\n";

// 获取订单项
$items = $orderService->getOrderItems(999);
echo "订单项: " . json_encode($items, JSON_UNESCAPED_UNICODE) . "\n";

// 更新订单（会清除缓存）
$orderService->updateOrder(999, ['status' => 'shipped']);

// 再次获取（会重新查询数据库）
$updatedOrder = $orderService->getOrder(999);
echo "更新后订单: " . json_encode($updatedOrder, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== Key 管理示例完成 ===\n";
echo "✅ 使用工厂模式，无需手动创建 KeyManager\n";
echo "✅ 支持复杂的键模板配置\n";
echo "✅ 提供辅助函数，使用更简洁\n";
echo "✅ 适合复杂的业务场景\n";
