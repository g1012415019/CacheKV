# CacheKV 示例文件说明

本目录包含了 CacheKV 的各种使用示例，所有示例都已更新为使用新的简化模式。

## 📁 文件说明

### 🎯 推荐入门顺序

1. **[simplified-usage.php](simplified-usage.php)** - 最简单的使用方式
   - 展示辅助函数的使用
   - 快速创建独立实例
   - 批量操作示例

2. **[best-practices.php](best-practices.php)** - 最佳实践指南
   - 全局配置 + 辅助函数（推荐）
   - 多环境配置
   - 实际业务场景示例

3. **[factory-usage.php](factory-usage.php)** - 工厂模式详解
   - 快速创建方式
   - 配置式创建
   - 多实例管理

### 🔧 功能专题示例

4. **[key-management-quickstart.php](key-management-quickstart.php)** - 键管理快速入门
   - 快速创建方式
   - 全局配置方式
   - 键管理详细示例

5. **[key-management-example.php](key-management-example.php)** - 键管理高级功能
   - 复杂键模板配置
   - 业务服务集成
   - 实际电商场景

6. **[project-integration-example.php](project-integration-example.php)** - 项目集成示例
   - 工厂模式集成
   - 门面模式集成
   - 多环境配置
   - 批量操作

## 🚀 使用方式对比

### ❌ 旧方式（繁琐）
```php
// 每次都要手动创建
$keyManager = new KeyManager([
    'app_prefix' => 'test',
    'env_prefix' => 'dev',
    'version' => 'v1',
    'templates' => [
        'user' => 'user:{id}',
        // ...
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 60, $keyManager);
```

### ✅ 新方式（简洁）

#### 方案1：辅助函数（推荐）
```php
// 一次配置
CacheKVFactory::setDefaultConfig([...]);

// 任何地方直接使用
$user = cache_kv_get('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
});
```

#### 方案2：快速创建
```php
// 一行代码创建
$cache = cache_kv_quick('myapp', 'dev', [
    'user' => 'user:{id}',
    'product' => 'product:{id}',
]);
```

#### 方案3：工厂模式
```php
$cache = CacheKVFactory::create();
$user = $cache->getByTemplate('user', ['id' => 123], $callback);
```

## 🎯 选择建议

| 项目类型 | 推荐方案 | 示例文件 |
|---------|---------|---------|
| **简单项目** | 快速创建 | `simplified-usage.php` |
| **中型项目** | 全局配置 + 辅助函数 | `best-practices.php` |
| **大型项目** | 工厂模式 + 业务服务 | `project-integration-example.php` |
| **企业项目** | 门面模式 + 依赖注入 | `project-integration-example.php` |

## 🔍 快速测试

运行任何示例文件：

```bash
# 最简单的使用方式
php examples/simplified-usage.php

# 最佳实践指南
php examples/best-practices.php

# 项目集成示例
php examples/project-integration-example.php

# 键管理示例
php examples/key-management-example.php
```

## ✨ 新模式优势

1. **消除重复代码** - 不再需要每次手动创建 KeyManager 和 CacheKV
2. **使用更简洁** - 提供辅助函数，一行代码搞定
3. **配置更灵活** - 支持全局配置、快速创建、多实例管理
4. **符合标准** - 完全符合 Composer PSR-4 标准
5. **向后兼容** - 原有使用方式仍然可用

## 📚 相关文档

- [快速开始](../docs/quick-start.md)
- [核心功能](../docs/core-features.md)
- [API 参考](../docs/api-reference.md)
- [主项目 README](../README.md)
