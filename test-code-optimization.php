<?php

require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== 代码优化验证测试 ===\n\n";

// 1. 测试优化后的 ArrayDriver
echo "1. 测试优化后的 ArrayDriver\n";
echo "============================\n";

$driver = new ArrayDriver();

// 测试基本操作
$driver->set('test1', 'value1', 10);
$driver->set('test2', 'value2', 1); // 1秒后过期

echo "设置缓存: test1=value1 (10s), test2=value2 (1s)\n";
echo "获取 test1: " . $driver->get('test1') . "\n";
echo "获取 test2: " . $driver->get('test2') . "\n";

// 等待过期
echo "等待 2 秒...\n";
sleep(2);

echo "过期后获取 test1: " . ($driver->get('test1') ?: 'null') . "\n";
echo "过期后获取 test2: " . ($driver->get('test2') ?: 'null') . "\n";

// 测试批量操作
$driver->setMultiple([
    'batch1' => 'value1',
    'batch2' => 'value2',
    'batch3' => 'value3'
], 60);

$batchResults = $driver->getMultiple(['batch1', 'batch2', 'batch3', 'nonexistent']);
echo "批量获取结果: " . count($batchResults) . " 个项目\n";

// 测试标签功能
$driver->set('tagged1', 'value1', 60);
$driver->set('tagged2', 'value2', 60);
$driver->tag('tagged1', ['group1', 'important']);
$driver->tag('tagged2', ['group1', 'normal']);

echo "设置标签后，tagged1 存在: " . ($driver->has('tagged1') ? 'Yes' : 'No') . "\n";
echo "设置标签后，tagged2 存在: " . ($driver->has('tagged2') ? 'Yes' : 'No') . "\n";

$driver->clearTag('group1');
echo "清除 group1 标签后，tagged1 存在: " . ($driver->has('tagged1') ? 'Yes' : 'No') . "\n";
echo "清除 group1 标签后，tagged2 存在: " . ($driver->has('tagged2') ? 'Yes' : 'No') . "\n";

echo "ArrayDriver 统计: " . json_encode($driver->getStats()) . "\n\n";

// 2. 测试优化后的 CacheKV
echo "2. 测试优化后的 CacheKV\n";
echo "=======================\n";

$keyManager = new KeyManager([
    'app_prefix' => 'test',
    'env_prefix' => 'dev',
    'version' => 'v1',
    'templates' => [
        'user' => 'user:{id}',
        'product' => 'product:{id}',
        'session' => 'session:{id}',
        'perf_test' => 'perf_test:{id}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 60, $keyManager);

// 测试滑动过期功能
echo "测试滑动过期功能:\n";
$cache->set('sliding_test', 'initial_value', 3);
echo "设置 sliding_test，3秒过期\n";

// 不启用滑动过期
$value1 = $cache->get('sliding_test', null, null, false);
echo "1秒后获取（无滑动过期）: " . $value1 . "\n";
sleep(1);

// 启用滑动过期
$value2 = $cache->get('sliding_test', null, 5, true); // 延长到5秒
echo "2秒后获取（启用滑动过期，延长到5秒）: " . $value2 . "\n";
sleep(2);

$value3 = $cache->get('sliding_test');
echo "4秒后获取（应该仍然存在）: " . ($value3 ?: 'null') . "\n";

// 测试模板方法
echo "\n测试模板方法:\n";
$userData = $cache->getByTemplate('user', ['id' => 123], function() {
    echo "从数据源获取用户 123\n";
    return ['id' => 123, 'name' => 'Test User', 'email' => 'test@example.com'];
});
echo "用户数据: " . json_encode($userData) . "\n";

// 测试缓存命中
$cachedUser = $cache->getByTemplate('user', ['id' => 123], function() {
    echo "这不应该被执行（缓存命中）\n";
    return null;
});
echo "缓存命中: " . json_encode($cachedUser) . "\n";

// 测试带标签的模板方法
$cache->setByTemplateWithTag('product', ['id' => 456], [
    'id' => 456,
    'name' => 'Test Product',
    'price' => 99.99
], ['products', 'electronics']);

echo "设置带标签的产品缓存\n";
echo "产品存在: " . ($cache->hasByTemplate('product', ['id' => 456]) ? 'Yes' : 'No') . "\n";

$cache->clearTag('products');
echo "清除 products 标签后，产品存在: " . ($cache->hasByTemplate('product', ['id' => 456]) ? 'Yes' : 'No') . "\n";

// 3. 测试错误处理
echo "\n3. 测试错误处理\n";
echo "===============\n";

// 测试空键处理
echo "空键测试:\n";
echo "设置空键: " . ($cache->set('', 'value') ? 'Success' : 'Failed') . "\n";
echo "获取空键: " . ($cache->get('') ?: 'null') . "\n";
echo "检查空键: " . ($cache->has('') ? 'Exists' : 'Not exists') . "\n";

// 测试无效 TTL
echo "\n无效 TTL 测试:\n";
echo "设置负数 TTL: " . ($cache->set('negative_ttl', 'value', -1) ? 'Success' : 'Failed') . "\n";
echo "设置零 TTL: " . ($cache->set('zero_ttl', 'value', 0) ? 'Success' : 'Failed') . "\n";

// 测试批量操作的错误处理
echo "\n批量操作错误处理:\n";
$emptyResults = $cache->getMultiple([]);
echo "空数组批量获取: " . count($emptyResults) . " 个结果\n";

$invalidResults = $cache->getMultiple('not_array');
echo "非数组批量获取: " . count($invalidResults) . " 个结果\n";

// 测试回调函数异常处理
echo "\n回调函数异常处理:\n";
$exceptionResult = $cache->getMultiple(['test_exception'], function($keys) {
    throw new Exception('Test exception');
});
echo "异常回调结果: " . count($exceptionResult) . " 个结果\n";

// 4. 测试新增的辅助方法
echo "\n4. 测试新增的辅助方法\n";
echo "=====================\n";

echo "默认 TTL: " . $cache->getDefaultTtl() . " 秒\n";
$cache->setDefaultTtl(1800);
echo "设置新的默认 TTL: " . $cache->getDefaultTtl() . " 秒\n";

echo "KeyManager 已设置: " . ($cache->getKeyManager() ? 'Yes' : 'No') . "\n";
echo "驱动类型: " . get_class($cache->getDriver()) . "\n";

// 测试 ArrayDriver 的新方法
$arrayDriver = $cache->getDriver();
echo "缓存项目数量: " . $arrayDriver->count() . "\n";
echo "清理过期项目: " . $arrayDriver->cleanup() . " 个\n";

// 5. 性能测试
echo "\n5. 性能测试\n";
echo "===========\n";

$startTime = microtime(true);

// 批量设置
for ($i = 0; $i < 1000; $i++) {
    $cache->setByTemplate('perf_test', ['id' => $i], "value_{$i}");
}

$setTime = microtime(true) - $startTime;
echo "设置 1000 个缓存项耗时: " . round($setTime * 1000, 2) . "ms\n";

$startTime = microtime(true);

// 批量获取
$hits = 0;
for ($i = 0; $i < 1000; $i++) {
    $value = $cache->getByTemplate('perf_test', ['id' => $i]);
    if ($value !== null) {
        $hits++;
    }
}

$getTime = microtime(true) - $startTime;
echo "获取 1000 个缓存项耗时: " . round($getTime * 1000, 2) . "ms\n";
echo "命中数量: {$hits}\n";

// 最终统计
echo "\n6. 最终统计\n";
echo "===========\n";
$finalStats = $cache->getStats();
echo "最终缓存统计:\n";
echo "  命中次数: {$finalStats['hits']}\n";
echo "  未命中次数: {$finalStats['misses']}\n";
echo "  命中率: {$finalStats['hit_rate']}%\n";

echo "\n=== 代码优化验证测试完成 ===\n";
echo "\n✅ 主要优化:\n";
echo "  - 修复了滑动过期逻辑，改为可选参数\n";
echo "  - 增强了错误处理和边界情况检查\n";
echo "  - 优化了 ArrayDriver 的过期处理机制\n";
echo "  - 添加了更多辅助方法和统计功能\n";
echo "  - 改进了批量操作的异常处理\n";
echo "  - 增加了代码注释和文档\n";
echo "\n💡 性能改进:\n";
echo "  - 更高效的过期检查机制\n";
echo "  - 更好的内存管理\n";
echo "  - 更准确的统计信息\n";
echo "  - 更稳定的错误处理\n";
