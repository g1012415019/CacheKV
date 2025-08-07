# Composer 依赖删除问题深度分析

## 🔍 问题现象

当执行以下命令时：
```bash
composer require asfop1/cache-kv:dev-main --prefer-source
```

会导致之前安装的 `asfop/constants` 被删除。

## 🎯 根本原因

### 1. Composer 的依赖解析机制

Composer 使用 **SAT solver**（布尔可满足性问题求解器）来解析依赖关系：

```
当你运行 composer require 时：
1. 读取当前 composer.json 和 composer.lock
2. 添加新的依赖要求
3. 重新计算整个依赖图
4. 移除所有"孤立"的包（没有被依赖的包）
5. 安装/更新必要的包
```

### 2. "孤立包"的定义

一个包被认为是"孤立"的，当且仅当：
- 它不在 `composer.json` 的 `require` 或 `require-dev` 中
- 没有其他已安装的包依赖它
- 不是任何包的传递依赖

### 3. `asfop/constants` 被删除的具体原因

假设你之前手动安装了 `asfop/constants`：
```bash
composer require asfop/constants
```

这时 `composer.json` 变成：
```json
{
    "require": {
        "php": ">=7.0",
        "ext-redis": "*",
        "asfop/constants": "^1.0"
    }
}
```

但是当你安装 `asfop1/cache-kv:dev-main` 时：

1. **Composer 检查 `asfop1/cache-kv` 的依赖**：
   ```json
   // asfop1/cache-kv 的 composer.json
   {
       "require": {
           "php": ">=7.0",
           "ext-redis": "*"
           // 注意：没有 asfop/constants
       }
   }
   ```

2. **重新计算依赖图**：
   - `asfop1/cache-kv` 不依赖 `asfop/constants`
   - 没有其他包依赖 `asfop/constants`
   - `asfop/constants` 变成"孤立包"

3. **清理孤立包**：
   - Composer 认为 `asfop/constants` 不再需要
   - 自动移除它

## 🔧 为什么会这样设计？

### 1. 依赖管理的一致性
```bash
# 这两个命令应该产生相同的结果
composer install
composer require package-a package-b && composer install
```

### 2. 避免依赖污染
```bash
# 防止这种情况：
composer require temp-package  # 临时安装
composer remove temp-package   # 移除
# temp-package 的依赖不应该残留
```

### 3. 确保可重现的构建
```bash
# 基于 composer.json 重建项目时，结果应该一致
rm -rf vendor composer.lock
composer install
```

## 📊 详细的执行流程

让我们追踪一下具体发生了什么：

### 步骤1：初始状态
```json
// composer.json
{
    "require": {
        "asfop/constants": "^1.0"
    }
}

// 已安装的包
vendor/
├── asfop/constants/
└── ...
```

### 步骤2：执行 composer require
```bash
composer require asfop1/cache-kv:dev-main --prefer-source
```

### 步骤3：Composer 内部处理
```
1. 解析 asfop1/cache-kv:dev-main 的依赖
   ├── php: >=7.0 ✓
   ├── ext-redis: * ✓
   └── (没有其他依赖)

2. 构建新的依赖图
   ├── asfop1/cache-kv:dev-main (新增)
   ├── php: >=7.0 (系统)
   ├── ext-redis: * (系统)
   └── asfop/constants: ^1.0 (孤立!)

3. 检查孤立包
   └── asfop/constants 没有被任何包依赖 → 标记为删除

4. 更新 composer.json
   {
       "require": {
           "asfop1/cache-kv": "dev-main"
       }
   }

5. 执行安装/删除操作
   ├── 安装 asfop1/cache-kv:dev-main
   └── 删除 asfop/constants (孤立包)
```

## 🚨 --prefer-source 的影响

`--prefer-source` 选项会：

1. **从 Git 仓库克隆源码**而不是下载 zip 包
2. **触发更彻底的依赖重新计算**
3. **可能暴露一些平时被忽略的依赖问题**

```bash
# 这两个命令的行为可能不同
composer require asfop1/cache-kv:dev-main           # 下载 zip
composer require asfop1/cache-kv:dev-main --prefer-source  # Git 克隆
```

## 🔧 解决方案对比

### 方案1：明确声明依赖（推荐）
```json
{
    "require": {
        "asfop/constants": "^1.0",
        "asfop1/cache-kv": "dev-main"
    }
}
```
**优点**：依赖关系明确，不会被意外删除
**缺点**：需要手动维护

### 方案2：分步安装
```bash
composer require asfop/constants
composer require asfop1/cache-kv:dev-main
```
**优点**：简单直接
**缺点**：每次都需要记住顺序

### 方案3：使用 composer.lock
```bash
# 先锁定当前状态
composer install

# 再添加新包
composer require asfop1/cache-kv:dev-main
```
**优点**：保护现有依赖
**缺点**：可能导致版本冲突

## 🎯 最佳实践

### 1. 明确声明所有直接依赖
```json
{
    "require": {
        "package-you-use-directly": "^1.0",
        "another-package-you-use": "^2.0"
    }
}
```

### 2. 不要依赖传递依赖
```php
// ❌ 错误：直接使用传递依赖
use SomePackage\TransitiveDependency\Class;

// ✅ 正确：明确声明依赖
// composer require some-package/transitive-dependency
use SomePackage\TransitiveDependency\Class;
```

### 3. 定期检查依赖
```bash
# 查看依赖树
composer show --tree

# 查看为什么安装了某个包
composer why package-name

# 查看为什么没有安装某个包
composer why-not package-name
```

## 🔍 调试技巧

### 1. 查看详细输出
```bash
composer require package-name -vvv
```

### 2. 模拟安装（不实际执行）
```bash
composer require package-name --dry-run
```

### 3. 查看依赖原因
```bash
composer depends package-name
composer why package-name
```

### 4. 分析依赖冲突
```bash
composer why-not package-name:version
```

## 📝 总结

`composer require asfop1/cache-kv:dev-main --prefer-source` 删除 `asfop/constants` 是 Composer 正常的依赖管理行为，不是 bug。这确保了：

1. **依赖的一致性**：只安装真正需要的包
2. **构建的可重现性**：基于 composer.json 能重建相同环境
3. **避免依赖污染**：不会残留不需要的包

**解决方法**：在 `composer.json` 中明确声明所有直接使用的依赖。
