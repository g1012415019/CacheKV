<?php
/**
 * CacheKV 快速入门示例
 * 
 * 这个示例展示了 CacheKV 的基本使用方法
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheTemplates;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== CacheKV 快速入门示例 ===\n\n";

// 1. 配置 CacheKV
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'demo',
        'env_prefix' => 'dev',
        'version' => 'v1',
        'templates' => [
            CacheTemplates::USER => 'user:{id}',
            CacheTemplates::POST => 'post:{id}',
            CacheTemplates::API_RESPONSE => 'api:{endpoint}:{params_hash}',
        ]
    ]
]);

$cache = CacheKVFactory::store();

// 2. 基础缓存操作
echo "1. 基础缓存操作\n";
echo "设置缓存: ";
$cache->set('hello', 'world');
echo "✓\n";

echo "获取缓存: ";
$value = $cache->get('hello');
echo $value . "\n";

echo "检查缓存: ";
echo $cache->has('hello') ? '存在' : '不存在';
echo "\n\n";

// 3. 自动回填缓存（核心功能）
echo "2. 自动回填缓存\n";
$userId = 123;

$user = $cache->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
    echo "从数据库获取用户 {$userId}... ";
    // 模拟数据库查询
    return [
        'id' => $userId,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'created_at' => date('Y-m-d H:i:s')
    ];
});

echo "✓\n";
echo "用户信息: " . json_encode($user, JSON_PRETTY_PRINT) . "\n";

// 再次获取，这次会从缓存获取
echo "再次获取用户（从缓存）: ";
$userFromCache = $cache->getByTemplate(CacheTemplates::USER, ['id' => $userId]);
echo "✓\n\n";

// 4. 批量操作
echo "3. 批量操作\n";
$userIds = [1, 2, 3];
$userKeys = [];
foreach ($userIds as $id) {
    $userKeys[] = $cache->makeKey(CacheTemplates::USER, ['id' => $id]);
}

// 预设一个用户缓存
$cache->setByTemplate(CacheTemplates::USER, ['id' => 1], [
    'id' => 1,
    'name' => 'Alice',
    'email' => 'alice@example.com'
]);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    echo "批量从数据库获取缺失的用户... ";
    $result = [];
    foreach ($missingKeys as $key) {
        // 从键中提取用户ID（简化处理）
        if (preg_match('/user:(\d+)$/', $key, $matches)) {
            $id = (int)$matches[1];
            $result[$key] = [
                'id' => $id,
                'name' => "User {$id}",
                'email' => "user{$id}@example.com"
            ];
        }
    }
    return $result;
});

echo "✓\n";
echo "获取到 " . count($users) . " 个用户\n\n";

// 5. 标签管理
echo "4. 标签管理\n";
$cache->setWithTag('user:active:1', ['name' => 'Active User 1'], ['users', 'active']);
$cache->setWithTag('user:active:2', ['name' => 'Active User 2'], ['users', 'active']);
$cache->setWithTag('user:inactive:1', ['name' => 'Inactive User 1'], ['users', 'inactive']);

echo "设置带标签的缓存: ✓\n";

// 清除所有活跃用户缓存
$cache->clearTag('active');
echo "清除 'active' 标签的缓存: ✓\n";

echo "检查缓存状态:\n";
echo "- user:active:1: " . ($cache->has('user:active:1') ? '存在' : '已清除') . "\n";
echo "- user:active:2: " . ($cache->has('user:active:2') ? '存在' : '已清除') . "\n";
echo "- user:inactive:1: " . ($cache->has('user:inactive:1') ? '存在' : '已清除') . "\n\n";

// 6. 使用辅助函数
echo "5. 使用辅助函数\n";
$postId = 456;

$post = cache_kv_get(CacheTemplates::POST, ['id' => $postId], function() use ($postId) {
    echo "从数据库获取文章 {$postId}... ";
    return [
        'id' => $postId,
        'title' => 'CacheKV 使用指南',
        'content' => '这是一篇关于 CacheKV 的文章...',
        'author' => 'CacheKV Team'
    ];
});

echo "✓\n";
echo "文章标题: " . $post['title'] . "\n\n";

// 7. 缓存统计
echo "6. 缓存统计\n";
$stats = $cache->getStats();
echo "缓存统计: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 示例完成 ===\n";
