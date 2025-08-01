# CacheKV 示例

本目录包含了 CacheKV 的各种使用示例。

## 目录结构

### basic/ - 基础示例
- `example_constants.php` - 常量定义方式的完整示例
- `example.php` - 基本使用示例
- `index.php` - 简单的入门示例

### advanced/ - 高级示例
- `benchmark.php` - 性能基准测试

### template-management.php - 模板管理示例

## 运行示例

```bash
# 基础示例
php examples/basic/example_constants.php

# 性能测试
php examples/advanced/benchmark.php

# 模板管理
php examples/template-management.php
```

## 前置条件

确保已安装依赖：

```bash
composer install
```

对于 Redis 相关示例，需要安装 Redis 服务器和 PHP Redis 扩展或 Predis 库：

```bash
# 安装 Predis（推荐）
composer require predis/predis

# 或者安装 PhpRedis 扩展
# sudo apt-get install php-redis  # Ubuntu/Debian
# brew install php-redis          # macOS
```
