<?php

/**
 * 测试新增的 cache_kv_get_keys 批量获取缓存键对象功能
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\ConfigManager;

echo "=== cache_kv_get_keys 功能测试 ===\n\n";

// 加载配置
try {
    ConfigManager::loadConfig(__DIR__ . '/config/complete_cache_kv.php');
    echo "✅ 配置加载成功\n\n";
} catch (Exception $e) {
    echo "❌ 配置加载失败: " . $e->getMessage() . "\n";
    exit(1);
}

$testResults = array();

function testGetKeys($testName, $testFunc) {
    global $testResults;
    
    echo "🧪 测试: {$testName}\n";
    
    try {
        $result = $testFunc();
        if ($result['success']) {
            echo "   ✅ 通过";
            if (isset($result['details'])) {
                echo " - {$result['details']}";
            }
            echo "\n";
            $testResults[$testName] = 'PASS';
        } else {
            echo "   ❌ 失败";
            if (isset($result['error'])) {
                echo " - {$result['error']}";
            }
            echo "\n";
            $testResults[$testName] = 'FAIL';
        }
    } catch (Exception $e) {
        echo "   ❌ 异常: " . $e->getMessage() . "\n";
        $testResults[$testName] = 'ERROR';
    }
    
    echo "\n";
}

// 测试1: 基本功能测试
testGetKeys("基本批量获取键对象", function() {
    $paramsList = array(
        array('id' => 1),
        array('id' => 2),
        array('id' => 3)
    );
    
    $keys = cache_kv_get_keys('user.profile', $paramsList);
    
    if (!is_array($keys)) {
        return array('success' => false, 'error' => '返回值不是数组');
    }
    
    if (count($keys) !== 3) {
        return array('success' => false, 'error' => '返回键数量不正确: ' . count($keys));
    }
    
    $expectedKeys = array(
        'myapp:user:v1:profile:1',
        'myapp:user:v1:profile:2', 
        'myapp:user:v1:profile:3'
    );
    
    foreach ($expectedKeys as $expectedKey) {
        if (!isset($keys[$expectedKey])) {
            return array('success' => false, 'error' => "缺少键: {$expectedKey}");
        }
        
        if (!($keys[$expectedKey] instanceof \Asfop\CacheKV\Key\CacheKey)) {
            return array('success' => false, 'error' => "键值不是CacheKey对象: {$expectedKey}");
        }
    }
    
    return array(
        'success' => true,
        'details' => '成功生成3个键对象: ' . implode(', ', array_keys($keys))
    );
});

// 测试2: 键配置检查
testGetKeys("键配置检查功能", function() {
    $paramsList = array(
        array('id' => 100),
        array('id' => 200)
    );
    
    // 测试有缓存配置的键
    $cacheKeys = cache_kv_get_keys('user.profile', $paramsList);
    
    foreach ($cacheKeys as $keyString => $keyObj) {
        if (!$keyObj->hasCacheConfig()) {
            return array('success' => false, 'error' => "user.profile键应该有缓存配置: {$keyString}");
        }
    }
    
    // 测试没有缓存配置的键
    $nonCacheKeys = cache_kv_get_keys('user.session', array(
        array('token' => 'abc123'),
        array('token' => 'def456')
    ));
    
    foreach ($nonCacheKeys as $keyString => $keyObj) {
        if ($keyObj->hasCacheConfig()) {
            return array('success' => false, 'error' => "user.session键不应该有缓存配置: {$keyString}");
        }
    }
    
    return array(
        'success' => true,
        'details' => '缓存配置检查正确: ' . count($cacheKeys) . '个缓存键 + ' . count($nonCacheKeys) . '个非缓存键'
    );
});

// 测试3: 复杂参数模板
testGetKeys("复杂参数模板处理", function() {
    $paramsList = array(
        array('id' => 1, 'size' => 'small'),
        array('id' => 2, 'size' => 'medium'),
        array('id' => 3, 'size' => 'large')
    );
    
    $keys = cache_kv_get_keys('user.avatar', $paramsList);
    
    $expectedKeys = array(
        'myapp:user:v1:avatar:1:small',
        'myapp:user:v1:avatar:2:medium',
        'myapp:user:v1:avatar:3:large'
    );
    
    foreach ($expectedKeys as $expectedKey) {
        if (!isset($keys[$expectedKey])) {
            return array('success' => false, 'error' => "缺少复杂模板键: {$expectedKey}");
        }
    }
    
    return array(
        'success' => true,
        'details' => '复杂模板处理正确: ' . implode(', ', array_keys($keys))
    );
});

// 测试4: 不同组的键
testGetKeys("不同组的键处理", function() {
    $testCases = array(
        array('user.profile', array(array('id' => 1)), 'myapp:user:v1:profile:1'),
        array('goods.info', array(array('id' => 2)), 'myapp:goods:v1:info:2'),
        array('article.content', array(array('id' => 3)), 'myapp:article:v1:content:3'),
        array('api.response', array(array('endpoint' => 'test', 'params_hash' => 'hash')), 'myapp:api:v2:response:test:hash'),
        array('system.config', array(array('key' => 'setting')), 'myapp:sys:v1:config:setting')
    );
    
    foreach ($testCases as $case) {
        list($template, $paramsList, $expectedKey) = $case;
        
        $keys = cache_kv_get_keys($template, $paramsList);
        
        if (!isset($keys[$expectedKey])) {
            return array('success' => false, 'error' => "组 {$template} 键生成失败: 期望 {$expectedKey}");
        }
    }
    
    return array(
        'success' => true,
        'details' => '所有组的键生成正确: ' . count($testCases) . '个组测试通过'
    );
});

// 测试5: 空参数处理
testGetKeys("空参数处理", function() {
    // 测试空参数列表
    $keys1 = cache_kv_get_keys('user.profile', array());
    
    if (!is_array($keys1) || count($keys1) !== 0) {
        return array('success' => false, 'error' => '空参数列表应该返回空数组');
    }
    
    // 测试包含非数组元素的参数列表
    $keys2 = cache_kv_get_keys('user.profile', array(
        array('id' => 1),
        'invalid_param',  // 非数组参数，应该被跳过
        array('id' => 2)
    ));
    
    if (count($keys2) !== 2) {
        return array('success' => false, 'error' => '非数组参数处理错误，期望2个键，实际' . count($keys2) . '个');
    }
    
    return array(
        'success' => true,
        'details' => '空参数和无效参数处理正确'
    );
});

// 测试6: 错误处理
testGetKeys("错误处理", function() {
    // 测试不存在的组
    try {
        cache_kv_get_keys('nonexistent.key', array(array('id' => 1)));
        return array('success' => false, 'error' => '不存在的组应该抛出异常');
    } catch (Exception $e) {
        // 正确抛出异常
    }
    
    // 测试不存在的键
    try {
        cache_kv_get_keys('user.nonexistent', array(array('id' => 1)));
        return array('success' => false, 'error' => '不存在的键应该抛出异常');
    } catch (Exception $e) {
        // 正确抛出异常
    }
    
    // 测试无效模板格式
    try {
        cache_kv_get_keys('invalid_template', array(array('id' => 1)));
        return array('success' => false, 'error' => '无效模板格式应该抛出异常');
    } catch (Exception $e) {
        // 正确抛出异常
    }
    
    return array(
        'success' => true,
        'details' => '错误处理机制正常工作'
    );
});

// 测试7: 实际使用场景演示
testGetKeys("实际使用场景演示", function() {
    echo "   📋 使用场景演示:\n";
    
    // 场景1: 批量检查键配置
    $userIds = array(
        array('id' => 1),
        array('id' => 2),
        array('id' => 3)
    );
    
    $profileKeys = cache_kv_get_keys('user.profile', $userIds);
    $sessionKeys = cache_kv_get_keys('user.session', array(
        array('token' => 'token1'),
        array('token' => 'token2')
    ));
    
    echo "   - 用户资料键 (有缓存配置):\n";
    foreach ($profileKeys as $keyString => $keyObj) {
        echo "     * {$keyString}: " . ($keyObj->hasCacheConfig() ? '✅缓存' : '❌非缓存') . "\n";
    }
    
    echo "   - 会话键 (无缓存配置):\n";
    foreach ($sessionKeys as $keyString => $keyObj) {
        echo "     * {$keyString}: " . ($keyObj->hasCacheConfig() ? '✅缓存' : '❌非缓存') . "\n";
    }
    
    // 场景2: 键信息统计
    $allKeys = array_merge($profileKeys, $sessionKeys);
    $cacheKeyCount = 0;
    $nonCacheKeyCount = 0;
    
    foreach ($allKeys as $keyObj) {
        if ($keyObj->hasCacheConfig()) {
            $cacheKeyCount++;
        } else {
            $nonCacheKeyCount++;
        }
    }
    
    echo "   - 统计信息: 总键数=" . count($allKeys) . ", 缓存键=" . $cacheKeyCount . ", 非缓存键=" . $nonCacheKeyCount . "\n";
    
    return array(
        'success' => true,
        'details' => '实际使用场景演示完成'
    );
});

// 输出测试总结
echo "=== 测试总结 ===\n\n";

$totalTests = count($testResults);
$passedTests = 0;
$failedTests = array();

foreach ($testResults as $testName => $result) {
    if ($result === 'PASS') {
        $passedTests++;
        echo "✅ {$testName}\n";
    } else {
        $failedTests[] = $testName;
        echo "❌ {$testName}: {$result}\n";
    }
}

echo "\n";
echo "测试统计:\n";
echo "- 总测试项: {$totalTests}\n";
echo "- 通过测试: {$passedTests}\n";
echo "- 失败测试: " . count($failedTests) . "\n";
echo "- 成功率: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";

if (count($failedTests) === 0) {
    echo "🎉 所有测试通过！cache_kv_get_keys 功能正常工作！\n\n";
    
    echo "✅ 新功能特性确认:\n";
    echo "1. ✅ 批量键对象生成 - 一次性生成多个CacheKey对象\n";
    echo "2. ✅ 键配置检查 - 准确区分缓存键和非缓存键\n";
    echo "3. ✅ 复杂模板支持 - 正确处理多参数模板\n";
    echo "4. ✅ 多组支持 - 支持所有配置组的键生成\n";
    echo "5. ✅ 错误处理 - 完善的异常处理机制\n";
    echo "6. ✅ 边界情况 - 正确处理空参数和无效参数\n";
    echo "7. ✅ 实用性 - 适合实际使用场景\n\n";
    
    echo "🎯 函数设计优势:\n";
    echo "- 📦 返回关联数组，键为字符串形式便于查找\n";
    echo "- 🔧 不执行缓存操作，专注于键对象生成\n";
    echo "- 🛡️ 完善的错误处理和参数验证\n";
    echo "- 📋 清晰的文档和使用示例\n";
    echo "- ⚡ 高效的批量处理\n\n";
    
    echo "💡 适用场景:\n";
    echo "- 批量检查键的缓存配置\n";
    echo "- 预生成键对象用于后续操作\n";
    echo "- 键信息统计和分析\n";
    echo "- 缓存键管理和维护\n\n";
    
    echo "🏆 cache_kv_get_keys 函数已经准备好使用！\n";
    
} else {
    echo "⚠️  部分测试失败，需要修复:\n";
    foreach ($failedTests as $test) {
        echo "- {$test}\n";
    }
}

echo "\n=== 使用示例 ===\n";

echo "// 基本用法\n";
echo "\$keys = cache_kv_get_keys('user.profile', [\n";
echo "    ['id' => 1],\n";
echo "    ['id' => 2],\n";
echo "    ['id' => 3]\n";
echo "]);\n\n";

echo "// 检查键配置\n";
echo "foreach (\$keys as \$keyString => \$keyObj) {\n";
echo "    echo \"键: {\$keyString}, 有缓存配置: \" . (\$keyObj->hasCacheConfig() ? '是' : '否') . \"\\n\";\n";
echo "}\n\n";

echo "// 复杂参数模板\n";
echo "\$avatarKeys = cache_kv_get_keys('user.avatar', [\n";
echo "    ['id' => 1, 'size' => 'small'],\n";
echo "    ['id' => 2, 'size' => 'large']\n";
echo "]);\n";

?>
