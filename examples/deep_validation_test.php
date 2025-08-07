<?php

/**
 * CacheKV 深度验证测试
 * 
 * 针对之前测试失败的部分进行深入验证
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\ConfigManager;

echo "=== CacheKV 深度验证测试 ===\n\n";

$testResults = array();
$allPassed = true;

function deepTest($name, $testFunc, $description = '') {
    global $testResults, $allPassed;
    
    echo "🔍 深度测试: {$name}\n";
    if ($description) {
        echo "   📝 {$description}\n";
    }
    
    try {
        $result = $testFunc();
        if ($result['success']) {
            echo "   ✅ 通过";
            if (isset($result['details'])) {
                echo " - {$result['details']}";
            }
            echo "\n";
            $testResults[$name] = 'PASS';
        } else {
            echo "   ❌ 失败";
            if (isset($result['error'])) {
                echo " - {$result['error']}";
            }
            echo "\n";
            $testResults[$name] = 'FAIL';
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "   ❌ 异常: " . $e->getMessage() . "\n";
        $testResults[$name] = 'ERROR';
        $allPassed = false;
    }
    
    echo "\n";
}

// 1. 修复配置问题并重新测试键管理
deepTest("键管理系统完整性", function() {
    // 使用完整配置文件
    ConfigManager::loadConfig(__DIR__ . '/config/complete_cache_kv.php');
    
    $results = array();
    
    // 测试所有组的键生成
    $testCases = array(
        array('user.profile', array('id' => 123), 'myapp:user:v1:profile:123'),
        array('user.settings', array('id' => 456), 'myapp:user:v1:settings:456'),
        array('goods.info', array('id' => 789), 'myapp:goods:v1:info:789'),
        array('article.content', array('id' => 101), 'myapp:article:v1:content:101'),
        array('api.response', array('endpoint' => 'users', 'params_hash' => 'abc123'), 'myapp:api:v2:response:users:abc123'),
        array('system.config', array('key' => 'app_name'), 'myapp:sys:v1:config:app_name')
    );
    
    foreach ($testCases as $case) {
        list($keyName, $params, $expected) = $case;
        
        try {
            $key = cache_kv_make_key($keyName, $params);
            $actual = (string)$key;
            
            if ($actual === $expected) {
                $results[] = "✅ {$keyName}: {$actual}";
            } else {
                return array(
                    'success' => false,
                    'error' => "{$keyName} 键格式错误: 期望 {$expected}, 实际 {$actual}"
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => "{$keyName} 生成失败: " . $e->getMessage()
            );
        }
    }
    
    return array(
        'success' => true,
        'details' => count($testCases) . " 个键生成测试全部通过"
    );
}, "测试所有配置组的键生成功能");

// 2. 深度测试配置继承机制
deepTest("配置继承机制深度验证", function() {
    $inheritanceTests = array();
    
    // 测试用户组的配置继承
    $globalConfig = ConfigManager::getGlobalCacheConfig();
    $userGroupConfig = ConfigManager::getGroupCacheConfig('user');
    $profileKeyConfig = ConfigManager::getKeyCacheConfig('user', 'profile');
    $sessionKeyConfig = ConfigManager::getKeyCacheConfig('user', 'session');
    
    // 验证TTL继承
    $ttlInheritance = array(
        'global' => $globalConfig['ttl'],      // 3600
        'group' => $userGroupConfig['ttl'],    // 7200 (覆盖全局)
        'profile' => $profileKeyConfig['ttl'], // 10800 (覆盖组级)
        'session' => $sessionKeyConfig['ttl']  // 7200 (继承组级)
    );
    
    if ($ttlInheritance['global'] != 3600) {
        return array('success' => false, 'error' => '全局TTL不正确');
    }
    if ($ttlInheritance['group'] != 7200) {
        return array('success' => false, 'error' => '组级TTL不正确');
    }
    if ($ttlInheritance['profile'] != 10800) {
        return array('success' => false, 'error' => 'Profile键TTL不正确');
    }
    if ($ttlInheritance['session'] != 7200) {
        return array('success' => false, 'error' => 'Session键TTL不正确');
    }
    
    // 验证热点键阈值继承
    $thresholdInheritance = array(
        'global' => $globalConfig['hot_key_threshold'],      // 100
        'group' => $userGroupConfig['hot_key_threshold'],    // 50 (覆盖全局)
        'profile' => $profileKeyConfig['hot_key_threshold'], // 30 (覆盖组级)
        'session' => $sessionKeyConfig['hot_key_threshold']  // 50 (继承组级)
    );
    
    if ($thresholdInheritance['global'] != 100) {
        return array('success' => false, 'error' => '全局热点阈值不正确');
    }
    if ($thresholdInheritance['group'] != 50) {
        return array('success' => false, 'error' => '组级热点阈值不正确');
    }
    if ($thresholdInheritance['profile'] != 30) {
        return array('success' => false, 'error' => 'Profile键热点阈值不正确');
    }
    if ($thresholdInheritance['session'] != 50) {
        return array('success' => false, 'error' => 'Session键热点阈值不正确');
    }
    
    return array(
        'success' => true,
        'details' => "TTL继承: {$ttlInheritance['global']}→{$ttlInheritance['group']}→{$ttlInheritance['profile']}, 阈值继承: {$thresholdInheritance['global']}→{$thresholdInheritance['group']}→{$thresholdInheritance['profile']}"
    );
}, "验证多级配置继承的正确性");

// 3. 键行为区分深度测试
deepTest("键行为区分机制", function() {
    $behaviorTests = array();
    
    // 测试有缓存配置的键
    $cacheKeys = array(
        'user.profile' => array('id' => 123),
        'goods.info' => array('id' => 456),
        'article.content' => array('id' => 789)
    );
    
    foreach ($cacheKeys as $keyName => $params) {
        $key = cache_kv_make_key($keyName, $params);
        if (!$key->hasCacheConfig()) {
            return array(
                'success' => false,
                'error' => "{$keyName} 应该有缓存配置但检测为无"
            );
        }
        $behaviorTests[] = "✅ {$keyName}: 有缓存配置";
    }
    
    // 测试没有缓存配置的键
    $nonCacheKeys = array(
        'user.session' => array('token' => 'abc123'),
        'user.lock' => array('id' => 123, 'action' => 'update'),
        'article.view_count' => array('id' => 456),
        'api.rate_limit' => array('user_id' => 789, 'endpoint' => 'users')
    );
    
    foreach ($nonCacheKeys as $keyName => $params) {
        $key = cache_kv_make_key($keyName, $params);
        if ($key->hasCacheConfig()) {
            return array(
                'success' => false,
                'error' => "{$keyName} 不应该有缓存配置但检测为有"
            );
        }
        $behaviorTests[] = "✅ {$keyName}: 无缓存配置";
    }
    
    return array(
        'success' => true,
        'details' => count($cacheKeys) . " 个缓存键 + " . count($nonCacheKeys) . " 个非缓存键行为正确"
    );
}, "验证键的缓存行为判断准确性");

// 4. API参数验证
deepTest("API参数设计验证", function() {
    $apiTests = array();
    
    // 检查 cache_kv_get 的实际参数
    $reflection = new ReflectionFunction('cache_kv_get');
    $params = $reflection->getParameters();
    
    $paramNames = array();
    foreach ($params as $param) {
        $paramNames[] = $param->getName();
    }
    
    // cache_kv_get 应该有3个必需参数 + 1个可选参数
    if (count($params) < 3) {
        return array(
            'success' => false,
            'error' => "cache_kv_get 参数数量不足: " . count($params) . ", 期望至少3个"
        );
    }
    
    $expectedParams = array('keyName', 'params', 'callback');
    for ($i = 0; $i < 3; $i++) {
        if (!isset($params[$i]) || $params[$i]->getName() !== $expectedParams[$i]) {
            return array(
                'success' => false,
                'error' => "cache_kv_get 第" . ($i+1) . "个参数名错误: 期望 {$expectedParams[$i]}, 实际 " . ($params[$i]->getName() ?? 'null')
            );
        }
    }
    
    // 检查其他API
    $apiChecks = array(
        'cache_kv_get_multiple' => 3,
        'cache_kv_make_key' => 2,
        'cache_kv_make_keys' => 2,
        'cache_kv_delete_by_prefix' => 1
    );
    
    foreach ($apiChecks as $funcName => $expectedCount) {
        $funcReflection = new ReflectionFunction($funcName);
        $actualCount = $funcReflection->getNumberOfRequiredParameters();
        
        if ($actualCount != $expectedCount) {
            return array(
                'success' => false,
                'error' => "{$funcName} 必需参数数量错误: 期望 {$expectedCount}, 实际 {$actualCount}"
            );
        }
        $apiTests[] = "✅ {$funcName}: {$actualCount} 个必需参数";
    }
    
    return array(
        'success' => true,
        'details' => "所有API参数设计正确: " . implode(', ', $paramNames)
    );
}, "验证API函数的参数设计");

// 5. 边界情况测试
deepTest("边界情况处理", function() {
    $boundaryTests = array();
    
    // 测试空参数
    try {
        cache_kv_make_key('user.profile', array());
        return array('success' => false, 'error' => '空参数应该抛出异常');
    } catch (Exception $e) {
        $boundaryTests[] = "✅ 空参数正确抛出异常";
    }
    
    // 测试缺少必需参数
    try {
        cache_kv_make_key('user.avatar', array('id' => 123)); // 缺少 size 参数
        return array('success' => false, 'error' => '缺少必需参数应该抛出异常');
    } catch (Exception $e) {
        $boundaryTests[] = "✅ 缺少必需参数正确抛出异常";
    }
    
    // 测试不存在的组
    try {
        cache_kv_make_key('nonexistent.key', array('id' => 123));
        return array('success' => false, 'error' => '不存在的组应该抛出异常');
    } catch (Exception $e) {
        $boundaryTests[] = "✅ 不存在的组正确抛出异常";
    }
    
    // 测试不存在的键
    try {
        cache_kv_make_key('user.nonexistent', array('id' => 123));
        return array('success' => false, 'error' => '不存在的键应该抛出异常');
    } catch (Exception $e) {
        $boundaryTests[] = "✅ 不存在的键正确抛出异常";
    }
    
    return array(
        'success' => true,
        'details' => count($boundaryTests) . " 个边界情况测试通过"
    );
}, "测试各种边界情况和错误处理");

// 6. 复杂参数模板测试
deepTest("复杂参数模板处理", function() {
    $templateTests = array();
    
    // 测试多参数模板
    $complexCases = array(
        array(
            'key' => 'user.avatar',
            'params' => array('id' => 123, 'size' => 'large'),
            'expected' => 'myapp:user:v1:avatar:123:large'
        ),
        array(
            'key' => 'article.comments',
            'params' => array('id' => 456, 'page' => 2),
            'expected' => 'myapp:article:v1:comments:456:2'
        ),
        array(
            'key' => 'api.response',
            'params' => array('endpoint' => 'users/search', 'params_hash' => 'md5hash123'),
            'expected' => 'myapp:api:v2:response:users/search:md5hash123'
        ),
        array(
            'key' => 'user.lock',
            'params' => array('id' => 789, 'action' => 'delete'),
            'expected' => 'myapp:user:v1:lock:789:delete'
        )
    );
    
    foreach ($complexCases as $case) {
        $key = cache_kv_make_key($case['key'], $case['params']);
        $actual = (string)$key;
        
        if ($actual !== $case['expected']) {
            return array(
                'success' => false,
                'error' => "{$case['key']} 模板处理错误: 期望 {$case['expected']}, 实际 {$actual}"
            );
        }
        
        $templateTests[] = "✅ {$case['key']}: {$actual}";
    }
    
    return array(
        'success' => true,
        'details' => count($complexCases) . " 个复杂模板测试通过"
    );
}, "测试复杂参数模板的处理能力");

// 7. 版本管理测试
deepTest("版本管理机制", function() {
    $versionTests = array();
    
    // 测试不同组的版本
    $versionCases = array(
        array('user.profile', array('id' => 123), 'v1'),
        array('goods.info', array('id' => 456), 'v1'),
        array('article.content', array('id' => 789), 'v1'),
        array('api.response', array('endpoint' => 'test', 'params_hash' => 'hash'), 'v2'), // API组使用v2
        array('system.config', array('key' => 'test'), 'v1')
    );
    
    foreach ($versionCases as $case) {
        list($keyName, $params, $expectedVersion) = $case;
        
        $key = cache_kv_make_key($keyName, $params);
        $keyString = (string)$key;
        
        if (!preg_match("/:$expectedVersion:/", $keyString)) {
            return array(
                'success' => false,
                'error' => "{$keyName} 版本错误: 期望包含 {$expectedVersion}, 实际 {$keyString}"
            );
        }
        
        $versionTests[] = "✅ {$keyName}: 版本 {$expectedVersion}";
    }
    
    return array(
        'success' => true,
        'details' => count($versionCases) . " 个版本管理测试通过"
    );
}, "验证不同组的版本管理");

// 8. 配置完整性验证
deepTest("配置文件完整性", function() {
    $configTests = array();
    
    // 验证所有组都有必需的配置项
    $keyManagerConfig = ConfigManager::getKeyManagerConfig();
    $groups = $keyManagerConfig['groups'];
    
    $requiredGroupFields = array('prefix', 'version', 'keys');
    $requiredKeyFields = array('template');
    
    foreach ($groups as $groupName => $groupConfig) {
        // 检查组级必需字段
        foreach ($requiredGroupFields as $field) {
            if (!isset($groupConfig[$field])) {
                return array(
                    'success' => false,
                    'error' => "组 {$groupName} 缺少必需字段: {$field}"
                );
            }
        }
        
        // 检查键级必需字段
        foreach ($groupConfig['keys'] as $keyName => $keyConfig) {
            foreach ($requiredKeyFields as $field) {
                if (!isset($keyConfig[$field])) {
                    return array(
                        'success' => false,
                        'error' => "组 {$groupName} 的键 {$keyName} 缺少必需字段: {$field}"
                    );
                }
            }
        }
        
        $configTests[] = "✅ 组 {$groupName}: " . count($groupConfig['keys']) . " 个键";
    }
    
    return array(
        'success' => true,
        'details' => count($groups) . " 个组配置完整性验证通过"
    );
}, "验证配置文件的完整性和正确性");

// 输出测试总结
echo "=== 深度验证测试总结 ===\n\n";

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
echo "深度验证统计:\n";
echo "- 总测试项: {$totalTests}\n";
echo "- 通过测试: {$passedTests}\n";
echo "- 失败测试: " . count($failedTests) . "\n";
echo "- 成功率: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";

if ($allPassed) {
    echo "🎉 所有深度验证测试通过！\n\n";
    
    echo "✅ 深度验证确认的功能：\n";
    echo "1. ✅ 键管理系统 - 所有组的键生成功能正常\n";
    echo "2. ✅ 配置继承机制 - 三级继承逻辑完全正确\n";
    echo "3. ✅ 键行为区分 - 缓存键与非缓存键区分准确\n";
    echo "4. ✅ API参数设计 - 所有API函数参数设计合理\n";
    echo "5. ✅ 边界情况处理 - 错误处理机制完善\n";
    echo "6. ✅ 复杂模板处理 - 多参数模板解析正确\n";
    echo "7. ✅ 版本管理机制 - 不同组版本管理正常\n";
    echo "8. ✅ 配置完整性 - 配置文件结构完整正确\n\n";
    
    echo "🏆 深度验证结论：CacheKV 包的所有核心功能都经过了严格验证，\n";
    echo "    完全符合设计预期，可以放心用于生产环境！\n\n";
    
    echo "📊 验证覆盖范围：\n";
    echo "- 🔧 核心功能：键生成、配置继承、行为区分\n";
    echo "- 🛡️ 错误处理：边界情况、异常处理\n";
    echo "- 📋 API设计：参数验证、接口一致性\n";
    echo "- ⚙️ 配置管理：完整性、正确性\n";
    echo "- 🎯 复杂场景：多参数模板、版本管理\n";
    
} else {
    echo "⚠️  部分深度验证测试失败：\n";
    foreach ($failedTests as $test) {
        echo "- {$test}\n";
    }
    echo "\n需要进一步检查和修复上述问题。\n";
}

?>
