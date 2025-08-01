<?php
/**
 * CacheKV 配置方式示例
 * 
 * 展示简洁的配置方式
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;

echo "=== CacheKV 配置方式示例 ===\n\n";

echo "方式1: 零配置使用（最简单）\n";
echo str_repeat("-", 40) . "\n";

// 无需任何配置，直接使用
$user = cache_kv_get('user', ['id' => 1], function() {
    return ['id' => 1, 'name' => 'Zero Config User'];
});

echo "✓ 零配置使用成功\n";
echo "用户: {$user['name']}\n\n";

echo "方式2: 全局配置（推荐）\n";
echo str_repeat("-", 40) . "\n";

// 一次配置，全局使用
cache_kv_config([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'ttl' => 3600,
    'templates' => [
        'user' => 'user:{id}',
        'product' => 'product:{id}',
        'order' => 'order:{user_id}:{id}',
        'weather' => 'weather:{city}',
        'calculation' => 'calculation:{key}',
    ]
]);

$product = cache_kv_get('product', ['id' => 1], function() {
    return ['id' => 1, 'name' => 'Configured Product'];
});

echo "✓ 全局配置使用成功\n";
echo "商品: {$product['name']}\n\n";

echo "方式3: 快速创建独立实例\n";
echo str_repeat("-", 40) . "\n";

// 需要多个实例时使用
$userCache = CacheKVFactory::quick([
    'profile' => 'profile:{id}',
    'settings' => 'settings:{id}',
], [
    'app_prefix' => 'user-service',
    'ttl' => 1800
]);

$orderCache = CacheKVFactory::quick([
    'order' => 'order:{id}',
    'items' => 'items:{order_id}',
], [
    'app_prefix' => 'order-service',
    'ttl' => 3600
]);

$profile = $userCache->getByTemplate('profile', ['id' => 1], function() {
    return ['id' => 1, 'name' => 'Service User'];
});

$order = $orderCache->getByTemplate('order', ['id' => 1], function() {
    return ['id' => 1, 'total' => 99.99];
});

echo "✓ 多实例创建成功\n";
echo "用户服务缓存键: " . $userCache->makeKey('profile', ['id' => 1]) . "\n";
echo "订单服务缓存键: " . $orderCache->makeKey('order', ['id' => 1]) . "\n\n";

echo "方式4: 框架集成\n";
echo str_repeat("-", 40) . "\n";

// Laravel 等框架中的使用
class AppServiceProvider {
    public function boot() {
        // 在应用启动时配置
        cache_kv_config([
            'app_prefix' => env('APP_NAME', 'laravel'),
            'env_prefix' => env('APP_ENV', 'production'),
            'templates' => config('cache.templates', [])
        ]);
    }
}

echo "✓ 框架集成示例\n";
echo "// 在 AppServiceProvider 中配置\n";
echo "// 然后在任何地方直接使用 cache_kv_get()\n\n";

echo "=== 实际使用场景 ===\n\n";

echo "场景1: 用户信息缓存\n";
echo str_repeat("-", 30) . "\n";

function getUserInfo($userId) {
    return cache_kv_get('user', ['id' => $userId], function() use ($userId) {
        // 模拟数据库查询
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com"
        ];
    });
}

$user = getUserInfo(123);
echo "用户信息: {$user['name']} ({$user['email']})\n\n";

echo "场景2: API 响应缓存\n";
echo str_repeat("-", 30) . "\n";

function getWeather($city) {
    return cache_kv_get('weather', ['city' => $city], function() use ($city) {
        // 模拟 API 调用
        return [
            'city' => $city,
            'temperature' => rand(15, 35),
            'condition' => 'sunny'
        ];
    }, 1800); // 30分钟缓存
}

$weather = getWeather('Beijing');
echo "天气信息: {$weather['city']} {$weather['temperature']}°C {$weather['condition']}\n\n";

echo "场景3: 计算结果缓存\n";
echo str_repeat("-", 30) . "\n";

function getExpensiveCalculation($params) {
    $key = md5(json_encode($params));
    return cache_kv_get('calculation', ['key' => $key], function() use ($params) {
        // 模拟复杂计算
        sleep(1); // 假设需要1秒计算时间
        return array_sum($params) * 1.5;
    }, 3600); // 1小时缓存
}

$result = getExpensiveCalculation([1, 2, 3, 4, 5]);
echo "计算结果: {$result}\n\n";

echo "=== 配置对比总结 ===\n\n";

echo "使用建议:\n";
echo "1. 简单项目: 零配置直接使用\n";
echo "2. 正式项目: 使用 cache_kv_config() 全局配置\n";
echo "3. 微服务: 使用 CacheKVFactory::quick() 创建独立实例\n";
echo "4. 框架集成: 在服务提供者中配置\n\n";

echo "优势:\n";
echo "✓ 零学习成本 - 直接使用\n";
echo "✓ 一行配置 - 全局生效\n";
echo "✓ 无重复代码 - 辅助函数封装\n";
echo "✓ 灵活扩展 - 支持多实例\n\n";

echo "=== 示例完成 ===\n";
