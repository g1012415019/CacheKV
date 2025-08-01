<?php
/**
 * CacheKV 快速入门示例
 * 
 * 这个示例展示了 CacheKV 的基本使用方法和多种配置方式
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheKVBuilder;
use Asfop\CacheKV\CacheTemplates;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

echo "=== CacheKV 快速入门示例 ===\n\n";

// 方式1: 直接创建（推荐方式）
echo "1. 直接创建 CacheKV 实例\n";
echo str_repeat("-", 30) . "\n";

$driver = new ArrayDriver();
$keyManager = new KeyManager([
    'app_prefix' => 'demo',
    'env_prefix' => 'dev',
    'version' => 'v1',
    'templates' => [
        CacheTemplates::USER => 'user:{id}',
        CacheTemplates::POST => 'post:{id}',
        CacheTemplates::API_RESPONSE => 'api:{endpoint}:{params_hash}',
    ]
]);

$cache = CacheKVFactory::create($driver, 3600, $keyManager);

echo "✓ 使用 CacheKVFactory::create() 创建实例\n\n";

// 方式2: 使用配置数组创建
echo "2. 使用配置数组创建\n";
echo str_repeat("-", 30) . "\n";

$config = [
    'driver' => new ArrayDriver(),
    'ttl' => 3600,
    'key_manager' => [
        'app_prefix' => 'demo',
        'env_prefix' => 'dev',
        'version' => 'v1',
        'templates' => [
            CacheTemplates::USER => 'user:{id}',
            CacheTemplates::POST => 'post:{id}',
        ]
    ]
];

$cache2 = CacheKVFactory::createFromConfig($config);
echo "✓ 使用 CacheKVFactory::createFromConfig() 创建实例\n\n";

// 方式3: 使用构建器（流畅API）
echo "3. 使用构建器创建\n";
echo str_repeat("-", 30) . "\n";

$cache3 = CacheKVBuilder::create()
    ->useArrayDriver()
    ->ttl(3600)
    ->appPrefix('demo')
    ->envPrefix('dev')
    ->version('v1')
    ->template(CacheTemplates::USER, 'user:{id}')
    ->template(CacheTemplates::POST, 'post:{id}')
    ->build();

echo "✓ 使用 CacheKVBuilder 流畅API创建实例\n\n";

// 方式4: 快速创建（开发测试用）
echo "4. 快速创建\n";
echo str_repeat("-", 30) . "\n";

$cache4 = CacheKVFactory::quick('demo', 'dev', [
    CacheTemplates::USER => 'user:{id}',
    CacheTemplates::POST => 'post:{id}',
]);

echo "✓ 使用 CacheKVFactory::quick() 快速创建实例\n\n";

// 使用第一个缓存实例进行演示
echo "=== 功能演示 ===\n\n";

// 基础缓存操作
echo "1. 基础缓存操作\n";
$cache->set('hello', 'world');
echo "设置缓存: ✓\n";
echo "获取缓存: " . $cache->get('hello') . "\n";
echo "检查缓存: " . ($cache->has('hello') ? '存在' : '不存在') . "\n\n";

// 自动回填缓存（核心功能）
echo "2. 自动回填缓存\n";
$userId = 123;

$user = $cache->getByTemplate(CacheTemplates::USER, ['id' => $userId], function() use ($userId) {
    echo "从数据库获取用户 {$userId}... ";
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
echo "\n再次获取用户（从缓存）: ";
$userFromCache = $cache->getByTemplate(CacheTemplates::USER, ['id' => $userId]);
echo "✓\n\n";

// 批量操作
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

// 标签管理
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

// 使用辅助函数
echo "5. 使用辅助函数\n";
$postId = 456;

$post = cache_kv_get($cache, CacheTemplates::POST, ['id' => $postId], function() use ($postId) {
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

// 缓存统计
echo "6. 缓存统计\n";
$stats = $cache->getStats();
echo "缓存统计: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 配置方式对比 ===\n\n";

echo "推荐使用方式:\n";
echo "1. 生产环境: CacheKVFactory::create() - 最灵活，支持依赖注入\n";
echo "2. 配置驱动: CacheKVFactory::createFromConfig() - 适合配置文件\n";
echo "3. 流畅API: CacheKVBuilder - 代码可读性好\n";
echo "4. 快速开发: CacheKVFactory::quick() - 开发测试用\n\n";

echo "=== 示例完成 ===\n";
