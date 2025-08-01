<?php
/**
 * CacheKV 快速入门示例
 * 
 * 展示最简洁的使用方式（无业务相关硬编码）
 */

require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== CacheKV 快速入门示例 ===\n\n";

// 方式1: 零配置使用（最简单）
echo "1. 零配置使用\n";
echo str_repeat("-", 30) . "\n";

// 直接使用，无需任何配置
$data = cache_kv_get('data_item', ['id' => 123], function() {
    echo "从数据源获取数据 123... ";
    return [
        'id' => 123,
        'name' => 'Sample Data',
        'value' => 'sample_value'
    ];
});

echo "✓\n";
echo "数据信息: " . json_encode($data) . "\n\n";

// 方式2: 简单配置（推荐）
echo "2. 简单配置\n";
echo str_repeat("-", 30) . "\n";

// 一行配置，定义模板
cache_kv_config([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'templates' => [
        'item' => 'item:{id}',
        'record' => 'record:{type}:{id}',
        'content' => 'content:{category}:{id}',
    ]
]);

$item = cache_kv_get('item', ['id' => 456], function() {
    echo "从数据源获取项目 456... ";
    return [
        'id' => 456,
        'title' => 'Sample Item',
        'status' => 'active'
    ];
});

echo "✓\n";
echo "项目信息: " . json_encode($item) . "\n\n";

// 方式3: 快速创建独立实例
echo "3. 快速创建独立实例\n";
echo str_repeat("-", 30) . "\n";

use Asfop\CacheKV\CacheKVFactory;

$cache = CacheKVFactory::quick([
    'entity' => 'entity:{id}',
    'relation' => 'relation:{from}:{to}',
], [
    'app_prefix' => 'service',
    'ttl' => 1800
]);

$entity = $cache->getByTemplate('entity', ['id' => 789], function() {
    echo "从数据源获取实体 789... ";
    return [
        'id' => 789,
        'type' => 'sample_entity',
        'data' => ['key' => 'value']
    ];
});

echo "✓\n";
echo "实体信息: " . json_encode($entity) . "\n\n";

echo "=== 核心功能演示 ===\n\n";

// 自动回填缓存
echo "1. 自动回填缓存\n";
$itemId = 999;

// 第一次调用 - 执行回调
$item1 = cache_kv_get('item', ['id' => $itemId], function() use ($itemId) {
    echo "首次查询，从数据源获取项目 {$itemId}... ";
    return ['id' => $itemId, 'name' => "Item {$itemId}"];
});
echo "✓\n";

// 第二次调用 - 从缓存获取
echo "再次查询，从缓存获取... ";
$item2 = cache_kv_get('item', ['id' => $itemId]);
echo "✓\n\n";

// 缓存操作
echo "2. 缓存操作\n";
cache_kv_set('item', ['id' => 888], ['id' => 888, 'name' => 'Cached Item']);
echo "设置缓存: ✓\n";

$cachedItem = cache_kv_get('item', ['id' => 888]);
echo "获取缓存: " . $cachedItem['name'] . "\n";

cache_kv_delete('item', ['id' => 888]);
echo "删除缓存: ✓\n\n";

// 批量操作
echo "3. 批量操作\n";
$cache = cache_kv_instance();

$itemIds = [1, 2, 3];
$itemKeys = array_map(function($id) use ($cache) {
    return $cache->makeKey('item', ['id' => $id]);
}, $itemIds);

$items = $cache->getMultiple($itemKeys, function($missingKeys) {
    echo "批量从数据源获取项目... ";
    $result = [];
    foreach ($missingKeys as $key) {
        if (preg_match('/item:(\d+)$/', $key, $matches)) {
            $id = (int)$matches[1];
            $result[$key] = ['id' => $id, 'name' => "Item {$id}"];
        }
    }
    return $result;
});

echo "✓\n";
echo "获取到 " . count($items) . " 个项目\n\n";

echo "=== 使用方式对比 ===\n\n";

echo "推荐使用方式:\n";
echo "1. 零配置: 直接使用 cache_kv_get() - 最简单\n";
echo "2. 简单配置: cache_kv_config() + cache_kv_get() - 推荐\n";
echo "3. 独立实例: CacheKVFactory::quick() - 需要多实例时\n\n";

echo "=== 示例完成 ===\n";
