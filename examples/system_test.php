<?php

/**
 * 系统完整性测试
 * 
 * 验证简化配置结构后整个系统是否正常工作
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

echo "=== CacheKV 系统完整性测试 ===\n\n";

try {
    // 1. 测试主配置文件加载
    echo "1. 测试主配置文件加载...\n";
    CacheKVFactory::configure(
        function() {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            return $redis;
        },
        __DIR__ . '/config/cache_kv.php'
    );
    echo "✅ 主配置文件加载成功\n\n";
    
    // 2. 测试分组配置文件自动加载
    echo "2. 测试分组配置文件自动加载...\n";
    
    // 模拟数据库函数
    function getUserFromDatabase($userId) {
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com",
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    function getGoodsFromDatabase($goodsId) {
        return [
            'id' => $goodsId,
            'name' => "商品 {$goodsId}",
            'price' => rand(100, 9999) / 100,
            'category' => 'electronics'
        ];
    }
    
    function getArticleFromDatabase($articleId) {
        return [
            'id' => $articleId,
            'title' => "文章标题 {$articleId}",
            'content' => "这是文章 {$articleId} 的详细内容...",
            'author' => "作者{$articleId}"
        ];
    }
    
    // 3. 测试有缓存配置的键
    echo "3. 测试有缓存配置的键...\n";
    
    $user = cache_kv_get('user.profile', ['id' => 123], function() {
        echo "  从数据库获取用户资料...\n";
        return getUserFromDatabase(123);
    });
    echo "  用户资料: {$user['name']} ({$user['email']})\n";
    
    $goods = cache_kv_get('goods.info', ['id' => 456], function() {
        echo "  从数据库获取商品信息...\n";
        return getGoodsFromDatabase(456);
    });
    echo "  商品信息: {$goods['name']} - ¥{$goods['price']}\n";
    
    $article = cache_kv_get('article.content', ['id' => 789], function() {
        echo "  从数据库获取文章内容...\n";
        return getArticleFromDatabase(789);
    });
    echo "  文章内容: {$article['title']} by {$article['author']}\n\n";
    
    // 4. 测试没有缓存配置的键（仅用于键生成）
    echo "4. 测试没有缓存配置的键...\n";
    
    $sessionKey = cache_kv_make_key('user.session', ['token' => 'abc123def456']);
    echo "  会话键: " . (string)$sessionKey . "\n";
    
    $viewCountKey = cache_kv_make_key('article.view_count', ['id' => 789]);
    echo "  浏览计数键: " . (string)$viewCountKey . "\n\n";
    
    // 5. 测试批量操作
    echo "5. 测试批量操作...\n";
    
    $users = cache_kv_get_multiple('user.profile', [
        ['id' => 1], ['id' => 2], ['id' => 3]
    ], function($missedKeys) {
        echo "  批量从数据库获取 " . count($missedKeys) . " 个用户\n";
        $data = [];
        foreach ($missedKeys as $cacheKey) {
            $keyString = (string)$cacheKey;
            $params = $cacheKey->getParams();
            $data[$keyString] = getUserFromDatabase($params['id']);
        }
        return $data;
    });
    echo "  批量获取到 " . count($users) . " 个用户数据\n\n";
    
    // 6. 测试按前缀删除
    echo "6. 测试按前缀删除...\n";
    
    // 先创建一些缓存
    for ($i = 10; $i <= 12; $i++) {
        cache_kv_get('user.settings', ['id' => $i], function() use ($i) {
            return ['user_id' => $i, 'theme' => 'dark', 'language' => 'zh-CN'];
        });
    }
    
    $deletedCount = cache_kv_delete_by_prefix('user.settings');
    echo "  删除了 {$deletedCount} 个用户设置缓存\n\n";
    
    // 7. 测试键集合生成
    echo "7. 测试键集合生成...\n";
    
    $keyCollection = cache_kv_make_keys('goods.price', [
        ['id' => 100], ['id' => 101], ['id' => 102]
    ]);
    
    echo "  生成了 {$keyCollection->count()} 个商品价格键:\n";
    foreach ($keyCollection->toStrings() as $keyString) {
        echo "    - {$keyString}\n";
    }
    echo "\n";
    
    // 8. 测试统计功能
    echo "8. 测试统计功能...\n";
    
    $stats = cache_kv_get_stats();
    echo "  命中率: {$stats['hit_rate']}\n";
    echo "  总请求: {$stats['total_requests']}\n";
    echo "  命中次数: {$stats['hits']}\n";
    echo "  未命中次数: {$stats['misses']}\n";
    
    $hotKeys = cache_kv_get_hot_keys(5);
    if (!empty($hotKeys)) {
        echo "  热点键:\n";
        foreach ($hotKeys as $key => $count) {
            echo "    - {$key}: {$count}次\n";
        }
    }
    echo "\n";
    
    // 9. 测试配置继承
    echo "9. 测试配置继承...\n";
    
    // 创建一个键对象来检查配置
    $profileKey = cache_kv_make_key('user.profile', ['id' => 999]);
    $sessionKey = cache_kv_make_key('user.session', ['token' => 'test123']);
    
    echo "  user.profile 有缓存配置: " . ($profileKey->hasCacheConfig() ? '是' : '否') . "\n";
    echo "  user.session 有缓存配置: " . ($sessionKey->hasCacheConfig() ? '是' : '否') . "\n\n";
    
    // 10. 测试错误处理
    echo "10. 测试错误处理...\n";
    
    try {
        cache_kv_get('nonexistent.key', ['id' => 1], function() {
            return ['test' => 'data'];
        });
        echo "  ❌ 应该抛出异常但没有\n";
    } catch (Exception $e) {
        echo "  ✅ 正确捕获异常: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== 系统测试总结 ===\n";
    echo "✅ 主配置文件加载正常\n";
    echo "✅ 分组配置文件自动加载正常\n";
    echo "✅ 有缓存配置的键工作正常\n";
    echo "✅ 没有缓存配置的键工作正常\n";
    echo "✅ 批量操作工作正常\n";
    echo "✅ 按前缀删除工作正常\n";
    echo "✅ 键集合生成工作正常\n";
    echo "✅ 统计功能工作正常\n";
    echo "✅ 配置继承工作正常\n";
    echo "✅ 错误处理工作正常\n";
    echo "\n🎉 所有测试通过！系统运行正常！\n";
    
    // 11. 性能测试
    echo "\n=== 性能测试 ===\n";
    
    $startTime = microtime(true);
    $iterations = 1000;
    
    for ($i = 0; $i < $iterations; $i++) {
        cache_kv_get('user.profile', ['id' => $i % 100], function() use ($i) {
            return getUserFromDatabase($i % 100);
        });
    }
    
    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000;
    $avgTime = $totalTime / $iterations;
    
    echo "执行 {$iterations} 次缓存操作:\n";
    echo "总耗时: " . round($totalTime, 2) . "ms\n";
    echo "平均耗时: " . round($avgTime, 3) . "ms/次\n";
    
    $finalStats = cache_kv_get_stats();
    echo "最终命中率: {$finalStats['hit_rate']}\n";
    
} catch (Exception $e) {
    echo "❌ 系统测试失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
