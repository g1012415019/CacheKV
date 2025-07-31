# 商品批量查询（多条数据获取）

## 场景描述
在电商平台或内容管理系统中，经常需要根据多个 ID 批量获取商品、文章或其他实体的信息。传统方式下，这可能导致 N+1 查询问题或复杂的缓存回填逻辑。

## 问题痛点
- **N+1 查询**: 如果为每个 ID 单独查询数据库，会导致大量的数据库往返，性能低下。
- **复杂的回填逻辑**: 手动处理批量查询的缓存命中和未命中逻辑，以及将缺失数据批量回填到缓存中，代码复杂且容易出错。
- **数据不一致**: 批量操作中，如果部分数据从缓存获取，部分从数据库获取，可能导致数据不一致。

## 使用 CacheKV 后的解决方案

CacheKV 提供了 `getMultiple` 方法，能够自动处理批量查询的缓存逻辑，包括批量从缓存获取、批量从数据源回源未命中数据，并批量回填缓存，有效避免 N+1 查询问题。

### 示例代码

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKV;

// 假设这是你的数据库批量查询函数
function fetchProductsFromDatabase(array $productIds): array
{
    echo "从数据库批量获取商品 ID: " . implode(', ', $productIds) . "...\n";
    // 模拟数据库批量查询延迟
    sleep(1);
    $products = [];
    foreach ($productIds as $id) {
        $products[$id] = ['id' => $id, 'name' => 'Product ' . $id, 'price' => rand(10, 100) . '.00'];
    }
    return $products;
}

// 1. 初始化 CacheKV 实例
$arrayDriver = new ArrayDriver();
$cache = new CacheKV($arrayDriver, 3600);

// 2. 准备要查询的商品 ID
$productIds = [101, 102, 103, 104, 105];
$cacheKeys = array_map(fn($id) => "product:{$id}", $productIds);

echo "第一次批量获取商品信息 (部分或全部从数据库获取并缓存)...\n";
$products = $cache->getMultiple(
    $cacheKeys,
    function (array $missingKeys) use ($productIds) {
        $missingProductIds = array_map(fn($key) => (int) str_replace("product:", "", $key), $missingKeys);
        return fetchProductsFromDatabase($missingProductIds);
    },
    60 // 缓存 60 秒
);
print_r($products);

echo "\n第二次批量获取商品信息 (应全部从缓存获取)...\n";
$products = $cache->getMultiple(
    $cacheKeys,
    function (array $missingKeys) use ($productIds) {
        echo "从数据库批量获取商品 ID: " . implode(', ', $missingKeys) . "...\n"; // 这行不应该被执行
        $missingProductIds = array_map(fn($key) => (int) str_replace("product:", "", $key), $missingKeys);
        return fetchProductsFromDatabase($missingProductIds);
    },
    60
);
print_r($products);

// 模拟商品 103 更新，清除其缓存
echo "\n模拟商品 103 更新，清除其缓存...\n";
$cache->forget("product:103");

echo "\n第三次批量获取商品信息 (商品 103 应从数据库获取，其他从缓存)...\n";
$products = $cache->getMultiple(
    $cacheKeys,
    function (array $missingKeys) use ($productIds) {
        $missingProductIds = array_map(fn($key) => (int) str_replace("product:", "", $key), $missingKeys);
        return fetchProductsFromDatabase($missingProductIds);
    },
    60
);
print_r($products);

?>
```

## 优势
- **避免 N+1 查询**: CacheKV 自动处理批量查询，显著减少了数据库往返次数。
- **简化代码**: 开发者无需手动管理复杂的缓存命中/未命中逻辑和批量回填。
- **提高效率**: 批量操作优化减少了数据源的负载，提高了应用程序的响应速度。
- **统一缓存策略**: 批量操作也遵循统一的缓存过期和管理策略。
