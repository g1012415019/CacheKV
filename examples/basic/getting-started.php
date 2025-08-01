<?php
/**
 * CacheKV 快速入门示例
 * 
 * 展示最简洁的使用方式
 */

require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== CacheKV 快速入门示例 ===\n\n";

// 方式1: 零配置使用（最简单）
echo "1. 零配置使用\n";
echo str_repeat("-", 30) . "\n";

// 直接使用，无需任何配置
$user = cache_kv_get('user', ['id' => 123], function() {
    echo "从数据库获取用户 123... ";
    return [
        'id' => 123,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
});

echo "✓\n";
echo "用户信息: " . json_encode($user) . "\n\n";

// 方式2: 简单配置（推荐）
echo "2. 简单配置\n";
echo str_repeat("-", 30) . "\n";

// 一行配置，定义模板
cache_kv_config([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'templates' => [
        'user' => 'user:{id}',
        'product' => 'product:{id}',
        'post' => 'post:{id}',
    ]
]);

$product = cache_kv_get('product', ['id' => 456], function() {
    echo "从数据库获取商品 456... ";
    return [
        'id' => 456,
        'name' => 'iPhone 15',
        'price' => 999.99
    ];
});

echo "✓\n";
echo "商品信息: " . json_encode($product) . "\n\n";

// 方式3: 快速创建独立实例
echo "3. 快速创建独立实例\n";
echo str_repeat("-", 30) . "\n";

use Asfop\CacheKV\CacheKVFactory;

$cache = CacheKVFactory::quick([
    'user' => 'user:{id}',
    'order' => 'order:{id}',
], [
    'app_prefix' => 'shop',
    'ttl' => 1800
]);

$order = $cache->getByTemplate('order', ['id' => 789], function() {
    echo "从数据库获取订单 789... ";
    return [
        'id' => 789,
        'user_id' => 123,
        'total' => 999.99,
        'status' => 'paid'
    ];
});

echo "✓\n";
echo "订单信息: " . json_encode($order) . "\n\n";

echo "=== 核心功能演示 ===\n\n";

// 自动回填缓存
echo "1. 自动回填缓存\n";
$userId = 999;

// 第一次调用 - 执行回调
$user1 = cache_kv_get('user', ['id' => $userId], function() use ($userId) {
    echo "首次查询，从数据库获取用户 {$userId}... ";
    return ['id' => $userId, 'name' => "User {$userId}"];
});
echo "✓\n";

// 第二次调用 - 从缓存获取
echo "再次查询，从缓存获取... ";
$user2 = cache_kv_get('user', ['id' => $userId]);
echo "✓\n\n";

// 缓存操作
echo "2. 缓存操作\n";
cache_kv_set('user', ['id' => 888], ['id' => 888, 'name' => 'Cached User']);
echo "设置缓存: ✓\n";

$cachedUser = cache_kv_get('user', ['id' => 888]);
echo "获取缓存: " . $cachedUser['name'] . "\n";

cache_kv_delete('user', ['id' => 888]);
echo "删除缓存: ✓\n\n";

// 批量操作
echo "3. 批量操作\n";
$cache = cache_kv_instance();

$userIds = [1, 2, 3];
$userKeys = array_map(function($id) use ($cache) {
    return $cache->makeKey('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    echo "批量从数据库获取用户... ";
    $result = [];
    foreach ($missingKeys as $key) {
        if (preg_match('/user:(\d+)$/', $key, $matches)) {
            $id = (int)$matches[1];
            $result[$key] = ['id' => $id, 'name' => "User {$id}"];
        }
    }
    return $result;
});

echo "✓\n";
echo "获取到 " . count($users) . " 个用户\n\n";

echo "=== 使用方式对比 ===\n\n";

echo "推荐使用方式:\n";
echo "1. 零配置: 直接使用 cache_kv_get() - 最简单\n";
echo "2. 简单配置: cache_kv_config() + cache_kv_get() - 推荐\n";
echo "3. 独立实例: CacheKVFactory::quick() - 需要多实例时\n\n";

echo "=== 示例完成 ===\n";
