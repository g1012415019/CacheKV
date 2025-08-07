<?php

/**
 * 按前缀删除缓存示例
 * 
 * 演示如何使用前缀删除功能，相当于按 tag 删除
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

echo "=== 按前缀删除缓存示例 ===\n\n";

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
    
    echo "✅ 配置加载成功！\n\n";
    
    // 模拟数据库函数
    function getUserFromDatabase($userId) {
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com"
        ];
    }
    
    // 1. 先创建一些缓存数据
    echo "=== 1. 创建测试缓存数据 ===\n";
    
    // 创建用户设置缓存
    for ($i = 1; $i <= 5; $i++) {
        $settings = cache_kv_get('user.settings', ['id' => $i], function() use ($i) {
            echo "创建用户 {$i} 的设置缓存\n";
            return [
                'user_id' => $i,
                'theme' => 'dark',
                'language' => 'zh-CN',
                'notifications' => true
            ];
        });
    }
    
    // 创建用户资料缓存
    for ($i = 1; $i <= 3; $i++) {
        $profile = cache_kv_get('user.profile', ['id' => $i], function() use ($i) {
            echo "创建用户 {$i} 的资料缓存\n";
            return getUserFromDatabase($i);
        });
    }
    
    // 创建商品缓存
    for ($i = 100; $i <= 102; $i++) {
        $goods = cache_kv_get('goods.info', ['id' => $i], function() use ($i) {
            echo "创建商品 {$i} 的信息缓存\n";
            return [
                'id' => $i,
                'name' => "商品 {$i}",
                'price' => rand(100, 9999) / 100
            ];
        });
    }
    
    echo "\n";
    
    // 2. 查看当前的键
    echo "=== 2. 查看当前缓存键 ===\n";
    $keyCollection = cache_kv_make_keys('user.settings', [
        ['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]
    ]);
    
    echo "用户设置键:\n";
    foreach ($keyCollection->toStrings() as $key) {
        echo "- {$key}\n";
    }
    
    $profileKeys = cache_kv_make_keys('user.profile', [
        ['id' => 1], ['id' => 2], ['id' => 3]
    ]);
    
    echo "\n用户资料键:\n";
    foreach ($profileKeys->toStrings() as $key) {
        echo "- {$key}\n";
    }
    echo "\n";
    
    // 3. 按前缀删除 - 删除所有用户设置
    echo "=== 3. 按前缀删除用户设置 ===\n";
    $deletedCount = cache_kv_delete_by_prefix('user.settings');
    echo "删除了 {$deletedCount} 个用户设置缓存\n\n";
    
    // 4. 按前缀删除 - 删除特定用户的所有缓存
    echo "=== 4. 按前缀删除特定用户缓存 ===\n";
    // 这里演示删除用户ID为1的所有profile相关缓存
    $deletedCount = cache_kv_delete_by_prefix('user.profile', ['id' => 1]);
    echo "删除了用户1的 {$deletedCount} 个资料缓存\n\n";
    
    // 5. 使用完整前缀删除
    echo "=== 5. 使用完整前缀删除 ===\n";
    // 先获取一个键的完整前缀
    $sampleKey = cache_kv_make_key('user.profile', ['id' => 2]);
    $fullKey = (string)$sampleKey;
    echo "示例键: {$fullKey}\n";
    
    // 提取前缀（去掉最后的ID部分）
    $prefix = substr($fullKey, 0, strrpos($fullKey, ':') + 1);
    echo "提取的前缀: {$prefix}\n";
    
    $deletedCount = cache_kv_delete_by_full_prefix($prefix);
    echo "使用完整前缀删除了 {$deletedCount} 个缓存\n\n";
    
    // 6. 验证删除结果
    echo "=== 6. 验证删除结果 ===\n";
    
    // 尝试获取已删除的缓存
    $settings = cache_kv_get('user.settings', ['id' => 1], function() {
        echo "用户设置缓存已被删除，重新从数据库获取\n";
        return [
            'user_id' => 1,
            'theme' => 'light',  // 模拟数据变更
            'language' => 'en-US',
            'notifications' => false
        ];
    });
    
    echo "重新获取的用户1设置: " . json_encode($settings) . "\n";
    
    // 检查商品缓存是否还存在（应该还在）
    $goods = cache_kv_get('goods.info', ['id' => 100], function() {
        echo "商品缓存不应该被删除，但这里被调用了\n";
        return ['id' => 100, 'name' => '商品100', 'price' => 99.99];
    });
    
    echo "商品100信息: " . json_encode($goods) . "\n\n";
    
    // 7. 实际应用场景演示
    echo "=== 7. 实际应用场景 ===\n";
    
    echo "场景1: 用户注销时清空该用户所有缓存\n";
    $userId = 123;
    
    // 创建一些用户缓存
    cache_kv_get('user.profile', ['id' => $userId], function() use ($userId) {
        return getUserFromDatabase($userId);
    });
    cache_kv_get('user.settings', ['id' => $userId], function() use ($userId) {
        return ['user_id' => $userId, 'theme' => 'dark'];
    });
    
    // 用户注销，清空所有相关缓存
    $profileDeleted = cache_kv_delete_by_prefix('user.profile', ['id' => $userId]);
    $settingsDeleted = cache_kv_delete_by_prefix('user.settings', ['id' => $userId]);
    
    echo "用户 {$userId} 注销，清空缓存:\n";
    echo "- 资料缓存: {$profileDeleted} 个\n";
    echo "- 设置缓存: {$settingsDeleted} 个\n\n";
    
    echo "场景2: 商品分类变更时清空该分类所有商品缓存\n";
    $categoryId = 5;
    
    // 假设有商品分类相关的缓存键模板
    // 这里用 goods.info 演示，实际可能是 goods.by_category 等
    for ($i = 200; $i <= 205; $i++) {
        cache_kv_get('goods.info', ['id' => $i], function() use ($i) {
            return ['id' => $i, 'name' => "商品{$i}", 'category_id' => 5];
        });
    }
    
    // 分类变更，清空该分类所有商品缓存
    $deletedGoods = cache_kv_delete_by_prefix('goods.info');  // 删除所有商品缓存
    echo "商品分类变更，清空了 {$deletedGoods} 个商品缓存\n\n";
    
    echo "✅ 所有测试完成！\n";
    
    // 8. 性能提示
    echo "\n=== 性能提示 ===\n";
    echo "1. 按前缀删除使用 Redis SCAN 命令，对大数据量友好\n";
    echo "2. 删除操作是批量进行的，性能较好\n";
    echo "3. 建议在业务逻辑变更时使用，如用户注销、数据更新等\n";
    echo "4. 可以结合定时任务进行缓存清理\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
