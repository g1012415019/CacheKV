<?php
/**
 * CacheKV 实际应用场景示例
 * 
 * 展示在真实项目中如何使用 CacheKV 解决常见的缓存需求
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== CacheKV 实际应用场景示例 ===\n\n";

// 定义应用的缓存模板
class AppCacheTemplates {
    const USER_PROFILE = 'user_profile';
    const USER_PERMISSIONS = 'user_permissions';
    const PRODUCT_INFO = 'product_info';
    const PRODUCT_PRICE = 'product_price';
    const API_WEATHER = 'api_weather';
    const SEARCH_RESULTS = 'search_results';
    const SYSTEM_CONFIG = 'system_config';
}

// 配置 CacheKV
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            AppCacheTemplates::USER_PROFILE => 'user:profile:{id}',
            AppCacheTemplates::USER_PERMISSIONS => 'user:permissions:{user_id}',
            AppCacheTemplates::PRODUCT_INFO => 'product:info:{id}',
            AppCacheTemplates::PRODUCT_PRICE => 'product:price:{id}',
            AppCacheTemplates::API_WEATHER => 'api:weather:{city}',
            AppCacheTemplates::SEARCH_RESULTS => 'search:{query_hash}:{page}',
            AppCacheTemplates::SYSTEM_CONFIG => 'config:{key}',
        ]
    ]
]);

$cache = CacheKVFactory::store();

// 模拟数据库和外部API
class MockDatabase {
    public static function getUserProfile($userId) {
        // 模拟数据库查询延迟
        usleep(50000); // 50ms
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com",
            'avatar' => "https://example.com/avatar/{$userId}.jpg",
            'created_at' => '2024-01-01 10:00:00'
        ];
    }
    
    public static function getUserPermissions($userId) {
        usleep(30000); // 30ms
        return ['read', 'write', $userId == 1 ? 'admin' : 'user'];
    }
    
    public static function getProductInfo($productId) {
        usleep(40000); // 40ms
        return [
            'id' => $productId,
            'name' => "Product {$productId}",
            'description' => "This is product {$productId}",
            'category' => 'Electronics'
        ];
    }
    
    public static function getProductPrice($productId) {
        usleep(20000); // 20ms
        return [
            'product_id' => $productId,
            'price' => rand(100, 1000),
            'currency' => 'USD',
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}

class MockWeatherAPI {
    public static function getWeather($city) {
        // 模拟API调用延迟
        usleep(200000); // 200ms
        return [
            'city' => $city,
            'temperature' => rand(15, 35),
            'humidity' => rand(40, 80),
            'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}

// 应用服务层
class UserService {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function getUserProfile($userId) {
        return $this->cache->getByTemplate(
            AppCacheTemplates::USER_PROFILE,
            ['id' => $userId],
            function() use ($userId) {
                echo "  [DB] 查询用户 {$userId} 的资料\n";
                return MockDatabase::getUserProfile($userId);
            },
            3600 // 1小时缓存
        );
    }
    
    public function getUserPermissions($userId) {
        return $this->cache->getByTemplate(
            AppCacheTemplates::USER_PERMISSIONS,
            ['user_id' => $userId],
            function() use ($userId) {
                echo "  [DB] 查询用户 {$userId} 的权限\n";
                return MockDatabase::getUserPermissions($userId);
            },
            1800 // 30分钟缓存
        );
    }
    
    public function clearUserCache($userId) {
        $this->cache->deleteByTemplate(AppCacheTemplates::USER_PROFILE, ['id' => $userId]);
        $this->cache->deleteByTemplate(AppCacheTemplates::USER_PERMISSIONS, ['user_id' => $userId]);
        echo "  [Cache] 清除用户 {$userId} 的缓存\n";
    }
}

class ProductService {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function getProductDetails($productId) {
        // 并行获取商品信息和价格
        $productKeys = [
            $this->cache->makeKey(AppCacheTemplates::PRODUCT_INFO, ['id' => $productId]),
            $this->cache->makeKey(AppCacheTemplates::PRODUCT_PRICE, ['id' => $productId])
        ];
        
        $results = $this->cache->getMultiple($productKeys, function($missingKeys) use ($productId) {
            $data = [];
            foreach ($missingKeys as $key) {
                if (strpos($key, 'product:info:') !== false) {
                    echo "  [DB] 查询商品 {$productId} 的信息\n";
                    $data[$key] = MockDatabase::getProductInfo($productId);
                } elseif (strpos($key, 'product:price:') !== false) {
                    echo "  [DB] 查询商品 {$productId} 的价格\n";
                    $data[$key] = MockDatabase::getProductPrice($productId);
                }
            }
            return $data;
        });
        
        return [
            'info' => $results[$productKeys[0]] ?? null,
            'price' => $results[$productKeys[1]] ?? null
        ];
    }
}

class WeatherService {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function getWeather($city) {
        return $this->cache->getByTemplate(
            AppCacheTemplates::API_WEATHER,
            ['city' => $city],
            function() use ($city) {
                echo "  [API] 调用天气API获取 {$city} 的天气\n";
                return MockWeatherAPI::getWeather($city);
            },
            1800 // 30分钟缓存，天气数据不需要实时
        );
    }
}

// 演示场景

echo "场景1: 用户资料管理\n";
echo str_repeat("-", 30) . "\n";

$userService = new UserService($cache);

// 第一次访问 - 从数据库获取
echo "首次获取用户资料:\n";
$profile = $userService->getUserProfile(1);
echo "用户名: {$profile['name']}\n";

$permissions = $userService->getUserPermissions(1);
echo "权限: " . implode(', ', $permissions) . "\n";

// 第二次访问 - 从缓存获取
echo "\n再次获取用户资料（从缓存）:\n";
$profile2 = $userService->getUserProfile(1);
echo "用户名: {$profile2['name']}\n";

// 清除缓存
echo "\n更新用户资料后清除缓存:\n";
$userService->clearUserCache(1);

echo "\n";

echo "场景2: 商品详情页面\n";
echo str_repeat("-", 30) . "\n";

$productService = new ProductService($cache);

// 获取商品详情（信息+价格）
echo "获取商品详情:\n";
$productDetails = $productService->getProductDetails(101);
echo "商品名: {$productDetails['info']['name']}\n";
echo "价格: {$productDetails['price']['price']} {$productDetails['price']['currency']}\n";

echo "\n再次获取商品详情（从缓存）:\n";
$productDetails2 = $productService->getProductDetails(101);
echo "商品名: {$productDetails2['info']['name']}\n";

echo "\n";

echo "场景3: 外部API缓存\n";
echo str_repeat("-", 30) . "\n";

$weatherService = new WeatherService($cache);

// 获取天气信息
echo "获取天气信息:\n";
$weather = $weatherService->getWeather('Beijing');
echo "城市: {$weather['city']}\n";
echo "温度: {$weather['temperature']}°C\n";
echo "天气: {$weather['condition']}\n";

echo "\n再次获取天气信息（从缓存）:\n";
$weather2 = $weatherService->getWeather('Beijing');
echo "城市: {$weather2['city']}\n";

echo "\n";

echo "场景4: 系统配置缓存\n";
echo str_repeat("-", 30) . "\n";

// 系统配置通常很少变化，可以长时间缓存
$siteConfig = $cache->getByTemplate(
    AppCacheTemplates::SYSTEM_CONFIG,
    ['key' => 'site_settings'],
    function() {
        echo "  [DB] 查询系统配置\n";
        return [
            'site_name' => 'My Awesome Site',
            'maintenance_mode' => false,
            'max_upload_size' => '10MB',
            'timezone' => 'UTC'
        ];
    },
    86400 // 24小时缓存
);

echo "站点名称: {$siteConfig['site_name']}\n";
echo "维护模式: " . ($siteConfig['maintenance_mode'] ? '开启' : '关闭') . "\n";

echo "\n";

echo "场景5: 搜索结果缓存\n";
echo str_repeat("-", 30) . "\n";

// 搜索结果缓存，使用查询哈希作为键
$query = 'CacheKV tutorial';
$queryHash = md5($query);
$page = 1;

$searchResults = $cache->getByTemplate(
    AppCacheTemplates::SEARCH_RESULTS,
    ['query_hash' => $queryHash, 'page' => $page],
    function() use ($query) {
        echo "  [Search] 执行搜索: {$query}\n";
        // 模拟搜索引擎查询
        return [
            'query' => $query,
            'total' => 42,
            'results' => [
                ['title' => 'CacheKV 官方文档', 'url' => 'https://example.com/docs'],
                ['title' => 'CacheKV 使用教程', 'url' => 'https://example.com/tutorial'],
                ['title' => 'CacheKV 最佳实践', 'url' => 'https://example.com/best-practices'],
            ]
        ];
    },
    600 // 10分钟缓存
);

echo "搜索 '{$searchResults['query']}' 找到 {$searchResults['total']} 个结果\n";
echo "第一个结果: {$searchResults['results'][0]['title']}\n";

echo "\n";

echo "=== 缓存统计 ===\n";
$stats = $cache->getStats();
echo "缓存统计: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";

echo "\n=== 示例完成 ===\n";
