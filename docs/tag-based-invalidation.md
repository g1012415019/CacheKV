# 基于标签的批量缓存失效

## 场景描述
在复杂的应用程序中，数据之间往往存在关联性。当某个分类的数据发生变化时，可能需要清除所有与该分类相关的缓存项。手动清除这些分散的缓存项既繁琐又容易遗漏，导致数据不一致。

## 问题痛点
- **手动管理复杂**: 难以追踪所有与特定数据相关的缓存键，手动清除容易出错和遗漏。
- **数据不一致**: 缓存未及时失效可能导致用户看到过时的数据。
- **维护成本高**: 随着业务逻辑的增长，缓存失效的逻辑变得越来越复杂，维护成本高昂。

## 使用 CacheKV 后的解决方案

CacheKV 提供了强大的缓存标签系统，允许为缓存项附加一个或多个标签。当需要清除某个分类下的所有缓存时，只需通过标签名即可批量清除所有关联的缓存项，极大地简化了缓存失效管理。

### 示例代码

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKV;

// 1. 初始化 CacheKV 实例
$arrayDriver = new ArrayDriver();
$cache = new CacheKV($arrayDriver, 3600);

// 2. 存储带标签的商品信息
$cache->setWithTag('product:1', ['id' => 1, 'name' => 'Laptop', 'category' => 'electronics'], 'category:electronics', 3600);
$cache->setWithTag('product:2', ['id' => 2, 'name' => 'Mouse', 'category' => 'electronics'], 'category:electronics', 3600);
$cache->setWithTag('product:3', ['id' => 3, 'name' => 'Keyboard', 'category' => 'electronics'], ['category:electronics', 'hot_items'], 3600);
$cache->setWithTag('product:4', ['id' => 4, 'name' => 'Book', 'category' => 'books'], 'category:books', 3600);

// 3. 验证缓存项是否存在
echo "初始缓存状态：\n";
var_dump($cache->has('product:1')); // true
var_dump($cache->has('product:2')); // true
var_dump($cache->has('product:3')); // true
var_dump($cache->has('product:4')); // true

// 4. 清除 'category:electronics' 标签下的所有缓存
echo "\n清除 'category:electronics' 标签下的所有缓存...\n";
$cache->clearTag('category:electronics');

// 5. 再次验证缓存项是否存在
echo "\n清除后缓存状态：\n";
var_dump($cache->has('product:1')); // false
var_dump($cache->has('product:2')); // false
var_dump($cache->has('product:3')); // false (虽然有 hot_items 标签，但 electronics 标签被清除了)
var_dump($cache->has('product:4')); // true (不受影响)

// 6. 清除 'hot_items' 标签下的缓存 (此时 product:3 已经不存在了)
echo "\n清除 'hot_items' 标签下的所有缓存...\n";
var_dump($cache->has('product:3')); // 仍然是 false

?>
```

## 优势
- **批量、原子性失效**: 通过标签一键清除所有关联缓存，确保数据一致性。
- **简化缓存管理**: 开发者无需关心具体的缓存键，只需管理标签即可。
- **降低维护成本**: 业务逻辑变化时，只需调整标签策略，无需修改大量缓存清除代码。
- **提高开发效率**: 快速实现复杂的缓存失效逻辑。
