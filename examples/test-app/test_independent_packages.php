<?php

/**
 * 独立包测试应用
 * 
 * 演示如何在应用项目中正确使用多个独立的包
 */

echo "=== 独立包测试应用 ===\n\n";

echo "🎯 这个演示说明了正确的包使用方式：\n\n";

echo "1. 项目结构：\n";
echo "   test-app/                    # 应用项目\n";
echo "   ├── composer.json            # type: \"project\"\n";
echo "   ├── test_independent_packages.php\n";
echo "   └── vendor/                  # 独立的依赖\n";
echo "       ├── asfop/constants/     # 第一个包\n";
echo "       └── asfop1/cache-kv/     # 第二个包\n\n";

echo "2. 正确的安装方式：\n";
echo "   cd examples/test-app\n";
echo "   composer require asfop/constants        # 安装第一个包\n";
echo "   composer require asfop1/cache-kv:dev-main  # 安装第二个包\n\n";

echo "3. 为什么这样是正确的：\n";
echo "   ✅ 在应用项目中安装，不是在库项目中\n";
echo "   ✅ 两个包都是应用的直接依赖\n";
echo "   ✅ 它们互不影响，完全独立\n";
echo "   ✅ 可以自由添加、移除任何一个包\n\n";

echo "4. 之前的问题：\n";
echo "   ❌ 在库项目 (asfop1/cache-kv) 中运行 composer require\n";
echo "   ❌ 这会将包添加到库的依赖中\n";
echo "   ❌ 强制所有使用库的项目都安装这个包\n";
echo "   ❌ 违反了依赖管理的最佳实践\n\n";

// 检查包是否正确加载
echo "5. 包加载检查：\n";

// 检查 autoload 文件
$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
    echo "   ✅ Composer autoload 文件存在\n";
} else {
    echo "   ❌ Composer autoload 文件不存在\n";
    echo "   请先运行: composer install\n";
    exit(1);
}

// 检查 asfop1/cache-kv
if (class_exists('Asfop\CacheKV\Core\CacheKVFactory')) {
    echo "   ✅ asfop1/cache-kv 包加载成功\n";
} else {
    echo "   ❌ asfop1/cache-kv 包未找到\n";
}

// 检查 cache_kv 辅助函数
if (function_exists('cache_kv_make_key')) {
    echo "   ✅ cache_kv 辅助函数可用\n";
} else {
    echo "   ❌ cache_kv 辅助函数不可用\n";
}

// 检查 asfop/constants（如果安装了的话）
// 注意：这里我们不能假设这个包一定存在，因为它可能没有被安装
$constantsInstalled = false;
$vendorDir = __DIR__ . '/vendor';
if (is_dir($vendorDir . '/asfop/constants')) {
    $constantsInstalled = true;
    echo "   ✅ asfop/constants 包已安装\n";
} else {
    echo "   ℹ️  asfop/constants 包未安装（这是正常的）\n";
}

echo "\n6. 使用示例：\n";

try {
    // 使用 cache_kv 功能
    $key = cache_kv_make_key('test.demo', ['id' => 123]);
    echo "   生成的缓存键: " . (string)$key . "\n";
    
    // 如果 constants 包存在，也可以使用它
    if ($constantsInstalled) {
        echo "   asfop/constants 包也可以正常使用\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ 使用时出错: " . $e->getMessage() . "\n";
}

echo "\n7. 安装说明：\n";
echo "   要在这个测试应用中使用两个包：\n\n";
echo "   cd examples/test-app\n";
echo "   composer install                        # 安装基础依赖\n";
echo "   composer require asfop/constants        # 可选：安装 constants 包\n\n";

echo "   这样两个包就完全独立了！\n";
echo "   ✅ 可以单独安装任何一个\n";
echo "   ✅ 可以单独移除任何一个\n";
echo "   ✅ 它们不会互相影响\n\n";

echo "🎉 这就是正确的包管理方式！\n";

?>
