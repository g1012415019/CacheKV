<?php

/**
 * 验证 helpers 函数不包含业务逻辑的测试
 * 
 * 检查 helpers 函数是否只做简单的委托调用
 */

require_once '../vendor/autoload.php';

echo "=== Helpers 函数业务逻辑检查 ===\n\n";

// 使用反射检查 helpers 函数的代码复杂度
function analyzeFunction($functionName) {
    if (!function_exists($functionName)) {
        echo "❌ 函数 {$functionName} 不存在\n";
        return false;
    }
    
    $reflection = new ReflectionFunction($functionName);
    $filename = $reflection->getFileName();
    $startLine = $reflection->getStartLine();
    $endLine = $reflection->getEndLine();
    
    // 读取函数源码
    $file = file($filename);
    $functionCode = '';
    for ($i = $startLine - 1; $i < $endLine; $i++) {
        $functionCode .= $file[$i];
    }
    
    // 分析代码复杂度指标
    $analysis = array(
        'name' => $functionName,
        'lines' => $endLine - $startLine + 1,
        'has_loops' => preg_match('/\b(for|foreach|while|do)\b/', $functionCode) > 0,
        'has_conditions' => preg_match('/\b(if|switch|case|\?)\b/', $functionCode) > 0,
        'has_string_operations' => preg_match('/(explode|implode|substr|str_|preg_)/', $functionCode) > 0,
        'has_array_operations' => preg_match('/(array_|count\(|empty\(|isset\()/', $functionCode) > 0,
        'delegation_calls' => preg_match_all('/->(\w+)\(/', $functionCode),
        'return_statements' => preg_match_all('/return\s+/', $functionCode)
    );
    
    // 判断是否为简单委托
    $isSimpleDelegation = (
        $analysis['lines'] <= 5 &&  // 代码行数少
        !$analysis['has_loops'] &&  // 没有循环
        !$analysis['has_string_operations'] && // 没有字符串操作
        !$analysis['has_array_operations'] && // 没有数组操作
        $analysis['delegation_calls'] === 1 && // 只有一个方法调用
        $analysis['return_statements'] === 1   // 只有一个return语句
    );
    
    echo "🔍 分析函数: {$functionName}\n";
    echo "   - 代码行数: {$analysis['lines']}\n";
    echo "   - 包含循环: " . ($analysis['has_loops'] ? '是' : '否') . "\n";
    echo "   - 包含条件: " . ($analysis['has_conditions'] ? '是' : '否') . "\n";
    echo "   - 字符串操作: " . ($analysis['has_string_operations'] ? '是' : '否') . "\n";
    echo "   - 数组操作: " . ($analysis['has_array_operations'] ? '是' : '否') . "\n";
    echo "   - 方法调用数: {$analysis['delegation_calls']}\n";
    echo "   - 返回语句数: {$analysis['return_statements']}\n";
    
    if ($isSimpleDelegation) {
        echo "   ✅ 简单委托调用 - 符合要求\n";
    } else {
        echo "   ⚠️  可能包含业务逻辑\n";
        
        // 显示函数代码供检查
        echo "   📋 函数代码:\n";
        $lines = explode("\n", $functionCode);
        foreach ($lines as $i => $line) {
            if (trim($line)) {
                echo "      " . ($startLine + $i) . ": " . $line . "\n";
            }
        }
    }
    
    echo "\n";
    return $isSimpleDelegation;
}

// 检查主要的 helpers 函数
$helpersToCheck = array(
    'cache_kv_get',
    'cache_kv_get_multiple', 
    'cache_kv_make_key',
    'cache_kv_make_keys',
    'cache_kv_get_keys',
    'cache_kv_delete_by_prefix',
    'cache_kv_get_stats',
    'cache_kv_get_hot_keys'
);

$allSimple = true;
$results = array();

foreach ($helpersToCheck as $functionName) {
    $isSimple = analyzeFunction($functionName);
    $results[$functionName] = $isSimple;
    if (!$isSimple) {
        $allSimple = false;
    }
}

echo "=== 检查总结 ===\n\n";

foreach ($results as $functionName => $isSimple) {
    $status = $isSimple ? '✅' : '❌';
    echo "{$status} {$functionName}\n";
}

echo "\n";

if ($allSimple) {
    echo "🎉 所有 helpers 函数都是简单委托调用！\n\n";
    
    echo "✅ 设计原则确认:\n";
    echo "1. ✅ 无业务逻辑 - helpers 只做简单委托\n";
    echo "2. ✅ 单一职责 - 每个函数只调用一个方法\n";
    echo "3. ✅ 代码简洁 - 函数体保持在5行以内\n";
    echo "4. ✅ 层次清晰 - 业务逻辑在相应的类中实现\n\n";
    
    echo "🎯 架构优势:\n";
    echo "- 📦 职责分离: helpers 专注于提供便捷接口\n";
    echo "- 🔧 易于维护: 业务逻辑集中在类中管理\n";
    echo "- 🛡️ 降低耦合: helpers 不依赖具体实现细节\n";
    echo "- 📋 便于测试: 可以独立测试业务逻辑和接口层\n";
    
} else {
    echo "⚠️  部分 helpers 函数可能包含业务逻辑，需要重构:\n";
    foreach ($results as $functionName => $isSimple) {
        if (!$isSimple) {
            echo "- {$functionName}\n";
        }
    }
    
    echo "\n💡 重构建议:\n";
    echo "1. 将业务逻辑移到相应的类中\n";
    echo "2. helpers 函数只保留简单的委托调用\n";
    echo "3. 确保每个 helper 函数只调用一个方法\n";
    echo "4. 保持函数体简洁（5行以内）\n";
}

echo "\n=== 代码示例 ===\n";

echo "✅ 正确的 helper 函数设计:\n";
echo "```php\n";
echo "function cache_kv_make_key(\$template, array \$params = array())\n";
echo "{\n";
echo "    // 委托给 KeyManager 处理，不包含业务逻辑\n";
echo "    return \\Asfop\\CacheKV\\Key\\KeyManager::getInstance()->createKeyFromTemplate(\$template, \$params);\n";
echo "}\n";
echo "```\n\n";

echo "❌ 错误的 helper 函数设计:\n";
echo "```php\n";
echo "function cache_kv_make_key(\$template, array \$params = array())\n";
echo "{\n";
echo "    // 包含业务逻辑 - 应该避免\n";
echo "    \$parts = explode('.', \$template, 2);\n";
echo "    if (count(\$parts) !== 2) {\n";
echo "        throw new \\InvalidArgumentException(\"Invalid template\");\n";
echo "    }\n";
echo "    return KeyManager::getInstance()->createKey(\$parts[0], \$parts[1], \$params);\n";
echo "}\n";
echo "```\n";

?>
