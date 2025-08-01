# 批量产品查询缓存优化

## 场景描述

在电商平台中，商品列表页、购物车、订单详情等页面经常需要批量获取多个商品的信息。传统的实现方式容易导致 N+1 查询问题，严重影响性能。

## 传统方案的问题

### ❌ N+1 查询问题
```php
// 危险的实现方式
function getProducts($productIds) {
    $products = [];
    foreach ($productIds as $id) {
        // 每个商品都执行一次数据库查询！
        $products[] = $database->query("SELECT * FROM products WHERE id = ?", [$id]);
    }
    return $products;
}

// 10个商品 = 10次数据库查询 = 性能灾难
$products = getProducts([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
```

### ❌ 复杂的缓存逻辑
```php
// 手动处理批量缓存的复杂逻辑
function getProductsWithCache($productIds) {
    $products = [];
    $missingIds = [];
    
    // 1. 逐个检查缓存
    foreach ($productIds as $id) {
        $cacheKey = "product_{$id}";
        if ($cache->has($cacheKey)) {
            $products[$id] = $cache->get($cacheKey);
        } else {
            $missingIds[] = $id;
        }
    }
    
    // 2. 批量查询缺失的数据
    if (!empty($missingIds)) {
        $missingProducts = $database->query(
            "SELECT * FROM products WHERE id IN (" . implode(',', $missingIds) . ")"
        );
        
        // 3. 逐个写入缓存
        foreach ($missingProducts as $product) {
            $cacheKey = "product_{$product['id']}";
            $cache->set($cacheKey, $product, 3600);
            $products[$product['id']] = $product;
        }
    }
    
    return $products;
}
```

## CacheKV + KeyManager 解决方案

### ✅ 一行代码解决批量缓存
```php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 配置键管理器
$keyManager = new KeyManager([
    'app_prefix' => 'ecommerce',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        'product' => 'product:{id}',
        'product_detail' => 'product:detail:{id}',
        'product_price' => 'product:price:{id}',
        'product_stock' => 'product:stock:{id}',
        'category_products' => 'category:products:{id}:page:{page}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 批量获取商品 - 自动处理缓存逻辑！
$productKeys = array_map(fn($id) => $keyManager->make('product', ['id' => $id]), $productIds);
$products = $cache->getMultiple($productKeys, function($missingKeys) use ($keyManager) {
    // 只查询缓存中不存在的商品
    $missingIds = array_map(function($key) use ($keyManager) {
        $parsed = $keyManager->parse($key);
        return explode(':', $parsed['business_key'])[1];
    }, $missingKeys);
    
    return fetchProductsFromDatabase($missingIds);
});
```

## 完整实现示例

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== 批量产品查询缓存优化 ===\n\n";

// 1. 系统配置
$keyManager = new KeyManager([
    'app_prefix' => 'shop',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        'product' => 'product:{id}',
        'product_detail' => 'product:detail:{id}',
        'product_price' => 'product:price:{id}',
        'product_reviews' => 'product:reviews:{id}:page:{page}',
        'category_products' => 'category:products:{id}:sort:{sort}:page:{page}',
        'search_results' => 'search:{query}:page:{page}',
        'hot_products' => 'hot:products:{category}:limit:{limit}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 2. 模拟数据库操作
function fetchProductsFromDatabase($productIds) {
    echo "📊 从数据库批量获取商品 ID: " . implode(', ', $productIds) . "\n";
    // 模拟数据库批量查询延迟
    usleep(200000); // 0.2秒
    
    $products = [];
    foreach ($productIds as $id) {
        $key = "shop:prod:v1:product:{$id}"; // 使用完整的缓存键作为返回键
        $products[$key] = [
            'id' => $id,
            'name' => "Product {$id}",
            'price' => rand(10, 1000) + 0.99,
            'category' => 'Electronics',
            'brand' => "Brand " . chr(65 + ($id % 26)),
            'stock' => rand(0, 100),
            'rating' => round(rand(30, 50) / 10, 1),
            'created_at' => date('Y-m-d H:i:s', time() - rand(0, 86400 * 30))
        ];
    }
    return $products;
}

function fetchProductDetails($productIds) {
    echo "📊 从数据库获取商品详情 ID: " . implode(', ', $productIds) . "\n";
    usleep(300000); // 0.3秒
    
    $details = [];
    foreach ($productIds as $id) {
        $key = "shop:prod:v1:product:detail:{$id}";
        $details[$key] = [
            'product_id' => $id,
            'description' => "Detailed description for product {$id}",
            'specifications' => [
                'weight' => rand(100, 2000) . 'g',
                'dimensions' => rand(10, 50) . 'x' . rand(10, 50) . 'x' . rand(5, 20) . 'cm',
                'color' => ['Red', 'Blue', 'Green', 'Black'][rand(0, 3)],
                'warranty' => rand(1, 3) . ' years'
            ],
            'images' => [
                "product_{$id}_1.jpg",
                "product_{$id}_2.jpg",
                "product_{$id}_3.jpg"
            ],
            'features' => [
                "Feature 1 for product {$id}",
                "Feature 2 for product {$id}",
                "Feature 3 for product {$id}"
            ]
        ];
    }
    return $details;
}

// 3. 商品服务类
class ProductService
{
    private $cache;
    private $keyManager;
    
    public function __construct($cache, $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }
    
    /**
     * 批量获取商品基本信息
     */
    public function getProducts($productIds)
    {
        $startTime = microtime(true);
        
        // 生成所有商品的缓存键
        $productKeys = array_map(function($id) {
            return $this->keyManager->make('product', ['id' => $id]);
        }, $productIds);
        
        // 批量获取，自动处理缓存逻辑
        $products = $this->cache->getMultiple($productKeys, function($missingKeys) {
            // 从缺失的键中解析出商品ID
            $missingIds = array_map(function($key) {
                $parsed = $this->keyManager->parse($key);
                return explode(':', $parsed['business_key'])[1];
            }, $missingKeys);
            
            // 批量从数据库获取
            return fetchProductsFromDatabase($missingIds);
        });
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        echo "⏱️  批量获取 " . count($productIds) . " 个商品耗时: {$duration}ms\n";
        
        return $products;
    }
    
    /**
     * 批量获取商品详情
     */
    public function getProductDetails($productIds)
    {
        $detailKeys = array_map(function($id) {
            return $this->keyManager->make('product_detail', ['id' => $id]);
        }, $productIds);
        
        return $this->cache->getMultiple($detailKeys, function($missingKeys) {
            $missingIds = array_map(function($key) {
                $parsed = $this->keyManager->parse($key);
                return explode(':', $parsed['business_key'])[2]; // product:detail:{id}
            }, $missingKeys);
            
            return fetchProductDetails($missingIds);
        }, 7200); // 详情缓存2小时
    }
    
    /**
     * 获取商品完整信息（基本信息 + 详情）
     */
    public function getFullProductInfo($productIds)
    {
        $startTime = microtime(true);
        
        // 并行获取基本信息和详情
        $products = $this->getProducts($productIds);
        $details = $this->getProductDetails($productIds);
        
        // 合并数据
        $fullInfo = [];
        foreach ($productIds as $id) {
            $productKey = $this->keyManager->make('product', ['id' => $id]);
            $detailKey = $this->keyManager->make('product_detail', ['id' => $id]);
            
            if (isset($products[$productKey]) && isset($details[$detailKey])) {
                $fullInfo[$id] = [
                    'basic' => $products[$productKey],
                    'detail' => $details[$detailKey]
                ];
            }
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        echo "⏱️  获取 " . count($productIds) . " 个商品完整信息耗时: {$duration}ms\n";
        
        return $fullInfo;
    }
    
    /**
     * 搜索商品
     */
    public function searchProducts($query, $page = 1)
    {
        return $this->cache->getByTemplate('search_results', [
            'query' => $query,
            'page' => $page
        ], function() use ($query, $page) {
            echo "📊 执行商品搜索: '{$query}' 第 {$page} 页\n";
            usleep(150000); // 0.15秒
            
            // 模拟搜索结果
            $results = [];
            for ($i = 1; $i <= 10; $i++) {
                $id = ($page - 1) * 10 + $i;
                $results[] = [
                    'id' => $id,
                    'name' => "Search Result {$id} for '{$query}'",
                    'price' => rand(50, 500) + 0.99,
                    'relevance' => rand(70, 100) / 100
                ];
            }
            
            return [
                'query' => $query,
                'page' => $page,
                'results' => $results,
                'total' => 1000,
                'per_page' => 10
            ];
        }, 1800); // 搜索结果缓存30分钟
    }
    
    /**
     * 获取分类商品
     */
    public function getCategoryProducts($categoryId, $sort = 'popular', $page = 1)
    {
        return $this->cache->getByTemplate('category_products', [
            'id' => $categoryId,
            'sort' => $sort,
            'page' => $page
        ], function() use ($categoryId, $sort, $page) {
            echo "📊 获取分类 {$categoryId} 商品 (排序: {$sort}, 页码: {$page})\n";
            usleep(180000); // 0.18秒
            
            $products = [];
            for ($i = 1; $i <= 20; $i++) {
                $id = $categoryId * 100 + ($page - 1) * 20 + $i;
                $products[] = [
                    'id' => $id,
                    'name' => "Category {$categoryId} Product {$i}",
                    'price' => rand(20, 800) + 0.99,
                    'category_id' => $categoryId
                ];
            }
            
            return [
                'category_id' => $categoryId,
                'sort' => $sort,
                'page' => $page,
                'products' => $products,
                'total' => 500
            ];
        });
    }
    
    /**
     * 更新商品信息并清除相关缓存
     */
    public function updateProduct($productId, $data)
    {
        echo "💾 更新商品 {$productId} 信息\n";
        
        // 清除相关缓存
        $templates = ['product', 'product_detail', 'product_price'];
        foreach ($templates as $template) {
            $key = $this->keyManager->make($template, ['id' => $productId]);
            $this->cache->forget($key);
            echo "🗑️  清除缓存: {$key}\n";
        }
    }
}

// 4. 实际使用演示
echo "1. 初始化商品服务\n";
echo "==================\n";
$productService = new ProductService($cache, $keyManager);

echo "\n2. 第一次批量获取商品（从数据库）\n";
echo "=================================\n";
$productIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
$products = $productService->getProducts($productIds);
echo "获取到 " . count($products) . " 个商品\n";

echo "\n3. 第二次批量获取商品（从缓存）\n";
echo "=================================\n";
$products2 = $productService->getProducts($productIds);
echo "缓存命中，快速获取 " . count($products2) . " 个商品\n";

echo "\n4. 部分缓存命中场景\n";
echo "==================\n";
$mixedIds = [8, 9, 10, 11, 12]; // 前3个在缓存中，后2个不在
$mixedProducts = $productService->getProducts($mixedIds);
echo "混合获取 " . count($mixedProducts) . " 个商品（部分缓存命中）\n";

echo "\n5. 获取商品完整信息\n";
echo "==================\n";
$fullInfo = $productService->getFullProductInfo([1, 2, 3]);
echo "获取到 " . count($fullInfo) . " 个商品的完整信息\n";

echo "\n6. 商品搜索\n";
echo "===========\n";
$searchResults = $productService->searchProducts('laptop', 1);
echo "搜索 '{$searchResults['query']}' 找到 {$searchResults['total']} 个结果\n";

echo "\n7. 分类商品获取\n";
echo "===============\n";
$categoryProducts = $productService->getCategoryProducts(1, 'price_asc', 1);
echo "分类 {$categoryProducts['category_id']} 共有 {$categoryProducts['total']} 个商品\n";

echo "\n8. 缓存键管理\n";
echo "=============\n";
echo "生成的缓存键示例:\n";
$sampleKeys = [
    $keyManager->make('product', ['id' => 1]),
    $keyManager->make('product_detail', ['id' => 1]),
    $keyManager->make('search_results', ['query' => 'laptop', 'page' => 1]),
    $keyManager->make('category_products', ['id' => 1, 'sort' => 'price_asc', 'page' => 1])
];

foreach ($sampleKeys as $key) {
    echo "  - {$key}\n";
}

echo "\n9. 更新商品信息\n";
echo "===============\n";
$productService->updateProduct(1, ['name' => 'Updated Product']);

echo "\n10. 缓存统计\n";
echo "============\n";
$stats = $cache->getStats();
echo "缓存统计:\n";
echo "  命中次数: {$stats['hits']}\n";
echo "  未命中次数: {$stats['misses']}\n";
echo "  命中率: {$stats['hit_rate']}%\n";

echo "\n=== 批量产品查询示例完成 ===\n";
```

## 性能对比分析

### 场景：获取10个商品信息

#### 传统方案（N+1查询）
```
- 10次数据库查询
- 总耗时：~2000ms
- 数据库压力：极高
```

#### 手动批量缓存
```
- 首次：1次批量查询 + 10次缓存写入 = ~300ms
- 缓存命中：10次缓存读取 = ~50ms
- 代码复杂度：高
```

#### CacheKV 方案
```
- 首次：1次批量查询 + 自动缓存 = ~250ms
- 缓存命中：1次批量缓存读取 = ~5ms
- 代码复杂度：低（一行代码）
```

## 最佳实践

### 1. 合理的批量大小
```php
// ✅ 推荐：每批20-50个商品
$batchSize = 20;
$batches = array_chunk($productIds, $batchSize);

foreach ($batches as $batch) {
    $products = $productService->getProducts($batch);
    // 处理这批商品
}
```

### 2. 不同数据的缓存策略
```php
// 基本信息：1小时（相对稳定）
'product' => ['template' => 'product:{id}', 'ttl' => 3600],

// 详细信息：2小时（变化较少）
'product_detail' => ['template' => 'product:detail:{id}', 'ttl' => 7200],

// 价格信息：10分钟（变化频繁）
'product_price' => ['template' => 'product:price:{id}', 'ttl' => 600],

// 库存信息：5分钟（实时性要求高）
'product_stock' => ['template' => 'product:stock:{id}', 'ttl' => 300],
```

### 3. 缓存更新策略
```php
public function updateProduct($productId, $data) {
    // 1. 更新数据库
    $this->database->update('products', $data, ['id' => $productId]);
    
    // 2. 清除相关缓存
    $this->clearProductCache($productId);
    
    // 3. 如果是热门商品，主动预热缓存
    if ($this->isHotProduct($productId)) {
        $this->preloadProductCache($productId);
    }
}
```

## 总结

通过 CacheKV + KeyManager 的批量缓存方案：

- **性能提升**：从 N+1 查询优化为批量查询
- **代码简化**：复杂的缓存逻辑变成一行代码
- **自动优化**：智能处理缓存命中和未命中
- **标准化**：统一的键命名和管理
- **可扩展**：支持各种商品相关的缓存场景

这种方案特别适合电商平台、内容管理系统等需要频繁批量查询的场景。
