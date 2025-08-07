# 安装指南

## 标准安装

### 稳定版本安装
```bash
composer require asfop1/cache-kv
```

### 开发版本安装
```bash
composer require asfop1/cache-kv:dev-main
```

## 常见问题

### 1. 依赖包被删除问题

**问题描述：**
使用 `composer require asfop1/cache-kv:dev-main --prefer-source` 时，可能会删除其他依赖包（如 `asfop/constants`）。

**原因：**
- Composer 重新解析依赖关系时可能移除它认为不需要的包
- `--prefer-source` 选项会从源码安装，可能触发不同的依赖解析逻辑

**解决方案：**

**方案A：分步安装**
```bash
# 先安装其他依赖
composer require asfop/constants

# 再安装 CacheKV
composer require asfop1/cache-kv:dev-main
```

**方案B：使用 composer.json**
```json
{
    "require": {
        "asfop/constants": "^1.0",
        "asfop1/cache-kv": "dev-main"
    }
}
```
然后运行：
```bash
composer install
```

**方案C：锁定依赖版本**
```bash
# 安装时指定具体版本
composer require asfop/constants:^1.0 asfop1/cache-kv:dev-main
```

### 2. 版本冲突问题

**问题描述：**
不同包之间的版本要求冲突。

**解决方案：**
```bash
# 查看冲突详情
composer why-not asfop1/cache-kv:dev-main

# 更新所有依赖
composer update

# 或者忽略平台要求（谨慎使用）
composer install --ignore-platform-reqs
```

### 3. 缓存问题

**问题描述：**
Composer 缓存导致的安装问题。

**解决方案：**
```bash
# 清除 Composer 缓存
composer clear-cache

# 重新安装
composer install
```

## 验证安装

安装完成后，可以运行以下代码验证：

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 检查类是否正确加载
if (class_exists('Asfop\CacheKV\Core\CacheKVFactory')) {
    echo "✅ CacheKV 安装成功！\n";
} else {
    echo "❌ CacheKV 安装失败！\n";
}

// 检查辅助函数是否可用
if (function_exists('cache_kv_get')) {
    echo "✅ 辅助函数加载成功！\n";
} else {
    echo "❌ 辅助函数加载失败！\n";
}
?>
```

## 开发环境设置

如果你要参与 CacheKV 的开发：

```bash
# 克隆仓库
git clone https://github.com/asfop1/CacheKV.git
cd CacheKV

# 安装依赖
composer install

# 运行测试
composer test

# 查看测试覆盖率
composer test-coverage
```

## 系统要求

- **PHP**: >= 7.0
- **Redis 扩展**: 必须安装
- **Composer**: >= 1.0

## 获取帮助

如果遇到安装问题：

1. 查看 [GitHub Issues](https://github.com/asfop1/CacheKV/issues)
2. 提交新的 Issue 描述问题
3. 包含以下信息：
   - PHP 版本
   - Composer 版本
   - 完整的错误信息
   - `composer.json` 内容
