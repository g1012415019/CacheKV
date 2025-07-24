# 外部 API 响应缓存

## 场景描述
许多应用程序需要频繁调用第三方外部 API 来获取数据，例如天气信息、汇率、地理位置数据等。这些 API 调用通常伴随着网络延迟和调用频率限制。

## 问题痛点
- **网络延迟**: 每次调用外部 API 都会引入网络延迟，影响应用程序的响应速度。
- **API 调用限制**: 大多数第三方 API 都有调用频率或配额限制，频繁调用可能导致超出限制，服务不可用。
- **成本增加**: 部分按调用次数计费的 API 会因频繁调用而增加成本。
- **数据冗余**: 相同的数据被重复获取，浪费资源。

## 使用 DataCache 后的解决方案

DataCache 可以轻松地缓存外部 API 的响应，从而减少实际的 API 调用次数，提高应用程序的响应速度，并遵守 API 调用限制。

### 示例代码

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\DataCache\Cache\Drivers\ArrayDriver;
use Asfop\DataCache\DataCache;

// 模拟调用外部天气 API
function fetchWeatherDataFromApi(string $city): array
{
    echo "调用外部天气 API 获取 {$city} 的天气数据...\n";
    // 模拟网络延迟
    sleep(2);
    $temperatures = [
        'New York' => ['temp' => 25, 'condition' => 'Sunny'],
        'London' => ['temp' => 18, 'condition' => 'Cloudy'],
        'Tokyo' => ['temp' => 30, 'condition' => 'Hot'],
    ];
    return $temperatures[$city] ?? ['temp' => 'N/A', 'condition' => 'Unknown'];
}

// 1. 初始化 DataCache 实例
$arrayDriver = new ArrayDriver();
$cache = new DataCache($arrayDriver, 600); // 默认缓存 10 分钟

// 2. 获取纽约天气数据
$city = 'New York';
echo "第一次获取 {$city} 天气数据 (应调用 API 并缓存)...\n";
$weather = $cache->get("weather:{$city}", function() use ($city) {
    return fetchWeatherDataFromApi($city);
}, 300); // 缓存 5 分钟
print_r($weather);

echo "\n第二次获取 {$city} 天气数据 (应从缓存获取)...\n";
$weather = $cache->get("weather:{$city}", function() use ($city) {
    echo "调用外部天气 API 获取 {$city} 的天气数据...\n"; // 这行不应该被执行
    return fetchWeatherDataFromApi($city);
}, 300);
print_r($weather);

// 模拟缓存过期或手动清除缓存
echo "\n等待 6 秒，模拟缓存过期...\n";
sleep(6);

echo "\n第三次获取 {$city} 天气数据 (缓存已过期，应再次调用 API)...\n";
$weather = $cache->get("weather:{$city}", function() use ($city) {
    return fetchWeatherDataFromApi($city);
}, 300);
print_r($weather);

?>
```

## 优势
- **减少 API 调用**: 显著降低对外部 API 的请求次数，避免超出调用限制。
- **提高响应速度**: 从本地缓存获取数据比从外部 API 获取快得多，提升用户体验。
- **降低成本**: 减少按调用次数计费的 API 的使用成本。
- **数据一致性**: 在缓存有效期内，确保获取到的数据是一致的。
