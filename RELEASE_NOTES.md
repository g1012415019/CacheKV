# CacheKV v1.0.0 发布说明

🎉 **CacheKV v1.0.0 正式发布！**

CacheKV 是一个专注于简化缓存操作的 PHP 库，核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式。

## 🚀 核心功能

### 1. 自动回填缓存
```php
// 一行代码搞定：检查缓存 -> 未命中则获取数据 -> 自动回填缓存
$user = $cache->get('user:123', function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
});
```

### 2. 批量数据操作
```php
$userIds = [1, 2, 3, 4, 5];

// 自动处理：部分命中缓存，部分从数据源获取
$users = $cache->getMultiple($userIds, function($missingIds) {
    return $userService->getByIds($missingIds); // 只获取缺失的数据
});
```

### 3. 基于标签的缓存失效管理
```php
// 设置带标签的缓存
$cache->setWithTag('user:1', $userData, ['users', 'vip_users']);

// 批量清除：一次清除所有用户相关缓存
$cache->clearTag('users');
```

### 4. 性能统计功能
```php
$stats = $cache->getStats();
// 输出：['hits' => 85, 'misses' => 15, 'hit_rate' => 85.0]
```

## 🔧 技术特性

- ✅ **PHP 7.0+ 兼容** - 支持 PHP 7.0 及以上版本
- ✅ **多驱动支持** - Redis 驱动（生产环境）+ Array 驱动（开发测试）
- ✅ **门面模式** - 支持静态方法调用
- ✅ **框架集成** - Laravel、ThinkPHP、Webman 集成支持
- ✅ **缓存穿透防护** - 自动缓存 null 值
- ✅ **滑动过期** - 访问时自动延长过期时间
- ✅ **性能监控** - 内置命中率统计

## 📦 安装

```bash
composer require asfop/cache-kv
```

## 🎯 快速开始

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 创建缓存实例
$cache = new CacheKV(new ArrayDriver(), 3600);

// 使用核心功能：自动回填缓存
$user = $cache->get('user:123', function() {
    return [
        'id' => 123,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
});

echo "用户信息：" . json_encode($user);
```

## 📚 完整文档

- [架构文档](docs/architecture.md) - 深入了解设计架构
- [核心功能详解](docs/core-features.md) - 功能实现原理
- [API 参考文档](docs/api-reference.md) - 完整的 API 说明
- [使用指南](docs/usage-guide.md) - 实际应用场景
- [框架集成指南](docs/) - Laravel/ThinkPHP/Webman 集成

## 🧪 测试状态

- ✅ **8 个测试** 全部通过
- ✅ **22 个断言** 验证功能
- ✅ **100% 核心功能** 测试覆盖

```bash
PHPUnit 9.6.22 by Sebastian Bergmann and contributors.

........                                                            8 / 8 (100%)

Time: 00:00.005, Memory: 6.00 MB

OK (8 tests, 22 assertions)
```

## 🎨 使用场景

### 电商系统
```php
// 商品信息缓存
$product = $cache->get("product:{$productId}", function() use ($productId) {
    return $productService->getById($productId);
});

// 批量商品查询
$products = $cache->getMultiple($productIds, function($missingIds) {
    return $productService->getByIds($missingIds);
});
```

### 用户系统
```php
// 用户相关缓存管理
$cache->setWithTag("user:profile:{$userId}", $profile, ["user_{$userId}"]);
$cache->setWithTag("user:settings:{$userId}", $settings, ["user_{$userId}"]);

// 用户更新时，一次性清除所有相关缓存
$cache->clearTag("user_{$userId}");
```

### API 缓存
```php
// 外部 API 调用缓存
$weather = $cache->get("weather:{$city}", function() use ($city) {
    return $weatherAPI->getCurrentWeather($city);
}, 1800); // 30分钟缓存
```

## 🔄 从传统缓存迁移

### 传统方式（繁琐）
```php
if ($cache->has('user:' . $userId)) {
    $user = $cache->get('user:' . $userId);
} else {
    $user = $userService->getById($userId);
    $cache->set('user:' . $userId, $user, 3600);
}
```

### CacheKV 方式（简洁）
```php
$user = $cache->get('user:' . $userId, function() use ($userId, $userService) {
    return $userService->getById($userId);
});
```

## 🚀 性能优势

1. **代码简化** - 从 6-8 行代码减少到 1 行
2. **批量优化** - 智能处理批量查询，减少数据库访问
3. **防止穿透** - 自动缓存 null 值，避免重复查询
4. **标签管理** - 批量清理相关缓存，提高管理效率

## 🛠️ 系统要求

- PHP 7.0 或更高版本
- 使用 Redis 驱动时需要 Redis 服务器和 predis/predis 包

## 🤝 贡献

欢迎贡献代码、报告问题或改进文档！

1. Fork 本仓库
2. 创建特性分支
3. 提交更改
4. 开启 Pull Request

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

---

**CacheKV v1.0.0** - 让缓存回填变得简单！🚀

GitHub: https://github.com/asfop1/CacheKV
Packagist: https://packagist.org/packages/asfop/cache-kv
