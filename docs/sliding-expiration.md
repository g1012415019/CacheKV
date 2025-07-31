# 热点数据自动续期（滑动过期）

## 场景描述
在许多应用程序中，存在一些“热点数据”，它们被频繁访问。如果这些热点数据设置了固定的过期时间，那么即使它们被持续访问，也可能在过期后被清除，导致数据源频繁回源，增加了数据源的压力和响应延迟。

## 问题痛点
- **频繁回源**: 热点数据过期后，即使很快再次被请求，也需要重新从数据源加载，造成不必要的开销。
- **数据源压力**: 大量热点数据同时过期并回源，可能导致数据源瞬时压力过大，影响系统稳定性。
- **用户体验下降**: 数据回源带来的延迟会影响用户体验。

## 使用 CacheKV 后的解决方案

CacheKV 提供了基于访问频率的自动续期（滑动过期）机制。当一个缓存项被成功获取时，如果它配置了滑动过期，其过期时间会自动延长。这意味着只要热点数据持续被访问，它就会一直保持在缓存中，从而有效减少了数据源的访问。

### 示例代码

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKV;

// 模拟从数据源获取热点数据
function fetchHotItemFromSource(int $itemId): array
{
    echo "从数据源获取热点数据 ID: {$itemId}...\n";
    // 模拟数据源延迟
    sleep(1);
    return ['id' => $itemId, 'name' => 'Hot Item ' . $itemId, 'views' => rand(1000, 5000)];
}

// 1. 初始化 CacheKV 实例
$arrayDriver = new ArrayDriver();
$cache = new CacheKV($arrayDriver, 10); // 默认缓存有效期 10 秒，用于演示滑动过期

$itemId = 123;

echo "第一次获取热点数据 {$itemId} (应从数据源获取并缓存)...\n";
$item = $cache->get("hot_item:{$itemId}", function() use ($itemId) {
    return fetchHotItemFromSource($itemId);
}, 5); // 初始缓存 5 秒
print_r($item);

echo "\n等待 3 秒...\n";
sleep(3);

echo "\n第二次获取热点数据 {$itemId} (应从缓存获取，并自动续期)...\n";
$item = $cache->get("hot_item:{$itemId}", function() use ($itemId) {
    echo "从数据源获取热点数据 ID: {$itemId}...\n"; // 这行不应该被执行
    return fetchHotItemFromSource($itemId);
}, 5); // 再次访问，有效期延长到当前时间 + 5 秒
print_r($item);

echo "\n等待 3 秒...\n";
sleep(3);

echo "\n第三次获取热点数据 {$itemId} (应从缓存获取，并再次自动续期)...\n";
$item = $cache->get("hot_item:{$itemId}", function() use ($itemId) {
    echo "从数据源获取热点数据 ID: {$itemId}...\n"; // 这行不应该被执行
    return fetchHotItemFromSource($itemId);
}, 5);
print_r($item);

echo "\n等待 6 秒 (超过最后一次访问的 5 秒有效期)...\n";
sleep(6);

echo "\n第四次获取热点数据 {$itemId} (缓存应已过期，再次从数据源获取)...\n";
$item = $cache->get("hot_item:{$itemId}", function() use ($itemId) {
    return fetchHotItemFromSource($itemId);
}, 5);
print_r($item);

?>
```

## 优势
- **减少数据源压力**: 热点数据持续保持在缓存中，显著减少了对数据源的访问。
- **提升响应速度**: 用户总是从缓存中获取数据，响应速度更快。
- **优化资源利用**: 避免了频繁的缓存重建和数据源查询，提高了系统资源的利用效率。
- **智能过期管理**: 缓存的生命周期根据数据的实际访问频率动态调整，更加智能和高效。
