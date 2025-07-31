# CacheKV 文档中心

欢迎来到 CacheKV 文档中心！这里包含了使用 CacheKV 所需的所有文档。

## 🎯 CacheKV 是什么？

CacheKV 是一个专注于简化缓存操作的 PHP 库，**核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式**。该库支持单条及批量数据操作、基于标签的缓存失效管理，并提供基础的性能统计功能。

## 📚 文档导航

### 🚀 新手入门（推荐阅读顺序）

1. **[入门指南](getting-started.md)** - 快速上手 CacheKV
   - 什么是 CacheKV？
   - 安装和基本使用
   - 三大核心功能介绍
   - 实际应用场景

2. **[核心功能详解](core-features.md)** - 深入了解实现原理
   - 自动回填缓存的实现机制
   - 批量操作的智能处理
   - 标签系统的设计原理

3. **[使用指南](usage-guide.md)** - 实际项目应用
   - 详细的使用教程
   - 真实应用场景示例
   - 性能监控和优化
   - 最佳实践总结

4. **[API 参考文档](api-reference.md)** - 完整的方法说明
   - 所有公共方法的详细说明
   - 参数和返回值说明
   - 使用示例和错误处理

### 🏗️ 深入了解

- **[架构文档](architecture.md)** - 系统设计和架构
  - 整体架构图
  - 核心组件说明
  - 设计模式应用
  - 扩展性设计

### 🔧 框架集成

- **[Laravel 集成](laravel-integration.md)** - 在 Laravel 中使用 CacheKV
- **[ThinkPHP 集成](thinkphp-integration.md)** - 在 ThinkPHP 中使用 CacheKV
- **[Webman 集成](webman-integration.md)** - 在 Webman 中使用 CacheKV

### 💡 实际应用案例

- **[用户信息缓存](user-info-caching.md)** - 用户数据缓存最佳实践
- **[批量产品查询缓存](batch-product-query.md)** - 电商系统中的批量查询优化
- **[外部 API 缓存](external-api-caching.md)** - 第三方 API 调用缓存策略
- **[基于标签的失效](tag-based-invalidation.md)** - 标签系统的高级用法
- **[缓存穿透预防](cache-penetration-prevention.md)** - 防止缓存穿透的策略
- **[滑动过期](sliding-expiration.md)** - 滑动过期机制的使用

## 🎯 核心概念速览

### CacheKV 的三大核心功能

1. **🎯 自动回填缓存** - 简化"若无则从数据源获取并回填缓存"的常见模式
   ```php
   $user = $cache->get('user:123', function() {
       return getUserFromDatabase(123);
   });
   ```

2. **📦 批量数据操作** - 支持单条及批量数据的智能处理
   ```php
   $users = $cache->getMultiple($userIds, function($missingIds) {
       return getUsersByIds($missingIds);
   });
   ```

3. **🏷️ 基于标签的缓存失效管理** - 轻松管理相关缓存的批量清理
   ```php
   $cache->setWithTag('user:1', $userData, ['users', 'vip_users']);
   $cache->clearTag('users'); // 一次清除所有用户缓存
   ```

### 主要优势

- ✅ **代码简化** - 一行代码解决复杂的缓存逻辑
- ✅ **性能优化** - 批量操作减少数据库查询
- ✅ **防止穿透** - 自动缓存 null 值防止缓存穿透
- ✅ **智能管理** - 基于标签的缓存分组和批量清理
- ✅ **统计监控** - 内置性能统计功能

## 🔍 快速查找

### 按使用场景查找

| 我想... | 推荐文档 |
|---------|----------|
| 快速开始使用 CacheKV | [入门指南](getting-started.md) |
| 了解实现原理 | [核心功能详解](core-features.md) |
| 在实际项目中应用 | [使用指南](usage-guide.md) |
| 查找具体方法用法 | [API 参考文档](api-reference.md) |
| 了解系统架构 | [架构文档](architecture.md) |
| 集成到框架中 | [Laravel](laravel-integration.md) / [ThinkPHP](thinkphp-integration.md) / [Webman](webman-integration.md) |
| 解决具体问题 | 查看对应的应用案例文档 |

### 按功能查找

| 功能 | 相关文档 |
|------|----------|
| 自动回填缓存 | [入门指南](getting-started.md#🎯-1-自动回填缓存核心功能) + [核心功能详解](core-features.md#1-自动回填缓存核心功能) |
| 批量操作 | [入门指南](getting-started.md#批量数据获取) + [批量产品查询](batch-product-query.md) |
| 标签管理 | [入门指南](getting-started.md#🏷️-2-基于标签的缓存失效管理) + [基于标签的失效](tag-based-invalidation.md) |
| 性能统计 | [入门指南](getting-started.md#📊-3-性能统计功能) + [使用指南](usage-guide.md#性能监控和优化) |
| 缓存穿透防护 | [缓存穿透预防](cache-penetration-prevention.md) |
| 滑动过期 | [滑动过期](sliding-expiration.md) |

## 📖 阅读建议

### 🔰 初学者路径
1. [入门指南](getting-started.md) - 了解基本概念和使用方法
2. [使用指南](usage-guide.md) - 学习实际应用
3. [API 参考文档](api-reference.md) - 查阅具体方法

### 🔧 开发者路径
1. [核心功能详解](core-features.md) - 理解实现原理
2. [架构文档](architecture.md) - 了解系统设计
3. [使用指南](usage-guide.md) - 掌握最佳实践

### 🚀 项目集成路径
1. [入门指南](getting-started.md) - 快速上手
2. 选择对应的框架集成文档
3. [使用指南](usage-guide.md) - 优化和监控

## 📋 文档状态

| 文档 | 状态 | 最后更新 | 说明 |
|------|------|----------|------|
| 入门指南 | ✅ 完整 | 2024-07-31 | 与 README 保持一致 |
| 核心功能详解 | ✅ 完整 | 2024-07-31 | 详细的实现原理 |
| 使用指南 | ✅ 完整 | 2024-07-31 | 实际应用和最佳实践 |
| API 参考文档 | ✅ 完整 | 2024-07-31 | 完整的 API 说明 |
| 架构文档 | ✅ 完整 | 2024-07-31 | 系统设计和架构 |
| 框架集成文档 | ✅ 完整 | 2024-07-28 | Laravel/ThinkPHP/Webman |
| 应用案例文档 | ✅ 完整 | 2024-07-24 | 实际使用场景 |

## 🤝 贡献文档

如果你发现文档中有错误或需要改进的地方，欢迎：

1. 提交 Issue 报告问题
2. 提交 Pull Request 改进文档
3. 分享你的使用经验和最佳实践

## 📞 获取帮助

如果在使用过程中遇到问题：

1. 首先查看相关文档
2. 搜索已有的 Issues
3. 创建新的 Issue 描述问题
4. 参与社区讨论

---

**开始使用 CacheKV，让缓存操作变得简单！** 🚀
