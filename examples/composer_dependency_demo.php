<?php

/**
 * Composer 依赖删除问题演示
 * 
 * 这个脚本演示为什么 composer require 会删除其他包
 */

echo "=== Composer 依赖删除问题演示 ===\n\n";

echo "🔍 问题场景重现：\n\n";

echo "1. 初始状态：\n";
echo "   composer.json:\n";
echo "   {\n";
echo "       \"require\": {\n";
echo "           \"asfop/constants\": \"^1.0\"\n";
echo "       }\n";
echo "   }\n\n";

echo "2. 执行命令：\n";
echo "   composer require asfop1/cache-kv:dev-main --prefer-source\n\n";

echo "3. Composer 内部处理过程：\n\n";

echo "   步骤1: 解析新包的依赖\n";
echo "   ┌─ asfop1/cache-kv:dev-main\n";
echo "   ├─ php: >=7.0 ✓\n";
echo "   ├─ ext-redis: * ✓\n";
echo "   └─ (没有其他依赖)\n\n";

echo "   步骤2: 重新构建依赖图\n";
echo "   当前需要的包：\n";
echo "   ├─ asfop1/cache-kv:dev-main (新增)\n";
echo "   ├─ php: >=7.0 (系统)\n";
echo "   ├─ ext-redis: * (系统)\n";
echo "   └─ asfop/constants: ^1.0 (❌ 孤立包!)\n\n";

echo "   步骤3: 检查孤立包\n";
echo "   asfop/constants 分析：\n";
echo "   ├─ 不在新的 require 列表中 ❌\n";
echo "   ├─ 没有被 asfop1/cache-kv 依赖 ❌\n";
echo "   ├─ 没有被其他包依赖 ❌\n";
echo "   └─ 结论：标记为删除\n\n";

echo "   步骤4: 更新 composer.json\n";
echo "   {\n";
echo "       \"require\": {\n";
echo "           \"asfop1/cache-kv\": \"dev-main\"\n";
echo "       }\n";
echo "   }\n\n";

echo "   步骤5: 执行操作\n";
echo "   ├─ 安装 asfop1/cache-kv:dev-main ✓\n";
echo "   └─ 删除 asfop/constants ❌\n\n";

echo "🎯 为什么会这样？\n\n";

echo "这是 Composer 的设计原则：\n";
echo "1. 📦 只保留真正需要的包\n";
echo "2. 🔄 确保构建的可重现性\n";
echo "3. 🧹 避免依赖污染\n";
echo "4. 📋 基于 composer.json 重建时结果一致\n\n";

echo "🔧 解决方案对比：\n\n";

echo "方案1: 明确声明依赖（推荐）\n";
echo "{\n";
echo "    \"require\": {\n";
echo "        \"asfop/constants\": \"^1.0\",\n";
echo "        \"asfop1/cache-kv\": \"dev-main\"\n";
echo "    }\n";
echo "}\n";
echo "✅ 优点：依赖关系明确，不会被删除\n";
echo "❌ 缺点：需要手动维护\n\n";

echo "方案2: 分步安装\n";
echo "composer require asfop/constants\n";
echo "composer require asfop1/cache-kv:dev-main\n";
echo "✅ 优点：简单直接\n";
echo "❌ 缺点：需要记住顺序\n\n";

echo "方案3: 同时安装\n";
echo "composer require asfop/constants asfop1/cache-kv:dev-main\n";
echo "✅ 优点：一次性解决\n";
echo "❌ 缺点：需要知道所有依赖\n\n";

echo "🚨 --prefer-source 的特殊影响：\n\n";

echo "--prefer-source 选项会：\n";
echo "1. 从 Git 仓库克隆源码（而不是下载 zip）\n";
echo "2. 触发更彻底的依赖重新计算\n";
echo "3. 可能暴露平时被忽略的依赖问题\n";
echo "4. 执行更严格的依赖清理\n\n";

echo "对比：\n";
echo "composer require pkg:dev-main           # 可能保留一些包\n";
echo "composer require pkg:dev-main --prefer-source  # 更严格的清理\n\n";

echo "🎯 最佳实践：\n\n";

echo "1. 明确声明所有直接使用的依赖\n";
echo "2. 不要依赖传递依赖\n";
echo "3. 定期检查依赖关系\n";
echo "4. 使用 composer why 命令调试\n\n";

echo "🔍 调试命令：\n\n";

echo "# 查看依赖树\n";
echo "composer show --tree\n\n";

echo "# 查看为什么安装了某个包\n";
echo "composer why package-name\n\n";

echo "# 查看为什么没有安装某个包\n";
echo "composer why-not package-name\n\n";

echo "# 模拟安装（不实际执行）\n";
echo "composer require package-name --dry-run\n\n";

echo "# 详细输出\n";
echo "composer require package-name -vvv\n\n";

echo "📝 结论：\n\n";

echo "这不是 bug，而是 Composer 正常的依赖管理行为。\n";
echo "它确保了依赖的一致性和构建的可重现性。\n";
echo "解决方法是在 composer.json 中明确声明所有直接依赖。\n\n";

echo "🎉 现在你明白为什么会发生这种情况了！\n";

?>
