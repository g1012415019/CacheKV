# 测试应用项目

这个目录演示了如何在应用项目中正确使用多个独立的包。

## 🎯 目的

解决这个问题：为什么在库项目中运行 `composer require` 会导致包之间互相影响？

## 📁 项目结构

```
test-app/                           # 应用项目 (type: "project")
├── composer.json                   # 应用的依赖配置
├── README.md                       # 说明文档
├── test_independent_packages.php   # 测试脚本
└── vendor/                         # 独立的依赖目录
    ├── asfop/constants/            # 第一个包（可选）
    └── asfop1/cache-kv/            # 第二个包
```

## 🚀 使用方法

### 1. 安装基础依赖

```bash
cd examples/test-app
composer install
```

### 2. 测试 cache-kv 包

```bash
php test_independent_packages.php
```

### 3. 添加其他独立包（可选）

```bash
# 安装 constants 包
composer require asfop/constants

# 或者安装其他任何包
composer require monolog/monolog
composer require guzzlehttp/guzzle
```

### 4. 验证包的独立性

```bash
# 查看当前安装的包
composer show --direct

# 移除某个包，不会影响其他包
composer remove asfop/constants

# cache-kv 包仍然正常工作
php test_independent_packages.php
```

## ✅ 正确 vs ❌ 错误的做法

### ✅ 正确：在应用项目中安装

```bash
# 在应用项目目录中
cd my-app/                          # 应用项目
composer require asfop/constants    # 添加到应用依赖
composer require asfop1/cache-kv    # 添加到应用依赖
```

**结果**：两个包都是应用的直接依赖，完全独立。

### ❌ 错误：在库项目中安装

```bash
# 在库项目目录中
cd asfop1/cache-kv/                 # 库项目
composer require asfop/constants    # 添加到库依赖 ❌
```

**结果**：`asfop/constants` 变成 `asfop1/cache-kv` 的依赖，所有使用 cache-kv 的项目都会被迫安装 constants。

## 🔍 为什么会这样？

### 库项目 vs 应用项目

| 特征 | 库项目 | 应用项目 |
|------|--------|----------|
| **类型** | `"type": "library"` | `"type": "project"` |
| **目的** | 被其他项目使用 | 最终的可执行应用 |
| **依赖策略** | 尽可能少的依赖 | 可以有很多依赖 |
| **使用方式** | 被 require | 直接运行 |

### Composer 行为差异

在库项目中运行 `composer require`：
- 将包添加到库的 `require` 中
- 所有使用这个库的项目都会安装这个包
- 增加了库的依赖负担

在应用项目中运行 `composer require`：
- 将包添加到应用的 `require` 中
- 只影响当前应用
- 包之间完全独立

## 🎯 最佳实践

1. **库开发**：在库项目中只添加必要的运行时依赖
2. **应用开发**：在应用项目中自由添加所需的包
3. **测试**：创建独立的测试应用来验证包的组合使用
4. **依赖管理**：明确区分库依赖和应用依赖

## 📚 相关文档

- [库项目 vs 应用项目的 Composer 行为差异](../docs/LIBRARY_VS_APPLICATION_COMPOSER.md)
- [Composer 依赖删除问题深度分析](../docs/COMPOSER_DEPENDENCY_ANALYSIS.md)
- [安装指南](../docs/INSTALLATION.md)
