<?php
/**
 * CacheKV 配置方式示例
 * 
 * 展示简洁的配置方式（无业务相关硬编码）
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;

echo "=== CacheKV 配置方式示例 ===\n\n";

echo "方式1: 零配置使用（最简单）\n";
echo str_repeat("-", 40) . "\n";

// 无需任何配置，直接使用
$data = cache_kv_get('data_item', ['id' => 1], function() {
    return ['id' => 1, 'name' => 'Zero Config Data'];
});

echo "✓ 零配置使用成功\n";
echo "数据: {$data['name']}\n\n";

echo "方式2: 全局配置（推荐）\n";
echo str_repeat("-", 40) . "\n";

// 一次配置，全局使用
cache_kv_config([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'ttl' => 3600,
    'templates' => [
        'item' => 'item:{id}',
        'record' => 'record:{type}:{id}',
        'content' => 'content:{category}:{id}',
        'api_result' => 'api:{endpoint}:{params_hash}',
        'calculation' => 'calc:{key}',
    ]
]);

$item = cache_kv_get('item', ['id' => 1], function() {
    return ['id' => 1, 'name' => 'Configured Item'];
});

echo "✓ 全局配置使用成功\n";
echo "项目: {$item['name']}\n\n";

echo "方式3: 快速创建独立实例\n";
echo str_repeat("-", 40) . "\n";

// 需要多个实例时使用
$serviceACache = CacheKVFactory::quick([
    'entity' => 'entity:{id}',
    'relation' => 'relation:{from}:{to}',
], [
    'app_prefix' => 'service-a',
    'ttl' => 1800
]);

$serviceBCache = CacheKVFactory::quick([
    'resource' => 'resource:{id}',
    'metadata' => 'meta:{resource_id}',
], [
    'app_prefix' => 'service-b',
    'ttl' => 3600
]);

$entity = $serviceACache->getByTemplate('entity', ['id' => 1], function() {
    return ['id' => 1, 'name' => 'Service A Entity'];
});

$resource = $serviceBCache->getByTemplate('resource', ['id' => 1], function() {
    return ['id' => 1, 'name' => 'Service B Resource'];
});

echo "✓ 多实例创建成功\n";
echo "服务A缓存键: " . $serviceACache->makeKey('entity', ['id' => 1]) . "\n";
echo "服务B缓存键: " . $serviceBCache->makeKey('resource', ['id' => 1]) . "\n\n";

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

echo "场景1: 数据项缓存\n";
echo str_repeat("-", 30) . "\n";

function getDataItem($itemId) {
    return cache_kv_get('item', ['id' => $itemId], function() use ($itemId) {
        // 模拟数据库查询
        return [
            'id' => $itemId,
            'name' => "Item {$itemId}",
            'description' => "This is item {$itemId}"
        ];
    });
}

$item = getDataItem(123);
echo "数据项: {$item['name']} ({$item['description']})\n\n";

echo "场景2: API 响应缓存\n";
echo str_repeat("-", 30) . "\n";

function getApiResult($endpoint, $params) {
    $paramsHash = md5(json_encode($params));
    return cache_kv_get('api_result', ['endpoint' => $endpoint, 'params_hash' => $paramsHash], function() use ($endpoint, $params) {
        // 模拟 API 调用
        return [
            'endpoint' => $endpoint,
            'params' => $params,
            'result' => 'api_response_data',
            'timestamp' => time()
        ];
    }, 1800); // 30分钟缓存
}

$apiResult = getApiResult('data_service', ['type' => 'list', 'limit' => 10]);
echo "API结果: {$apiResult['endpoint']} -> {$apiResult['result']}\n\n";

echo "场景3: 计算结果缓存\n";
echo str_repeat("-", 30) . "\n";

function getCalculationResult($params) {
    $key = md5(json_encode($params));
    return cache_kv_get('calculation', ['key' => $key], function() use ($params) {
        // 模拟复杂计算
        sleep(1); // 假设需要1秒计算时间
        return array_sum($params) * 1.5;
    }, 3600); // 1小时缓存
}

$result = getCalculationResult([1, 2, 3, 4, 5]);
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
echo "✓ 灵活扩展 - 支持多实例\n";
echo "✓ 无业务耦合 - 通用模板设计\n\n";

echo "=== 示例完成 ===\n";
