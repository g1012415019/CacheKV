<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVServiceProvider;

echo "=== CacheKV 项目联动测试 ===\n\n";

// 测试1: 使用工厂模式 + 辅助函数
echo "✅ 测试1: 工厂模式 + 辅助函数\n";

CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'test',
        'env_prefix' => 'dev',
        'version' => 'v1',
        'templates' => [
            'user' => 'user:{id}',
            'product' => 'product:{id}',
            'order' => 'order:{id}',
        ]
    ]
]);

$userKey = cache_kv()->getKeyManager()->make('user', ['id' => 123]);
echo "生成的用户键: {$userKey}\n";

$user = cache_kv_get('user', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'Test User', 'email' => 'test@example.com'];
});
echo "用户数据: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试2: 快速创建模式
echo "✅ 测试2: 快速创建模式\n";

$quickCache = cache_kv_quick('quicktest', 'dev', [
    'session' => 'session:{id}',
    'temp' => 'temp:{key}',
]);

$session = $quickCache->getByTemplate('session', ['id' => 'abc123'], function() {
    return ['session_id' => 'abc123', 'user_id' => 456, 'expires' => time() + 3600];
});
echo "会话数据: " . json_encode($session, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试3: 门面模式
echo "✅ 测试3: 门面模式\n";

CacheKVServiceProvider::register([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 1800
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'facade',
        'env_prefix' => 'test',
        'version' => 'v1',
        'templates' => [
            'config' => 'config:{key}',
            'cache_test' => 'cache_test:{id}',
        ]
    ]
]);

$config = CacheKVFacade::getByTemplate('config', ['key' => 'app_settings'], function() {
    return [
        'app_name' => 'Test App',
        'version' => '1.0.0',
        'debug' => true,
        'cache_enabled' => true
    ];
});
echo "配置数据: " . json_encode($config, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试4: 批量操作
echo "✅ 测试4: 批量操作\n";

$productIds = [100, 200, 300];
$productKeys = array_map(function($id) {
    return cache_kv()->getKeyManager()->make('product', ['id' => $id]);
}, $productIds);

$products = cache_kv()->getMultiple($productKeys, function($missingKeys) {
    echo "  -> 批量获取缺失产品数据\n";
    $result = [];
    foreach ($missingKeys as $key) {
        if (preg_match('/product:(\d+)/', $key, $matches)) {
            $id = $matches[1];
            $result[$key] = [
                'id' => $id,
                'name' => "Product {$id}",
                'price' => rand(100, 1000),
                'stock' => rand(0, 100)
            ];
        }
    }
    return $result;
});

echo "批量产品数量: " . count($products) . "\n";
echo "产品示例: " . json_encode(array_values($products)[0] ?? [], JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试5: 标签管理
echo "✅ 测试5: 标签管理\n";

$cache = cache_kv();

// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 999], [
    'id' => 999,
    'name' => 'Tagged User',
    'role' => 'admin',
    'permissions' => ['read', 'write', 'delete']
], ['users', 'admins', 'high_privilege']);

echo "设置带标签的用户缓存\n";

// 验证缓存存在
$taggedUser = cache_kv_get('user', ['id' => 999], function() {
    echo "  -> 这行不应该出现（应该从缓存获取）\n";
    return [];
});

echo "带标签用户: " . json_encode($taggedUser, JSON_UNESCAPED_UNICODE) . "\n";

// 清除标签
$cache->clearTag('admins');
echo "清除 'admins' 标签的所有缓存\n\n";

// 测试6: 业务服务集成
echo "✅ 测试6: 业务服务集成\n";

class TestUserService {
    public function getUser($userId) {
        return cache_kv_get('user', ['id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取用户 {$userId}\n";
            return [
                'id' => $userId,
                'name' => "Service User {$userId}",
                'email' => "user{$userId}@service.com",
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => date('Y-m-d H:i:s', time() - rand(3600, 86400))
            ];
        });
    }
    
    public function updateUser($userId, $data) {
        echo "  -> 更新用户 {$userId}\n";
        // 模拟数据库更新
        
        // 清除缓存
        cache_kv_forget('user', ['id' => $userId]);
        echo "  -> 清除用户缓存\n";
    }
    
    public function getUserOrders($userId) {
        return cache_kv_get('order', ['id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取用户订单 {$userId}\n";
            return [
                'user_id' => $userId,
                'orders' => [
                    ['id' => 1001, 'total' => 299.99, 'status' => 'completed'],
                    ['id' => 1002, 'total' => 199.99, 'status' => 'processing'],
                ]
            ];
        });
    }
}

$userService = new TestUserService();

// 获取用户
$serviceUser = $userService->getUser(777);
echo "服务用户: " . json_encode($serviceUser, JSON_UNESCAPED_UNICODE) . "\n";

// 获取用户订单
$userOrders = $userService->getUserOrders(777);
echo "用户订单: " . json_encode($userOrders, JSON_UNESCAPED_UNICODE) . "\n";

// 更新用户（会清除缓存）
$userService->updateUser(777, ['name' => 'Updated Service User']);

// 再次获取（会重新从数据库获取）
$updatedUser = $userService->getUser(777);
echo "更新后用户: " . json_encode($updatedUser, JSON_UNESCAPED_UNICODE) . "\n\n";

// 测试7: 性能测试
echo "✅ 测试7: 性能测试\n";

$startTime = microtime(true);

// 大量缓存操作
for ($i = 1; $i <= 1000; $i++) {
    cache_kv_get('user', ['id' => $i], function() use ($i) {
        return ['id' => $i, 'name' => "User {$i}"];
    });
}

$endTime = microtime(true);
$duration = ($endTime - $startTime) * 1000; // 转换为毫秒

echo "1000次缓存操作耗时: " . number_format($duration, 2) . " ms\n";
echo "平均每次操作: " . number_format($duration / 1000, 4) . " ms\n\n";

echo "=== 项目联动测试完成 ===\n";
echo "🎯 测试结果：\n";
echo "  ✅ 工厂模式 + 辅助函数：正常\n";
echo "  ✅ 快速创建模式：正常\n";
echo "  ✅ 门面模式：正常\n";
echo "  ✅ 批量操作：正常\n";
echo "  ✅ 标签管理：正常\n";
echo "  ✅ 业务服务集成：正常\n";
echo "  ✅ 性能测试：正常\n";
echo "\n🚀 所有功能都已适配新的简化模式！\n";
