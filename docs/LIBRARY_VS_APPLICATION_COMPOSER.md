# 库项目 vs 应用项目的 Composer 行为差异

## 🚨 问题发现

当你在 `asfop1/cache-kv` 项目目录内运行：
```bash
composer require asfop/constants
```

会出现意外的行为，可能移除其他包。

## 🎯 根本原因

### 1. 项目类型的区别

**库项目 (Library)**：
- `"type": "library"` 在 composer.json 中
- 主要目的是被其他项目使用
- 依赖应该尽可能少
- 通常不直接运行

**应用项目 (Application)**：
- `"type": "project"` 或没有 type 字段
- 最终的可执行应用
- 可以有很多依赖
- 直接运行的项目

### 2. 当前项目状态

```json
{
    "name": "asfop1/cache-kv",
    "type": "library",  // ← 这是一个库项目！
    "require": {
        "php": ">=7.0",
        "ext-redis": "*"
    }
}
```

### 3. 在库项目中运行 composer require 的问题

当你在库项目中运行：
```bash
composer require asfop/constants
```

Composer 会：
1. **将 asfop/constants 添加到库的依赖中**
2. **这意味着所有使用这个库的项目都会被迫安装 asfop/constants**
3. **这通常不是你想要的结果**

## 🔍 实际场景分析

### 场景1：在库项目中测试（当前情况）
```bash
# 你在 /path/to/asfop1/cache-kv/ 目录中
pwd  # /Users/gongzhe/development/has-one

# 运行这个命令
composer require asfop/constants
```

**结果**：
- `asfop/constants` 被添加到 `asfop1/cache-kv` 的依赖中
- 所有使用 `asfop1/cache-kv` 的项目都会安装 `asfop/constants`
- 这不是你想要的！

### 场景2：在应用项目中使用（正确方式）
```bash
# 创建一个新的应用项目
mkdir my-app
cd my-app
composer init

# 安装两个独立的包
composer require asfop/constants
composer require asfop1/cache-kv:dev-main
```

**结果**：
- 两个包都安装在应用项目中
- 它们是独立的，不会互相影响
- 这是正确的使用方式！

## 🔧 解决方案

### 方案1：创建测试应用项目（推荐）

```bash
# 在 cache-kv 项目外创建测试项目
cd ..
mkdir cache-kv-test-app
cd cache-kv-test-app

# 初始化应用项目
composer init --name="test/cache-kv-app" --type="project"

# 安装依赖
composer require asfop/constants
composer require asfop1/cache-kv:dev-main --prefer-source

# 或者指向本地开发版本
composer config repositories.local path ../has-one
composer require asfop1/cache-kv:dev-main
```

### 方案2：使用 examples 目录

在 `asfop1/cache-kv` 项目中创建独立的测试环境：

```bash
# 在 cache-kv 项目中
mkdir examples/test-app
cd examples/test-app

# 创建独立的 composer.json
cat > composer.json << 'EOF'
{
    "name": "test/cache-kv-app",
    "type": "project",
    "require": {
        "php": ">=7.0"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../../"
        }
    ]
}
EOF

# 安装依赖
composer require asfop/constants
composer require asfop1/cache-kv:dev-main
```

### 方案3：使用 require-dev（仅开发时）

如果你确实需要在库项目中使用某些包进行开发测试：

```json
{
    "require": {
        "php": ">=7.0",
        "ext-redis": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^6.0|^7.0|^8.0|^9.0",
        "asfop/constants": "^1.0"  // 仅开发时需要
    }
}
```

## 📊 行为对比

| 操作位置 | 命令 | 结果 | 是否正确 |
|---------|------|------|----------|
| 库项目内 | `composer require asfop/constants` | 添加到库的依赖 | ❌ 通常不对 |
| 库项目内 | `composer require --dev asfop/constants` | 添加到开发依赖 | ✅ 可以接受 |
| 应用项目内 | `composer require asfop/constants asfop1/cache-kv` | 两个独立依赖 | ✅ 正确 |

## 🎯 最佳实践

### 1. 库项目开发
```bash
# 在库项目中，只添加必要的运行时依赖
composer require necessary-runtime-dependency

# 开发和测试依赖使用 --dev
composer require --dev phpunit/phpunit
composer require --dev development-tool
```

### 2. 应用项目使用
```bash
# 在应用项目中，自由添加所需依赖
composer require asfop/constants
composer require asfop1/cache-kv
composer require any-other-package
```

### 3. 测试库项目
```bash
# 方法1：创建独立测试项目
mkdir test-app && cd test-app
composer init --type=project
composer require your-library other-dependencies

# 方法2：使用 examples 目录
mkdir examples/demo && cd examples/demo
# 创建独立的 composer.json 和测试代码
```

## 🔍 调试和验证

### 检查项目类型
```bash
# 查看当前项目类型
grep -A5 -B5 '"type"' composer.json

# 查看当前依赖
composer show --direct
```

### 验证依赖关系
```bash
# 查看为什么安装了某个包
composer why package-name

# 查看依赖树
composer show --tree
```

## 📝 总结

你遇到的问题是因为：

1. **在库项目内运行 composer require**
2. **库项目和应用项目的依赖管理逻辑不同**
3. **应该在应用项目中安装和测试多个独立的包**

**解决方案**：创建一个独立的应用项目来测试 `asfop/constants` 和 `asfop1/cache-kv` 的组合使用。

这样两个包就真正独立了，不会互相影响！
