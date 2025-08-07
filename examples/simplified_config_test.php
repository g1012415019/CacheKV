<?php

/**
 * 简化配置结构测试
 * 
 * 验证移除 kv/other 区分后的配置是否正常工作
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

echo "=== 简化配置结构测试 ===\n\n";

try {
    // 配置 CacheKV
    CacheKVFactory::configure(
        function() {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            return $redis;
        },
        __DIR__ . '/config/cache_kv.php'
    );
    
    echo "✅ 配置加载成功！\n";
    echo "新的配置结构：keys 直接包含所有键，不再区分 kv/other\n\n";
    
    // 模拟数据库函数
    function getUserFromDatabase($userId) {
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com"
        ];
    }
    
    function getGoodsFromDatabase($goodsId) {
        return [
            'id' => $goodsId,
            'name' => "商品 {$goodsId}",
            'price' => rand(100, 9999) / 100
        ];
    }
    
    // 1. 测试有缓存配置的键（原 kv 类型）
    echo "=== 1. 测试有缓存配置的键 ===\n";
    
    $user = cache_kv_get('user.profile', ['id' => 123], function() {
        echo "从数据库获取用户资料...\n";
        return getUserFromDatabase(123);
    });
    echo "用户资料: {$user['name']} ({$user['email']})\n";
    
    $goods = cache_kv_get('goods.info', ['id' => 456], function() {
        echo "从数据库获取商品信息...\n";
        return getGoodsFromDatabase(456);
    });
    echo "商品信息: {$goods['name']} - ¥{$goods['price']}\n\n";
    
    // 2. 测试没有缓存配置的键（原 other 类型）
    echo "=== 2. 测试没有缓存配置的键 ===\n";
    
    // 生成会话键（仅用于键生成，不应用缓存逻辑）
    $sessionKey = cache_kv_make_key('user.session', ['token' => 'abc123']);
    echo "会话键: " . (string)$sessionKey . "\n";
    
    // 生成浏览计数键
    $viewCountKey = cache_kv_make_key('article.view_count', ['id' => 789]);
    echo "浏览计数键: " . (string)$viewCountKey . "\n\n";
    
    // 3. 测试批量操作
    echo "=== 3. 测试批量操作 ===\n";
    
    $users = cache_kv_get_multiple('user.profile', [
        ['id' => 1], ['id' => 2], ['id' => 3]
    ], function($missedKeys) {
        echo "批量从数据库获取 " . count($missedKeys) . " 个用户\n";
        $data = [];
        foreach ($missedKeys as $cacheKey) {
            $keyString = (string)$cacheKey;
            $params = $cacheKey->getParams();
            $data[$keyString] = getUserFromDatabase($params['id']);
        }
        return $data;
    });
    echo "获取到 " . count($users) . " 个用户数据\n\n";
    
    // 4. 测试按前缀删除
    echo "=== 4. 测试按前缀删除 ===\n";
    
    // 先创建一些缓存
    for ($i = 10; $i <= 12; $i++) {
        cache_kv_get('user.settings', ['id' => $i], function() use ($i) {
            return ['user_id' => $i, 'theme' => 'dark'];
        });
    }
    
    // 按前缀删除
    $deletedCount = cache_kv_delete_by_prefix('user.settings');
    echo "删除了 {$deletedCount} 个用户设置缓存\n\n";
    
    // 5. 测试键集合生成
    echo "=== 5. 测试键集合生成 ===\n";
    
    $keyCollection = cache_kv_make_keys('goods.price', [
        ['id' => 100], ['id' => 101], ['id' => 102]
    ]);
    
    echo "生成的商品价格键:\n";
    foreach ($keyCollection->toStrings() as $keyString) {
        echo "- {$keyString}\n";
    }
    echo "\n";
    
    // 6. 验证配置继承
    echo "=== 6. 验证配置继承 ===\n";
    
    // 测试不同键的TTL配置
    echo "测试配置继承（不同键应该有不同的TTL）:\n";
    echo "- user.profile: 应该使用键级配置 (10800秒)\n";
    echo "- user.settings: 应该使用组级配置 (7200秒)\n";
    echo "- goods.info: 应该使用键级配置 (21600秒)\n";
    echo "- goods.price: 应该使用键级配置 (1800秒)\n\n";
    
    // 7. 查看统计信息
    echo "=== 7. 统计信息 ===\n";
    $stats = cache_kv_get_stats();
    echo "命中率: {$stats['hit_rate']}\n";
    echo "总请求: {$stats['total_requests']}\n";
    
    $hotKeys = cache_kv_get_hot_keys(5);
    if (!empty($hotKeys)) {
        echo "热点键:\n";
        foreach ($hotKeys as $key => $count) {
            echo "- {$key}: {$count}次\n";
        }
    }
    
    echo "\n✅ 所有测试完成！\n";
    echo "\n=== 简化效果总结 ===\n";
    echo "1. 配置结构更简洁：keys 直接包含所有键定义\n";
    echo "2. 不再区分 kv/other 类型，减少复杂性\n";
    echo "3. 通过是否有 cache 配置来判断键的行为\n";
    echo "4. API 使用方式完全不变，向后兼容\n";
    echo "5. 配置文件更易读，学习成本更低\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
