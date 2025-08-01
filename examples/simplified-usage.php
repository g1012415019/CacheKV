<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;

echo "=== CacheKV 简化使用示例 ===\n\n";

// 一次性配置（通常在应用启动时执行）
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 60
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'test',
        'env_prefix' => 'dev',
        'version' => 'v1',
        'templates' => [
            'user' => 'user:{id}',
            'product' => 'product:{id}',
            'session' => 'session:{id}',
            'perf_test' => 'perf_test:{id}',
        ]
    ]
]);

echo "1. 使用辅助函数（最简单）：\n";

// 现在可以在任何地方直接使用辅助函数
$user = cache_kv_get('user', ['id' => 123], function() {
    echo "  -> 从数据库获取用户 123\n";
    return ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
});

echo "用户信息: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n\n";

// 再次获取（从缓存）
$user2 = cache_kv_get('user', ['id' => 123], function() {
    echo "  -> 这行不应该出现\n";
    return [];
});

echo "再次获取（从缓存）: " . json_encode($user2, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "2. 使用工厂方法：\n";

$product = cache_kv()->getByTemplate('product', ['id' => 456], function() {
    echo "  -> 从API获取产品 456\n";
    return ['id' => 456, 'name' => 'iPhone 15', 'price' => 999];
});

echo "产品信息: " . json_encode($product, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "3. 快速创建独立实例：\n";

// 快速创建独立实例，不影响全局配置
$quickCache = cache_kv_quick('myapp', 'prod', [
    'user' => 'user:{id}',
    'order' => 'order:{id}',
]);

$order = $quickCache->getByTemplate('order', ['id' => 789], function() {
    echo "  -> 从数据库获取订单 789\n";
    return ['id' => 789, 'total' => 299.99, 'status' => 'completed'];
});

echo "订单信息: " . json_encode($order, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "4. 批量操作：\n";

$userIds = [1, 2, 3];
$userKeys = array_map(function($id) {
    return cache_kv()->getKeyManager()->make('user', ['id' => $id]);
}, $userIds);

$users = cache_kv()->getMultiple($userKeys, function($missingKeys) {
    echo "  -> 批量获取缺失的用户数据\n";
    $result = [];
    foreach ($missingKeys as $key) {
        // 从键中提取ID（简化示例）
        if (preg_match('/user:(\d+)/', $key, $matches)) {
            $id = $matches[1];
            $result[$key] = ['id' => $id, 'name' => "User {$id}"];
        }
    }
    return $result;
});

echo "批量用户信息: " . json_encode($users, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== 现在使用变得非常简单！ ===\n";
