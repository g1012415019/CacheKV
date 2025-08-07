<?php

/**
 * 分组配置文件使用示例
 * 
 * 演示如何使用独立的分组配置文件
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

echo "=== 分组配置文件使用示例 ===\n\n";

try {
    // 配置 CacheKV（API 不变）
    CacheKVFactory::configure(
        function() {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            return $redis;
        },
        __DIR__ . '/config/cache_kv.php'  // 主配置文件路径不变
    );
    
    echo "✅ 配置加载成功！\n";
    echo "自动加载了以下分组配置：\n";
    echo "- kvconf/user.php -> user 分组\n";
    echo "- kvconf/goods.php -> goods 分组\n";
    echo "- kvconf/article.php -> article 分组\n\n";
    
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
    
    function getArticleFromDatabase($articleId) {
        return [
            'id' => $articleId,
            'title' => "文章标题 {$articleId}",
            'content' => "这是文章 {$articleId} 的内容..."
        ];
    }
    
    // 测试用户模块缓存
    echo "=== 测试用户模块缓存 ===\n";
    $user = cache_kv_get('user.profile', ['id' => 123], function() {
        echo "从数据库获取用户资料...\n";
        return getUserFromDatabase(123);
    });
    echo "用户: {$user['name']} ({$user['email']})\n\n";
    
    // 测试商品模块缓存
    echo "=== 测试商品模块缓存 ===\n";
    $goods = cache_kv_get('goods.info', ['id' => 456], function() {
        echo "从数据库获取商品信息...\n";
        return getGoodsFromDatabase(456);
    });
    echo "商品: {$goods['name']} - ¥{$goods['price']}\n\n";
    
    // 测试文章模块缓存
    echo "=== 测试文章模块缓存 ===\n";
    $article = cache_kv_get('article.content', ['id' => 789], function() {
        echo "从数据库获取文章内容...\n";
        return getArticleFromDatabase(789);
    });
    echo "文章: {$article['title']}\n";
    echo "内容: {$article['content']}\n\n";
    
    // 测试批量获取
    echo "=== 测试批量获取 ===\n";
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
    
    // 测试键生成
    echo "=== 测试键生成 ===\n";
    $keyCollection = cache_kv_make_keys('goods.price', [
        ['id' => 100], ['id' => 101], ['id' => 102]
    ]);
    
    echo "生成的商品价格键:\n";
    foreach ($keyCollection->toStrings() as $keyString) {
        echo "- {$keyString}\n";
    }
    echo "\n";
    
    // 查看统计信息
    echo "=== 统计信息 ===\n";
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
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
