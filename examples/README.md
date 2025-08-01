# CacheKV 示例

本目录包含了 CacheKV 的各种使用示例，从基础入门到高级应用。

## 目录结构

### basic/ - 基础示例
- `getting-started.php` - 快速入门示例，展示 CacheKV 的基本功能
- `real-world-usage.php` - 实际应用场景示例，展示在真实项目中的使用方法

### advanced/ - 高级示例
- `advanced-features.php` - 高级功能示例，包括分层缓存、预热、防穿透等
- `benchmark.php` - 性能基准测试

## 运行示例

### 前置条件

确保已安装依赖：

```bash
composer install
```

### 基础示例

```bash
# 快速入门
php examples/basic/getting-started.php

# 实际应用场景
php examples/basic/real-world-usage.php
```

### 高级示例

```bash
# 高级功能演示
php examples/advanced/advanced-features.php

# 性能基准测试
php examples/advanced/benchmark.php
```

### 使用 Composer 脚本

```bash
# 运行快速入门示例
composer example

# 运行性能测试
composer benchmark
```

## 示例说明

### 1. getting-started.php
展示 CacheKV 的核心功能：
- 基础缓存操作（set/get/delete）
- 自动回填缓存（核心特性）
- 批量操作
- 标签管理
- 辅助函数使用

### 2. real-world-usage.php
真实项目中的应用场景：
- 用户资料管理
- 商品详情页面
- 外部API缓存
- 系统配置缓存
- 搜索结果缓存

### 3. advanced-features.php
高级功能和最佳实践：
- 复杂参数的缓存键生成
- 分层缓存策略
- 缓存预热策略
- 防缓存穿透
- 限流缓存应用
- 分析数据缓存
- 缓存版本管理

### 4. benchmark.php
性能基准测试：
- 基础操作性能
- 模板操作性能
- 批量操作性能
- 自动回填性能
- 缓存命中率测试

## Redis 支持

如果要测试 Redis 驱动，需要安装 Redis 服务器和 PHP Redis 客户端：

```bash
# 安装 Predis（推荐）
composer require predis/predis

# 或者安装 PhpRedis 扩展
# Ubuntu/Debian: sudo apt-get install php-redis
# macOS: brew install php-redis
```

启动 Redis 服务器：

```bash
# 使用 Docker
docker run -d -p 6379:6379 redis:alpine

# 或者直接启动
redis-server
```

## 自定义示例

你可以基于这些示例创建自己的缓存策略：

1. **定义缓存模板**：创建符合你业务需求的缓存模板常量
2. **配置 CacheKV**：设置合适的驱动、TTL 和键管理策略
3. **实现服务层**：在服务类中封装缓存逻辑
4. **优化性能**：使用批量操作、预热、分层缓存等策略

## 最佳实践

1. **使用常量定义模板**：避免字符串硬编码
2. **合理设置 TTL**：根据数据更新频率设置缓存时间
3. **利用标签管理**：方便批量清除相关缓存
4. **防止缓存穿透**：对不存在的数据也进行缓存
5. **监控缓存性能**：定期检查缓存命中率和性能指标

## 问题反馈

如果在运行示例时遇到问题，请检查：

1. PHP 版本是否符合要求（>= 7.0）
2. 依赖是否正确安装
3. Redis 服务是否正常运行（如果使用 Redis 驱动）

更多问题请参考项目文档或提交 Issue。
