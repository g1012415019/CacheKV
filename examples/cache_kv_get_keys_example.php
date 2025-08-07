<?php

/**
 * cache_kv_get_keys 函数使用示例
 * 
 * 这个函数用于批量获取缓存键对象，不执行实际的缓存操作
 * 适用于键信息检查、配置验证、批量键管理等场景
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\ConfigManager;

echo "=== cache_kv_get_keys 使用示例 ===\n\n";

// 加载配置
ConfigManager::loadConfig(__DIR__ . '/config/complete_cache_kv.php');

// ==================== 示例1: 基本用法 ====================
echo "📋 示例1: 基本批量获取键对象\n";

$userIds = array(
    array('id' => 1),
    array('id' => 2),
    array('id' => 3)
);

$keys = cache_kv_get_keys('user.profile', $userIds);

echo "生成的键对象:\n";
foreach ($keys as $keyString => $keyObj) {
    echo "- {$keyString}\n";
}

echo "\n";

// ==================== 示例2: 键配置检查 ====================
echo "📋 示例2: 批量检查键的缓存配置\n";

// 检查有缓存配置的键
$profileKeys = cache_kv_get_keys('user.profile', array(
    array('id' => 100),
    array('id' => 200)
));

echo "用户资料键 (应该有缓存配置):\n";
foreach ($profileKeys as $keyString => $keyObj) {
    $hasCache = $keyObj->hasCacheConfig() ? '✅ 有缓存配置' : '❌ 无缓存配置';
    echo "- {$keyString}: {$hasCache}\n";
}

// 检查没有缓存配置的键
$sessionKeys = cache_kv_get_keys('user.session', array(
    array('token' => 'abc123'),
    array('token' => 'def456')
));

echo "\n会话键 (应该无缓存配置):\n";
foreach ($sessionKeys as $keyString => $keyObj) {
    $hasCache = $keyObj->hasCacheConfig() ? '✅ 有缓存配置' : '❌ 无缓存配置';
    echo "- {$keyString}: {$hasCache}\n";
}

echo "\n";

// ==================== 示例3: 复杂参数模板 ====================
echo "📋 示例3: 复杂参数模板处理\n";

$avatarParams = array(
    array('id' => 1, 'size' => 'small'),
    array('id' => 2, 'size' => 'medium'),
    array('id' => 3, 'size' => 'large')
);

$avatarKeys = cache_kv_get_keys('user.avatar', $avatarParams);

echo "用户头像键 (多参数模板):\n";
foreach ($avatarKeys as $keyString => $keyObj) {
    echo "- {$keyString}\n";
}

echo "\n";

// ==================== 示例4: 不同组的键 ====================
echo "📋 示例4: 不同组和版本的键生成\n";

$testCases = array(
    array('template' => 'user.profile', 'params' => array('id' => 1), 'group' => 'user', 'version' => 'v1'),
    array('template' => 'goods.info', 'params' => array('id' => 2), 'group' => 'goods', 'version' => 'v1'),
    array('template' => 'api.response', 'params' => array('endpoint' => 'users', 'params_hash' => 'hash123'), 'group' => 'api', 'version' => 'v2'),
    array('template' => 'system.config', 'params' => array('key' => 'app_name'), 'group' => 'system', 'version' => 'v1')
);

foreach ($testCases as $case) {
    $keys = cache_kv_get_keys($case['template'], array($case['params']));
    $keyString = array_keys($keys)[0];
    echo "- {$case['group']} 组 ({$case['version']}): {$keyString}\n";
}

echo "\n";

// ==================== 示例5: 键信息统计 ====================
echo "📋 示例5: 键信息统计分析\n";

// 收集不同类型的键
$allTestKeys = array();

// 用户相关键
$userKeys = cache_kv_get_keys('user.profile', array(
    array('id' => 1), array('id' => 2), array('id' => 3)
));
$userSessionKeys = cache_kv_get_keys('user.session', array(
    array('token' => 'token1'), array('token' => 'token2')
));

// 商品相关键
$goodsKeys = cache_kv_get_keys('goods.info', array(
    array('id' => 10), array('id' => 20)
));

// 合并所有键
$allTestKeys = array_merge($userKeys, $userSessionKeys, $goodsKeys);

// 统计分析
$stats = array(
    'total' => count($allTestKeys),
    'cache_keys' => 0,
    'non_cache_keys' => 0,
    'groups' => array()
);

foreach ($allTestKeys as $keyString => $keyObj) {
    // 统计缓存配置
    if ($keyObj->hasCacheConfig()) {
        $stats['cache_keys']++;
    } else {
        $stats['non_cache_keys']++;
    }
    
    // 统计组分布
    $parts = explode(':', $keyString);
    if (count($parts) >= 2) {
        $group = $parts[1];
        if (!isset($stats['groups'][$group])) {
            $stats['groups'][$group] = 0;
        }
        $stats['groups'][$group]++;
    }
}

echo "键统计信息:\n";
echo "- 总键数: {$stats['total']}\n";
echo "- 缓存键: {$stats['cache_keys']}\n";
echo "- 非缓存键: {$stats['non_cache_keys']}\n";
echo "- 组分布:\n";
foreach ($stats['groups'] as $group => $count) {
    echo "  * {$group}: {$count} 个键\n";
}

echo "\n";

// ==================== 示例6: 实际应用场景 ====================
echo "📋 示例6: 实际应用场景演示\n";

echo "场景A: 缓存预热准备\n";
// 假设我们要预热用户缓存，先获取所有需要的键对象
$userIdsToWarm = array(
    array('id' => 1001), array('id' => 1002), array('id' => 1003),
    array('id' => 1004), array('id' => 1005)
);

$keysToWarm = cache_kv_get_keys('user.profile', $userIdsToWarm);
echo "准备预热 " . count($keysToWarm) . " 个用户资料缓存:\n";
foreach ($keysToWarm as $keyString => $keyObj) {
    if ($keyObj->hasCacheConfig()) {
        echo "- 将预热: {$keyString}\n";
    }
}

echo "\n场景B: 缓存键管理\n";
// 假设我们要管理某个功能的所有相关键
$featureKeys = array();

// 收集用户相关的所有键类型
$userId = 12345;
$userRelatedTemplates = array('user.profile', 'user.settings', 'user.session');

foreach ($userRelatedTemplates as $template) {
    $params = array();
    if ($template === 'user.session') {
        $params = array('token' => 'user_' . $userId . '_token');
    } else {
        $params = array('id' => $userId);
    }
    
    $keys = cache_kv_get_keys($template, array($params));
    $featureKeys = array_merge($featureKeys, $keys);
}

echo "用户 {$userId} 相关的所有键:\n";
foreach ($featureKeys as $keyString => $keyObj) {
    $type = $keyObj->hasCacheConfig() ? '缓存键' : '普通键';
    echo "- {$keyString} ({$type})\n";
}

echo "\n";

// ==================== 示例7: 错误处理演示 ====================
echo "📋 示例7: 错误处理演示\n";

echo "正确处理各种错误情况:\n";

// 空参数列表
$emptyKeys = cache_kv_get_keys('user.profile', array());
echo "- 空参数列表: 返回 " . count($emptyKeys) . " 个键 (正确)\n";

// 包含无效参数
$mixedParams = array(
    array('id' => 1),
    'invalid_param',  // 这个会被跳过
    array('id' => 2)
);
$mixedKeys = cache_kv_get_keys('user.profile', $mixedParams);
echo "- 混合参数列表: 返回 " . count($mixedKeys) . " 个键 (跳过无效参数)\n";

// 错误情况演示（会抛出异常）
$errorCases = array(
    array('template' => 'nonexistent.key', 'error' => '不存在的组'),
    array('template' => 'user.nonexistent', 'error' => '不存在的键'),
    array('template' => 'invalid_format', 'error' => '无效的模板格式')
);

foreach ($errorCases as $case) {
    try {
        cache_kv_get_keys($case['template'], array(array('id' => 1)));
        echo "- {$case['error']}: ❌ 应该抛出异常但没有\n";
    } catch (Exception $e) {
        echo "- {$case['error']}: ✅ 正确抛出异常\n";
    }
}

echo "\n";

// ==================== 总结 ====================
echo "🎯 总结\n";
echo "cache_kv_get_keys 函数的主要特点:\n";
echo "1. 📦 批量生成键对象，返回关联数组便于查找\n";
echo "2. 🔧 不执行缓存操作，专注于键对象生成和信息获取\n";
echo "3. 🛡️ 完善的错误处理和参数验证\n";
echo "4. 📋 支持复杂参数模板和多组键生成\n";
echo "5. ⚡ 高效的批量处理，适合各种实际场景\n\n";

echo "💡 适用场景:\n";
echo "- 缓存预热前的键准备\n";
echo "- 批量检查键的缓存配置\n";
echo "- 键信息统计和分析\n";
echo "- 缓存键管理和维护\n";
echo "- 调试和开发时的键信息查看\n\n";

echo "🏆 函数已经准备好在生产环境中使用！\n";

?>
