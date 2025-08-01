# 外部 API 缓存最佳实践

## 场景描述

现代应用经常需要调用外部 API 获取数据，如天气信息、汇率数据、第三方用户信息、支付状态等。外部 API 调用通常有以下特点：
- **延迟高**：网络请求耗时长
- **不稳定**：可能出现超时、限流
- **成本高**：按调用次数计费
- **限制多**：频率限制、并发限制

## 传统方案的问题

### ❌ 直接调用 API
```php
// 每次都调用外部 API - 性能差、成本高
function getWeatherInfo($city) {
    $response = file_get_contents("https://api.weather.com/v1/current?city={$city}");
    return json_decode($response, true);
}

// 每次页面加载都要等待 API 响应
$weather = getWeatherInfo('Beijing'); // 可能需要 2-5 秒
```

### ❌ 简单缓存方案
```php
// 手动缓存管理 - 复杂且容易出错
function getWeatherInfoWithCache($city) {
    $cacheKey = "weather_" . strtolower($city);
    
    if ($cache->has($cacheKey)) {
        return $cache->get($cacheKey);
    }
    
    try {
        $response = file_get_contents("https://api.weather.com/v1/current?city={$city}");
        $data = json_decode($response, true);
        
        if ($data) {
            $cache->set($cacheKey, $data, 1800); // 30分钟
        }
        
        return $data;
    } catch (Exception $e) {
        // 错误处理复杂
        return $cache->get($cacheKey . "_backup");
    }
}
```

## CacheKV + KeyManager 解决方案

### ✅ 统一的 API 缓存管理
```php
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 配置 API 专用的键管理器
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // API 响应缓存
        'api_weather' => 'api:weather:{city}',
        'api_exchange' => 'api:exchange:{from}:{to}',
        'api_user_info' => 'api:user:{provider}:{user_id}',
        'api_payment' => 'api:payment:{provider}:{order_id}',
        'api_geocoding' => 'api:geocoding:{address_hash}',
        
        // 复杂参数的 API（使用哈希）
        'api_search' => 'api:search:{service}:{params_hash}',
        'api_analytics' => 'api:analytics:{service}:{params_hash}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 一行代码搞定 API 缓存！
$weather = $cache->getByTemplate('api_weather', ['city' => 'Beijing'], function() {
    return callWeatherAPI('Beijing');
}, 1800); // 30分钟缓存
```

## 完整实现示例

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== 外部 API 缓存最佳实践 ===\n\n";

// 1. 系统配置
$keyManager = new KeyManager([
    'app_prefix' => 'apiapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        // 各种 API 服务模板
        'api_weather' => 'api:weather:{city}',
        'api_exchange' => 'api:exchange:{from}:{to}',
        'api_user_info' => 'api:user:{provider}:{user_id}',
        'api_payment_status' => 'api:payment:{provider}:{order_id}',
        'api_geocoding' => 'api:geocoding:{address_hash}',
        'api_stock_price' => 'api:stock:{symbol}',
        'api_news' => 'api:news:{category}:{page}',
        'api_translate' => 'api:translate:{from}:{to}:{text_hash}',
        'api_image_analysis' => 'api:image:{service}:{image_hash}',
        'api_search' => 'api:search:{service}:{params_hash}',
    ]
]);

$cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);

// 2. 模拟外部 API 调用
function callWeatherAPI($city) {
    echo "🌐 调用天气 API: {$city}\n";
    // 模拟网络延迟
    usleep(2000000); // 2秒
    
    return [
        'city' => $city,
        'temperature' => rand(-10, 40),
        'humidity' => rand(30, 90),
        'condition' => ['Sunny', 'Cloudy', 'Rainy', 'Snowy'][rand(0, 3)],
        'wind_speed' => rand(0, 30),
        'timestamp' => time(),
        'source' => 'WeatherAPI'
    ];
}

function callExchangeAPI($from, $to) {
    echo "🌐 调用汇率 API: {$from} -> {$to}\n";
    usleep(1500000); // 1.5秒
    
    $rates = [
        'USD' => 1.0,
        'EUR' => 0.85,
        'GBP' => 0.73,
        'JPY' => 110.0,
        'CNY' => 6.45
    ];
    
    $fromRate = $rates[$from] ?? 1.0;
    $toRate = $rates[$to] ?? 1.0;
    
    return [
        'from' => $from,
        'to' => $to,
        'rate' => round($toRate / $fromRate, 4),
        'timestamp' => time(),
        'source' => 'ExchangeAPI'
    ];
}

function callUserInfoAPI($provider, $userId) {
    echo "🌐 调用用户信息 API: {$provider} - {$userId}\n";
    usleep(1800000); // 1.8秒
    
    return [
        'provider' => $provider,
        'user_id' => $userId,
        'username' => "user_{$userId}",
        'email' => "user{$userId}@{$provider}.com",
        'avatar' => "https://{$provider}.com/avatar/{$userId}.jpg",
        'verified' => rand(0, 1) == 1,
        'followers' => rand(100, 10000),
        'timestamp' => time()
    ];
}

function callPaymentStatusAPI($provider, $orderId) {
    echo "🌐 调用支付状态 API: {$provider} - {$orderId}\n";
    usleep(1200000); // 1.2秒
    
    $statuses = ['pending', 'processing', 'completed', 'failed', 'refunded'];
    
    return [
        'provider' => $provider,
        'order_id' => $orderId,
        'status' => $statuses[rand(0, 4)],
        'amount' => rand(100, 10000) / 100,
        'currency' => 'USD',
        'transaction_id' => 'txn_' . uniqid(),
        'timestamp' => time()
    ];
}

function callGeocodingAPI($address) {
    echo "🌐 调用地理编码 API: {$address}\n";
    usleep(1600000); // 1.6秒
    
    return [
        'address' => $address,
        'latitude' => rand(-90000, 90000) / 1000,
        'longitude' => rand(-180000, 180000) / 1000,
        'country' => 'Country',
        'city' => 'City',
        'postal_code' => rand(10000, 99999),
        'confidence' => rand(80, 100) / 100,
        'timestamp' => time()
    ];
}

// 3. API 服务封装类
class ExternalAPIService
{
    private $cache;
    private $keyManager;
    
    public function __construct($cache, $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }
    
    /**
     * 获取天气信息
     */
    public function getWeather($city)
    {
        return $this->cache->getByTemplate('api_weather', ['city' => $city], function() use ($city) {
            return callWeatherAPI($city);
        }, 1800); // 天气信息缓存30分钟
    }
    
    /**
     * 获取汇率信息
     */
    public function getExchangeRate($from, $to)
    {
        return $this->cache->getByTemplate('api_exchange', [
            'from' => $from,
            'to' => $to
        ], function() use ($from, $to) {
            return callExchangeAPI($from, $to);
        }, 600); // 汇率缓存10分钟（变化频繁）
    }
    
    /**
     * 获取第三方用户信息
     */
    public function getUserInfo($provider, $userId)
    {
        return $this->cache->getByTemplate('api_user_info', [
            'provider' => $provider,
            'user_id' => $userId
        ], function() use ($provider, $userId) {
            return callUserInfoAPI($provider, $userId);
        }, 3600); // 用户信息缓存1小时
    }
    
    /**
     * 获取支付状态
     */
    public function getPaymentStatus($provider, $orderId)
    {
        return $this->cache->getByTemplate('api_payment_status', [
            'provider' => $provider,
            'order_id' => $orderId
        ], function() use ($provider, $orderId) {
            return callPaymentStatusAPI($provider, $orderId);
        }, 300); // 支付状态缓存5分钟（需要相对实时）
    }
    
    /**
     * 地理编码（地址转坐标）
     */
    public function geocodeAddress($address)
    {
        // 对地址进行哈希，避免键名过长
        $addressHash = md5($address);
        
        return $this->cache->getByTemplate('api_geocoding', [
            'address_hash' => $addressHash
        ], function() use ($address) {
            return callGeocodingAPI($address);
        }, 86400); // 地理编码缓存24小时（基本不变）
    }
    
    /**
     * 复杂搜索 API（使用参数哈希）
     */
    public function searchAPI($service, $params)
    {
        return $this->cache->getByTemplate('api_search', [
            'service' => $service,
            'params_hash' => md5(serialize($params))
        ], function() use ($service, $params) {
            echo "🌐 调用搜索 API: {$service} with params: " . json_encode($params) . "\n";
            usleep(2500000); // 2.5秒
            
            return [
                'service' => $service,
                'params' => $params,
                'results' => [
                    ['id' => 1, 'title' => 'Result 1', 'score' => 0.95],
                    ['id' => 2, 'title' => 'Result 2', 'score' => 0.87],
                    ['id' => 3, 'title' => 'Result 3', 'score' => 0.76]
                ],
                'total' => 150,
                'timestamp' => time()
            ];
        }, 1200); // 搜索结果缓存20分钟
    }
    
    /**
     * 批量获取多个城市天气
     */
    public function getBatchWeather($cities)
    {
        $startTime = microtime(true);
        
        // 生成所有城市的缓存键
        $weatherKeys = array_map(function($city) {
            return $this->keyManager->make('api_weather', ['city' => $city]);
        }, $cities);
        
        // 批量获取天气信息
        $weatherData = $this->cache->getMultiple($weatherKeys, function($missingKeys) {
            // 解析出需要调用 API 的城市
            $missingCities = array_map(function($key) {
                $parsed = $this->keyManager->parse($key);
                return explode(':', $parsed['business_key'])[2]; // api:weather:{city}
            }, $missingKeys);
            
            echo "🌐 批量调用天气 API: " . implode(', ', $missingCities) . "\n";
            
            // 批量调用 API（实际中可能需要并发处理）
            $results = [];
            foreach ($missingKeys as $i => $key) {
                $city = $missingCities[$i];
                $results[$key] = callWeatherAPI($city);
            }
            
            return $results;
        }, 1800);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        echo "⏱️  批量获取 " . count($cities) . " 个城市天气耗时: {$duration}ms\n";
        
        return $weatherData;
    }
    
    /**
     * 清除特定 API 的缓存
     */
    public function clearAPICache($template, $params)
    {
        $key = $this->keyManager->make($template, $params);
        $this->cache->forget($key);
        echo "🗑️  清除 API 缓存: {$key}\n";
    }
    
    /**
     * 预热常用 API 缓存
     */
    public function preloadCommonAPIs()
    {
        echo "🔥 预热常用 API 缓存...\n";
        
        // 预热热门城市天气
        $hotCities = ['Beijing', 'Shanghai', 'New York', 'London', 'Tokyo'];
        foreach ($hotCities as $city) {
            $this->getWeather($city);
        }
        
        // 预热常用汇率
        $commonRates = [
            ['USD', 'EUR'], ['USD', 'GBP'], ['USD', 'JPY'], ['USD', 'CNY']
        ];
        foreach ($commonRates as [$from, $to]) {
            $this->getExchangeRate($from, $to);
        }
        
        echo "✅ API 缓存预热完成\n";
    }
}

// 4. 实际使用演示
echo "1. 初始化 API 服务\n";
echo "==================\n";
$apiService = new ExternalAPIService($cache, $keyManager);

echo "\n2. 第一次获取天气信息（调用 API）\n";
echo "=================================\n";
$startTime = microtime(true);
$weather = $apiService->getWeather('Beijing');
$firstCallTime = round((microtime(true) - $startTime) * 1000, 2);
echo "天气信息: " . json_encode($weather) . "\n";
echo "首次调用耗时: {$firstCallTime}ms\n";

echo "\n3. 第二次获取天气信息（从缓存）\n";
echo "=================================\n";
$startTime = microtime(true);
$weather2 = $apiService->getWeather('Beijing');
$cacheTime = round((microtime(true) - $startTime) * 1000, 2);
echo "缓存命中耗时: {$cacheTime}ms\n";
echo "性能提升: " . round($firstCallTime / $cacheTime, 1) . "x\n";

echo "\n4. 获取汇率信息\n";
echo "===============\n";
$exchangeRate = $apiService->getExchangeRate('USD', 'CNY');
echo "汇率信息: " . json_encode($exchangeRate) . "\n";

echo "\n5. 获取第三方用户信息\n";
echo "====================\n";
$userInfo = $apiService->getUserInfo('github', '12345');
echo "用户信息: " . json_encode($userInfo) . "\n";

echo "\n6. 获取支付状态\n";
echo "===============\n";
$paymentStatus = $apiService->getPaymentStatus('stripe', 'order_123');
echo "支付状态: " . json_encode($paymentStatus) . "\n";

echo "\n7. 地理编码\n";
echo "===========\n";
$geocoding = $apiService->geocodeAddress('1600 Amphitheatre Parkway, Mountain View, CA');
echo "地理编码: " . json_encode($geocoding) . "\n";

echo "\n8. 复杂搜索 API\n";
echo "===============\n";
$searchParams = [
    'query' => 'machine learning',
    'category' => 'technology',
    'sort' => 'relevance',
    'filters' => ['language' => 'en', 'date_range' => '2024']
];
$searchResults = $apiService->searchAPI('academic', $searchParams);
echo "搜索结果: 找到 {$searchResults['total']} 条结果\n";

echo "\n9. 批量获取多城市天气\n";
echo "====================\n";
$cities = ['Beijing', 'Shanghai', 'Tokyo', 'London', 'New York'];
$batchWeather = $apiService->getBatchWeather($cities);
echo "批量获取了 " . count($batchWeather) . " 个城市的天气信息\n";

echo "\n10. 缓存键管理\n";
echo "==============\n";
echo "生成的 API 缓存键示例:\n";
$sampleKeys = [
    $keyManager->make('api_weather', ['city' => 'Beijing']),
    $keyManager->make('api_exchange', ['from' => 'USD', 'to' => 'CNY']),
    $keyManager->make('api_user_info', ['provider' => 'github', 'user_id' => '12345']),
    $keyManager->make('api_payment_status', ['provider' => 'stripe', 'order_id' => 'order_123'])
];

foreach ($sampleKeys as $key) {
    echo "  - {$key}\n";
}

echo "\n11. 缓存管理操作\n";
echo "================\n";
// 清除特定缓存
$apiService->clearAPICache('api_weather', ['city' => 'Beijing']);

// 预热常用缓存
$apiService->preloadCommonAPIs();

echo "\n12. 缓存统计\n";
echo "============\n";
$stats = $cache->getStats();
echo "API 缓存统计:\n";
echo "  命中次数: {$stats['hits']}\n";
echo "  未命中次数: {$stats['misses']}\n";
echo "  命中率: {$stats['hit_rate']}%\n";

echo "\n=== 外部 API 缓存示例完成 ===\n";
```

## 高级特性

### 1. 错误处理和降级
```php
public function getWeatherWithFallback($city) {
    try {
        return $this->cache->getByTemplate('api_weather', ['city' => $city], function() use ($city) {
            $result = callWeatherAPI($city);
            if (!$result) {
                throw new Exception('API returned empty result');
            }
            return $result;
        }, 1800);
    } catch (Exception $e) {
        // 降级到备用数据源或默认值
        return $this->getDefaultWeather($city);
    }
}
```

### 2. 缓存预热策略
```php
public function preloadWeatherForHotCities() {
    $hotCities = $this->getHotCities(); // 从统计数据获取热门城市
    
    foreach ($hotCities as $city) {
        // 异步预热缓存
        $this->asyncPreload('api_weather', ['city' => $city], function() use ($city) {
            return callWeatherAPI($city);
        });
    }
}
```

### 3. 智能缓存时间
```php
public function getExchangeRateWithSmartTTL($from, $to) {
    // 根据汇率波动性调整缓存时间
    $volatility = $this->getCurrencyVolatility($from, $to);
    $ttl = $volatility > 0.1 ? 300 : 1800; // 高波动性短缓存，低波动性长缓存
    
    return $this->cache->getByTemplate('api_exchange', [
        'from' => $from,
        'to' => $to
    ], function() use ($from, $to) {
        return callExchangeAPI($from, $to);
    }, $ttl);
}
```

## 性能优化建议

### 1. 合理的缓存时间
```php
// 根据数据特性设置不同的缓存时间
$cacheTTL = [
    'weather' => 1800,      // 30分钟（变化适中）
    'exchange' => 600,      // 10分钟（变化频繁）
    'user_info' => 3600,    // 1小时（相对稳定）
    'geocoding' => 86400,   // 24小时（基本不变）
    'payment' => 300,       // 5分钟（需要实时性）
];
```

### 2. 批量 API 调用
```php
// 优化：批量调用多个相关 API
public function getDashboardData($userId) {
    $keys = [
        $this->keyManager->make('api_user_info', ['provider' => 'github', 'user_id' => $userId]),
        $this->keyManager->make('api_weather', ['city' => 'Beijing']),
        $this->keyManager->make('api_exchange', ['from' => 'USD', 'to' => 'CNY'])
    ];
    
    return $this->cache->getMultiple($keys, function($missingKeys) {
        // 并发调用多个 API
        return $this->callMultipleAPIs($missingKeys);
    });
}
```

### 3. 缓存监控
```php
public function monitorAPICache() {
    $stats = $this->cache->getStats();
    
    // 监控缓存命中率
    if ($stats['hit_rate'] < 70) {
        $this->alertLowCacheHitRate($stats);
    }
    
    // 监控 API 调用频率
    $apiCalls = $this->getAPICallCount();
    if ($apiCalls > $this->getAPILimit() * 0.8) {
        $this->alertHighAPIUsage($apiCalls);
    }
}
```

## 总结

通过 CacheKV + KeyManager 的外部 API 缓存方案：

- **性能提升**：API 响应时间从秒级降到毫秒级
- **成本降低**：减少 API 调用次数，降低费用
- **稳定性提升**：缓存提供服务降级能力
- **代码简化**：复杂的缓存逻辑变成一行代码
- **统一管理**：标准化的 API 缓存键管理

这种方案特别适合需要频繁调用外部 API 的应用，如数据聚合平台、实时监控系统、第三方集成服务等。
