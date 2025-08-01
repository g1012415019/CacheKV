<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

echo "=== CacheKV Key 管理快速入门 ===\n\n";

// 1. 创建 KeyManager
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'dev',
    'version' => 'v1'
]);

// 2. 创建缓存实例
$cache = new CacheKV(new ArrayDriver(), 3600);

echo "1. 基本用法\n";
echo "----------\n";

// 生成标准化的缓存键
$userKey = $keyManager->make('user', ['id' => 123]);
echo "用户键: {$userKey}\n";

// 在缓存中使用
$user = $cache->get($userKey, function() {
    return ['id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com'];
});
echo "用户数据: " . json_encode($user) . "\n\n";

echo "2. 批量操作\n";
echo "----------\n";

// 批量生成键
$userIds = [101, 102, 103];
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

// 批量获取数据
$users = $cache->getMultiple($userKeys, function($missingKeys) use ($keyManager) {
    $data = [];
    foreach ($missingKeys as $key) {
        // 从键中解析出 ID
        $parsed = $keyManager->parse($key);
        $userId = explode(':', $parsed['business_key'])[1];
        
        $data[$key] = [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com"
        ];
    }
    return $data;
});

echo "批量获取了 " . count($users) . " 个用户\n\n";

echo "3. 自定义模板\n";
echo "------------\n";

// 添加业务特定的模板
$keyManager->addTemplate('order', 'order:{id}');
$keyManager->addTemplate('cart', 'cart:{user_id}');

$orderKey = $keyManager->make('order', ['id' => 'ORD001']);
$cartKey = $keyManager->make('cart', ['user_id' => 123]);

echo "订单键: {$orderKey}\n";
echo "购物车键: {$cartKey}\n\n";

echo "4. 键解析和验证\n";
echo "--------------\n";

$parsed = $keyManager->parse($userKey);
echo "解析 '{$userKey}':\n";
echo "  应用: {$parsed['app_prefix']}\n";
echo "  环境: {$parsed['env_prefix']}\n";
echo "  版本: {$parsed['version']}\n";
echo "  业务键: {$parsed['business_key']}\n\n";

echo "5. 模式匹配（用于批量清理）\n";
echo "-------------------------\n";

$userPattern = $keyManager->pattern('user', ['id' => '*']);
echo "所有用户键模式: {$userPattern}\n";

$specificUserPattern = $keyManager->pattern('user_profile', ['id' => 123]);
echo "特定用户资料键: {$specificUserPattern}\n\n";

echo "=== 快速入门完成 ===\n";
echo "\n💡 提示：查看 key-management-example.php 了解更多高级功能！\n";
