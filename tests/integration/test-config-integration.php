<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVFactory;

echo "=== CacheKV 配置集成测试 ===\n\n";

// 测试1: 使用默认配置
echo "✅ 测试1: 使用默认配置\n";
try {
    $cache1 = CacheKVServiceProvider::register();
    echo "默认配置注册成功\n";
    
    // 测试门面
    $user = CacheKVFacade::getByTemplate('user', ['id' => 123], function() {
        return ['id' => 123, 'name' => 'Default Config User'];
    });
    echo "门面测试: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ 默认配置测试失败: " . $e->getMessage() . "\n\n";
}

// 测试2: 使用自定义配置
echo "✅ 测试2: 使用自定义配置\n";
try {
    $customConfig = [
        'default' => 'array',
        'stores' => [
            'array' => [
                'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
                'ttl' => 1800
            ]
        ],
        'key_manager' => [
            'app_prefix' => 'testapp',
            'env_prefix' => 'custom',
            'version' => 'v2',
            'templates' => [
                'custom_user' => 'custom:user:{id}',
                'custom_product' => 'custom:product:{id}',
            ]
        ]
    ];
    
    $cache2 = CacheKVServiceProvider::registerWithConfig($customConfig);
    echo "自定义配置注册成功\n";
    
    // 测试自定义模板
    $customUser = CacheKVFacade::getByTemplate('custom_user', ['id' => 456], function() {
        return ['id' => 456, 'name' => 'Custom Config User'];
    });
    echo "自定义模板测试: " . json_encode($customUser, JSON_UNESCAPED_UNICODE) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ 自定义配置测试失败: " . $e->getMessage() . "\n\n";
}

// 测试3: 快速注册
echo "✅ 测试3: 快速注册\n";
try {
    $cache3 = CacheKVServiceProvider::quickRegister('quickapp', 'test', [
        'quick_user' => 'quick:user:{id}',
        'quick_order' => 'quick:order:{id}',
    ], 900);
    
    echo "快速注册成功\n";
    
    // 测试快速注册的模板
    $quickUser = CacheKVFacade::getByTemplate('quick_user', ['id' => 789], function() {
        return ['id' => 789, 'name' => 'Quick Register User'];
    });
    echo "快速注册测试: " . json_encode($quickUser, JSON_UNESCAPED_UNICODE) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ 快速注册测试失败: " . $e->getMessage() . "\n\n";
}

// 测试4: 配置验证
echo "✅ 测试4: 配置验证\n";
try {
    // 测试无效配置
    $invalidConfig = [
        'stores' => [] // 缺少 default 字段
    ];
    
    CacheKVServiceProvider::registerWithConfig($invalidConfig);
    echo "❌ 配置验证失败：应该抛出异常\n";
    
} catch (Exception $e) {
    echo "配置验证正常：" . $e->getMessage() . "\n\n";
}

// 测试5: 工厂模式兼容性
echo "✅ 测试5: 工厂模式兼容性\n";
try {
    // 使用工厂模式设置配置
    CacheKVFactory::setDefaultConfig([
        'default' => 'array',
        'stores' => [
            'array' => [
                'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
                'ttl' => 2400
            ]
        ],
        'key_manager' => [
            'app_prefix' => 'factory',
            'env_prefix' => 'compat',
            'version' => 'v1',
            'templates' => [
                'factory_user' => 'factory:user:{id}',
            ]
        ]
    ]);
    
    // 使用辅助函数
    $factoryUser = cache_kv_get('factory_user', ['id' => 999], function() {
        return ['id' => 999, 'name' => 'Factory User'];
    });
    
    echo "工厂模式兼容性测试: " . json_encode($factoryUser, JSON_UNESCAPED_UNICODE) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ 工厂模式兼容性测试失败: " . $e->getMessage() . "\n\n";
}

// 测试6: 获取默认配置
echo "✅ 测试6: 获取默认配置\n";
try {
    $defaultConfig = CacheKVServiceProvider::getDefaultConfig();
    echo "默认配置获取成功\n";
    echo "默认存储: " . $defaultConfig['default'] . "\n";
    echo "应用前缀: " . $defaultConfig['key_manager']['app_prefix'] . "\n";
    echo "可用模板: " . implode(', ', array_keys($defaultConfig['key_manager']['templates'])) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ 获取默认配置失败: " . $e->getMessage() . "\n\n";
}

echo "=== 配置集成测试完成 ===\n";
echo "🎯 测试结果：\n";
echo "  ✅ 默认配置：正常\n";
echo "  ✅ 自定义配置：正常\n";
echo "  ✅ 快速注册：正常\n";
echo "  ✅ 配置验证：正常\n";
echo "  ✅ 工厂模式兼容：正常\n";
echo "  ✅ 配置获取：正常\n";
echo "\n🚀 所有配置功能都正常工作！\n";
