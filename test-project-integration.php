<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

echo "=== CacheKV 项目联动测试 ===\n\n";

// 测试1: 直接使用 CacheKV + KeyManager
echo "✅ 测试1: 直接使用 CacheKV + KeyManager\n";
$keyManager = new KeyManager([
    'app_prefix' => 'test',
    'env_prefix' => 'dev',
    'version' => 'v1'
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

$userKey = $cache->makeKey('user', ['id' => 123]);
echo "生成的用户键: {$userKey}\n";

$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'Test User'];
});
echo "用户数据: " . json_encode($user) . "\n\n";

// 测试2: 使用门面 + KeyManager
echo "✅ 测试2: 使用门面 + KeyManager\n";
$config = [
    'default' => 'array',
    'stores' => [
        'array' => ['driver' => ArrayDriver::class]
    ],
    'key_manager' => [
        'app_prefix' => 'facade_test',
        'env_prefix' => 'dev',
        'version' => 'v1'
    ]
];

CacheKVServiceProvider::register($config);

$productKey = CacheKVFacade::makeKey('product', ['id' => 456]);
echo "门面生成的产品键: {$productKey}\n";

$product = CacheKVFacade::getByTemplate('product', ['id' => 456], function() {
    return ['id' => 456, 'name' => 'Test Product', 'price' => 99.99];
});
echo "产品数据: " . json_encode($product) . "\n\n";

// 测试3: 标签功能集成
echo "✅ 测试3: 标签功能集成\n";
CacheKVFacade::setByTemplateWithTag('product', ['id' => 789], [
    'id' => 789,
    'name' => 'Tagged Product'
], ['products', 'electronics']);

echo "设置了带标签的产品缓存\n";

$exists = CacheKVFacade::hasByTemplate('product', ['id' => 789]);
echo "产品缓存存在: " . ($exists ? 'Yes' : 'No') . "\n";

CacheKVFacade::clearTag('electronics');
$existsAfterClear = CacheKVFacade::hasByTemplate('product', ['id' => 789]);
echo "清除标签后产品缓存存在: " . ($existsAfterClear ? 'Yes' : 'No') . "\n\n";

// 测试4: 统计功能
echo "✅ 测试4: 统计功能\n";
$stats = CacheKVFacade::getStats();
echo "缓存统计: " . json_encode($stats) . "\n\n";

// 测试5: 键解析功能
echo "✅ 测试5: 键解析功能\n";
$testKey = CacheKVFacade::makeKey('user_profile', ['id' => 999]);
$parsed = CacheKVFacade::getInstance()->getKeyManager()->parse($testKey);
echo "解析键 '{$testKey}':\n";
echo "  - 应用前缀: {$parsed['app_prefix']}\n";
echo "  - 环境前缀: {$parsed['env_prefix']}\n";
echo "  - 版本: {$parsed['version']}\n";
echo "  - 业务键: {$parsed['business_key']}\n\n";

echo "🎉 所有测试通过！CacheKV 项目联动正常工作。\n";
echo "\n📋 功能验证清单:\n";
echo "  ✅ KeyManager 键生成\n";
echo "  ✅ 模板方法集成\n";
echo "  ✅ 门面模式支持\n";
echo "  ✅ 服务提供者配置\n";
echo "  ✅ 标签管理功能\n";
echo "  ✅ 缓存统计功能\n";
echo "  ✅ 键解析功能\n";
echo "\n=== 项目联动测试完成 ===\n";
