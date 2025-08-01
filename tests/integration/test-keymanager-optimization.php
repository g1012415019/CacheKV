<?php

require_once 'vendor/autoload.php';

use Asfop\CacheKV\Cache\KeyManager;

echo "=== KeyManager 优化验证测试 ===\n\n";

// 1. 测试基本功能
echo "1. 测试基本功能\n";
echo "===============\n";

$keyManager = new KeyManager([
    'app_prefix' => 'testapp',
    'env_prefix' => 'dev',
    'version' => 'v2',
    'templates' => [
        'custom_user' => 'custom:user:{id}',
        'complex_key' => 'data:{type}:{category}:{id}',
    ]
]);

// 测试基本键生成
$userKey = $keyManager->make('user', ['id' => 123]);
echo "用户键: {$userKey}\n";

$customKey = $keyManager->make('custom_user', ['id' => 456]);
echo "自定义用户键: {$customKey}\n";

$complexKey = $keyManager->make('complex_key', [
    'type' => 'product',
    'category' => 'electronics',
    'id' => 789
]);
echo "复杂键: {$complexKey}\n";

// 2. 测试参数类型处理
echo "\n2. 测试参数类型处理\n";
echo "==================\n";

$testParams = [
    'bool_true' => true,
    'bool_false' => false,
    'null_value' => null,
    'array_value' => ['a', 'b', 'c'],
    'object_value' => (object)['key' => 'value'],
    'number' => 12345,
    'float' => 123.45,
];

$keyManager->addTemplate('type_test', 'test:{bool_true}:{bool_false}:{null_value}:{array_value}:{object_value}:{number}:{float}');

$typeKey = $keyManager->make('type_test', $testParams);
echo "类型测试键: {$typeKey}\n";

// 3. 测试键解析
echo "\n3. 测试键解析\n";
echo "=============\n";

$parsed = $keyManager->parse($userKey);
echo "解析结果:\n";
echo "  完整键: {$parsed['full_key']}\n";
echo "  有前缀: " . ($parsed['has_prefix'] ? 'Yes' : 'No') . "\n";
echo "  应用前缀: {$parsed['app_prefix']}\n";
echo "  环境前缀: {$parsed['env_prefix']}\n";
echo "  版本: {$parsed['version']}\n";
echo "  业务键: {$parsed['business_key']}\n";

// 测试无前缀键解析
$noPrefixKey = $keyManager->make('user', ['id' => 123], false);
$parsedNoPrefix = $keyManager->parse($noPrefixKey);
echo "\n无前缀键解析:\n";
echo "  完整键: {$parsedNoPrefix['full_key']}\n";
echo "  有前缀: " . ($parsedNoPrefix['has_prefix'] ? 'Yes' : 'No') . "\n";
echo "  业务键: {$parsedNoPrefix['business_key']}\n";

// 4. 测试错误处理
echo "\n4. 测试错误处理\n";
echo "===============\n";

// 测试不存在的模板
try {
    $keyManager->make('nonexistent_template', ['id' => 1]);
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 不存在模板异常: " . $e->getMessage() . "\n";
}

// 测试缺失参数
try {
    $keyManager->make('user', []); // 缺少 id 参数
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 缺失参数异常: " . $e->getMessage() . "\n";
}

// 测试无效模板名称
try {
    $keyManager->addTemplate('', 'invalid:template');
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 无效模板名称异常: " . $e->getMessage() . "\n";
}

// 测试无效模板模式
try {
    $keyManager->addTemplate('invalid', '');
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 无效模板模式异常: " . $e->getMessage() . "\n";
}

// 5. 测试配置验证
echo "\n5. 测试配置验证\n";
echo "===============\n";

// 测试无效配置
try {
    new KeyManager('invalid_config');
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 无效配置类型异常: " . $e->getMessage() . "\n";
}

// 测试包含无效字符的前缀
try {
    new KeyManager(['app_prefix' => 'app with space']);
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 无效字符异常: " . $e->getMessage() . "\n";
}

// 测试空前缀
try {
    new KeyManager(['app_prefix' => '']);
    echo "❌ 应该抛出异常但没有\n";
} catch (Exception $e) {
    echo "✅ 空前缀异常: " . $e->getMessage() . "\n";
}

// 6. 测试键验证和清理
echo "\n6. 测试键验证和清理\n";
echo "==================\n";

$validKey = 'valid:key:123';
$invalidKey = "invalid key\twith\nspecial\rchars";

echo "验证有效键: " . ($keyManager->validate($validKey) ? 'Valid' : 'Invalid') . "\n";
echo "验证无效键: " . ($keyManager->validate($invalidKey) ? 'Valid' : 'Invalid') . "\n";

$cleanedKey = $keyManager->sanitize($invalidKey);
echo "清理后的键: {$cleanedKey}\n";

// 7. 测试模板管理
echo "\n7. 测试模板管理\n";
echo "===============\n";

echo "添加前的模板数量: " . count($keyManager->getTemplates()) . "\n";

$keyManager->addTemplate('new_template', 'new:{id}');
echo "添加后的模板数量: " . count($keyManager->getTemplates()) . "\n";

echo "new_template 存在: " . ($keyManager->hasTemplate('new_template') ? 'Yes' : 'No') . "\n";

$keyManager->removeTemplate('new_template');
echo "移除后 new_template 存在: " . ($keyManager->hasTemplate('new_template') ? 'Yes' : 'No') . "\n";

// 8. 测试配置获取和设置
echo "\n8. 测试配置获取和设置\n";
echo "====================\n";

$config = $keyManager->getConfig();
echo "当前配置:\n";
echo "  应用前缀: {$config['app_prefix']}\n";
echo "  环境前缀: {$config['env_prefix']}\n";
echo "  版本: {$config['version']}\n";
echo "  分隔符: {$config['separator']}\n";
echo "  模板数量: " . count($config['templates']) . "\n";

// 测试前缀设置
$keyManager->setAppPrefix('newapp');
$keyManager->setEnvPrefix('prod');
$keyManager->setVersion('v3');

$newKey = $keyManager->make('user', ['id' => 999]);
echo "更新前缀后的键: {$newKey}\n";

// 9. 测试模式匹配
echo "\n9. 测试模式匹配\n";
echo "===============\n";

$pattern = $keyManager->pattern('user', ['id' => '*']);
echo "用户模式匹配键: {$pattern}\n";

$complexPattern = $keyManager->pattern('complex_key', [
    'type' => 'product',
    'category' => '*',
    'id' => '*'
]);
echo "复杂模式匹配键: {$complexPattern}\n";

// 10. 性能测试
echo "\n10. 性能测试\n";
echo "============\n";

$startTime = microtime(true);

// 生成大量键
for ($i = 0; $i < 10000; $i++) {
    $keyManager->make('user', ['id' => $i]);
}

$keyGenTime = microtime(true) - $startTime;
echo "生成 10000 个键耗时: " . round($keyGenTime * 1000, 2) . "ms\n";

$startTime = microtime(true);

// 解析大量键
$testKey = $keyManager->make('user', ['id' => 123]);
for ($i = 0; $i < 10000; $i++) {
    $keyManager->parse($testKey);
}

$parseTime = microtime(true) - $startTime;
echo "解析 10000 个键耗时: " . round($parseTime * 1000, 2) . "ms\n";

echo "\n=== KeyManager 优化验证测试完成 ===\n";
echo "\n✅ 主要优化:\n";
echo "  - 增强了配置验证和错误处理\n";
echo "  - 改进了参数类型处理和清理\n";
echo "  - 添加了键验证和清理功能\n";
echo "  - 增强了模板管理功能\n";
echo "  - 添加了配置获取和设置方法\n";
echo "  - 改进了代码注释和文档\n";
echo "\n💡 新功能:\n";
echo "  - 支持多种参数类型自动转换\n";
echo "  - 无效字符自动清理\n";
echo "  - 模板动态管理\n";
echo "  - 配置运行时修改\n";
echo "  - 模式匹配键生成\n";
echo "\n🚀 性能表现:\n";
echo "  - 键生成: " . round($keyGenTime * 1000, 2) . "ms (10000次)\n";
echo "  - 键解析: " . round($parseTime * 1000, 2) . "ms (10000次)\n";
echo "  - 平均每次操作: " . round(($keyGenTime + $parseTime) / 20, 6) . "ms\n";
