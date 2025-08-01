# CacheKV 使用示例

本目录包含了 CacheKV 的各种使用示例，帮助您快速上手和深入了解 CacheKV 的功能。

## 示例列表

### 🔑 Key 管理示例

#### 1. [key-management-quickstart.php](key-management-quickstart.php)
**快速入门示例** - 5分钟了解 Key 管理的基本用法

```bash
php examples/key-management-quickstart.php
```

**包含内容：**
- 基本键生成和使用
- 批量操作
- 自定义模板
- 键解析和验证
- 模式匹配

#### 2. [key-management-example.php](key-management-example.php)
**完整功能示例** - 深入了解 Key 管理的所有功能

```bash
php examples/key-management-example.php
```

**包含内容：**
- 基本 Key 生成
- 复杂参数的 Key 生成
- 在缓存操作中使用 Key 管理
- 批量 Key 操作
- Key 模式匹配（用于批量清理）
- Key 解析和验证
- 动态模板管理
- 实际业务场景示例
- 缓存统计和监控

## 运行示例

### 环境要求
- PHP 7.0 或更高版本
- 已安装 CacheKV 依赖

### 运行方法

1. **进入项目根目录**
   ```bash
   cd /path/to/cachekv
   ```

2. **运行快速入门示例**
   ```bash
   php examples/key-management-quickstart.php
   ```

3. **运行完整功能示例**
   ```bash
   php examples/key-management-example.php
   ```

## 示例说明

### Key 管理的核心价值

CacheKV 的 KeyManager 解决了缓存使用中的关键问题：

- ✅ **统一命名规范** - 避免键名混乱和冲突
- ✅ **环境隔离** - 自动处理不同环境的键前缀
- ✅ **模板化管理** - 预定义和自定义键模板
- ✅ **批量操作支持** - 简化批量缓存操作
- ✅ **键解析验证** - 完整的键管理功能

### 实际应用场景

示例中展示了以下实际应用场景：

1. **用户数据缓存** - 标准化的用户信息缓存
2. **产品信息缓存** - 电商系统中的产品数据缓存
3. **API 响应缓存** - 外部 API 调用结果缓存
4. **批量数据操作** - 高效的批量缓存处理
5. **购物车缓存** - 用户购物车数据管理

### 键命名规范

示例中使用的键命名遵循以下规范：

```
{app_prefix}:{env_prefix}:{version}:{business_key}
```

例如：
- `myapp:prod:v1:user:123` - 生产环境用户数据
- `myapp:dev:v1:product:456` - 开发环境产品数据
- `myapp:test:v2:cart:789` - 测试环境购物车数据

## 相关文档

- [Key 管理详细文档](../docs/key-management.md) - 完整的 Key 管理指南
- [API 参考文档](../docs/api-reference.md) - 详细的 API 文档
- [使用指南](../docs/usage-guide.md) - 全面的使用教程

## 下一步

1. **运行示例** - 先运行快速入门示例了解基本用法
2. **阅读文档** - 查看详细文档了解更多功能
3. **实际应用** - 在您的项目中应用 Key 管理功能
4. **自定义扩展** - 根据业务需求添加自定义模板

## 问题反馈

如果您在运行示例时遇到问题，或者有任何建议，请：

1. 检查 PHP 版本和依赖是否正确安装
2. 查看错误信息和日志
3. 参考相关文档
4. 提交 Issue 或 Pull Request

---

**开始探索 CacheKV 的强大功能吧！** 🚀
