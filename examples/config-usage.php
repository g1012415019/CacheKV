<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVFactory;

echo "=== CacheKV 配置使用完整指南 ===\n\n";

// ========================================
// 方案1：使用默认配置文件
// ========================================
echo "1. 使用默认配置文件\n";
echo "================================\n";

// 直接使用默认配置
$cache1 = CacheKVServiceProvider::register();

// 使用门面访问
$user = CacheKVFacade::getByTemplate('user', ['id' => 100], function() {
    echo "  -> 从数据库获取用户 100\n";
    return ['id' => 100, 'name' => 'Default User', 'email' => 'default@example.com'];
});

echo "默认配置用户: " . json_encode($user, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案2：自定义完整配置
// ========================================
echo "2. 自定义完整配置\n";
echo "================================\n";

$customConfig = [
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 1800 // 30分钟
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'ecommerce',
        'env_prefix' => 'production',
        'version' => 'v2',
        'separator' => ':',
        'templates' => [
            // 用户相关
            'user_profile' => 'user:profile:{id}',
            'user_settings' => 'user:settings:{id}',
            'user_cart' => 'user:cart:{id}',
            
            // 产品相关
            'product_detail' => 'product:detail:{id}',
            'product_reviews' => 'product:reviews:{id}:page:{page}',
            'product_stock' => 'product:stock:{id}',
            
            // 订单相关
            'order_detail' => 'order:detail:{id}',
            'order_items' => 'order:items:{id}',
            
            // 系统相关
            'system_config' => 'system:config:{key}',
            'api_cache' => 'api:cache:{endpoint}:{params_hash}',
        ]
    ]
];

$cache2 = CacheKVServiceProvider::registerWithConfig($customConfig);

// 使用自定义模板
$userProfile = CacheKVFacade::getByTemplate('user_profile', ['id' => 200], function() {
    echo "  -> 从数据库获取用户档案 200\n";
    return [
        'id' => 200,
        'name' => 'John Doe',
        'bio' => 'Software Developer',
        'avatar' => 'avatar200.jpg',
        'preferences' => ['theme' => 'dark', 'language' => 'zh-CN']
    ];
});

$productDetail = CacheKVFacade::getByTemplate('product_detail', ['id' => 300], function() {
    echo "  -> 从API获取产品详情 300\n";
    return [
        'id' => 300,
        'name' => 'MacBook Pro M3',
        'price' => 1999,
        'description' => 'Latest MacBook Pro with M3 chip',
        'category' => 'Laptops',
        'brand' => 'Apple'
    ];
});

echo "用户档案: " . json_encode($userProfile, JSON_UNESCAPED_UNICODE) . "\n";
echo "产品详情: " . json_encode($productDetail, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案3：快速注册（适合简单场景）
// ========================================
echo "3. 快速注册（简单场景）\n";
echo "================================\n";

$cache3 = CacheKVServiceProvider::quickRegister('blog', 'dev', [
    'post' => 'post:{id}',
    'comment' => 'comment:{id}',
    'tag' => 'tag:{name}',
    'category' => 'category:{id}',
], 900); // 15分钟过期

$post = CacheKVFacade::getByTemplate('post', ['id' => 400], function() {
    echo "  -> 从数据库获取文章 400\n";
    return [
        'id' => 400,
        'title' => 'CacheKV 使用指南',
        'content' => '这是一篇关于 CacheKV 的详细使用指南...',
        'author' => 'Tech Writer',
        'published_at' => '2024-01-15 10:30:00',
        'tags' => ['PHP', 'Cache', 'Performance']
    ];
});

echo "博客文章: " . json_encode($post, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 方案4：多环境配置
// ========================================
echo "4. 多环境配置\n";
echo "================================\n";

// 开发环境配置
$devConfig = [
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 300 // 开发环境短过期时间
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'development',
        'version' => 'v1',
        'templates' => [
            'debug_info' => 'debug:info:{key}',
            'test_data' => 'test:data:{id}',
        ]
    ]
];

// 生产环境配置
$prodConfig = [
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 3600 // 生产环境长过期时间
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'production',
        'version' => 'v1',
        'templates' => [
            'analytics' => 'analytics:{type}:{date}',
            'performance' => 'performance:{metric}:{period}',
        ]
    ]
];

// 根据环境选择配置
$environment = 'development'; // 可以从环境变量获取
$envConfig = $environment === 'production' ? $prodConfig : $devConfig;

$cache4 = CacheKVServiceProvider::registerWithConfig($envConfig);

if ($environment === 'development') {
    $debugInfo = CacheKVFacade::getByTemplate('debug_info', ['key' => 'app_status'], function() {
        echo "  -> 获取调试信息\n";
        return [
            'status' => 'running',
            'memory_usage' => '45MB',
            'debug_mode' => true,
            'last_error' => null
        ];
    });
    echo "调试信息: " . json_encode($debugInfo, JSON_UNESCAPED_UNICODE) . "\n";
} else {
    $analytics = CacheKVFacade::getByTemplate('analytics', ['type' => 'daily', 'date' => '2024-01-15'], function() {
        echo "  -> 获取分析数据\n";
        return [
            'date' => '2024-01-15',
            'page_views' => 15420,
            'unique_visitors' => 8930,
            'bounce_rate' => 0.35
        ];
    });
    echo "分析数据: " . json_encode($analytics, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n";

// ========================================
// 方案5：配置文件管理
// ========================================
echo "5. 配置文件管理\n";
echo "================================\n";

// 获取默认配置
$defaultConfig = CacheKVServiceProvider::getDefaultConfig();
echo "默认配置信息:\n";
echo "  默认存储: " . $defaultConfig['default'] . "\n";
echo "  应用前缀: " . $defaultConfig['key_manager']['app_prefix'] . "\n";
echo "  环境前缀: " . $defaultConfig['key_manager']['env_prefix'] . "\n";
echo "  版本: " . $defaultConfig['key_manager']['version'] . "\n";
echo "  可用模板: " . implode(', ', array_keys($defaultConfig['key_manager']['templates'])) . "\n\n";

// ========================================
// 方案6：与工厂模式结合
// ========================================
echo "6. 与工厂模式结合\n";
echo "================================\n";

// 使用工厂模式设置全局配置
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 2400
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'hybrid',
        'env_prefix' => 'mixed',
        'version' => 'v1',
        'templates' => [
            'hybrid_data' => 'hybrid:data:{type}:{id}',
        ]
    ]
]);

// 使用辅助函数
$hybridData = cache_kv_get('hybrid_data', ['type' => 'user', 'id' => 500], function() {
    echo "  -> 获取混合数据\n";
    return ['type' => 'user', 'id' => 500, 'data' => 'hybrid approach works!'];
});

echo "混合模式数据: " . json_encode($hybridData, JSON_UNESCAPED_UNICODE) . "\n\n";

// ========================================
// 实际业务场景示例
// ========================================
echo "7. 实际业务场景示例\n";
echo "================================\n";

// 电商系统配置
$ecommerceConfig = [
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 1800
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'shop',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            'user_profile' => 'user:profile:{id}',
            'product_info' => 'product:info:{id}',
            'cart_items' => 'cart:items:{user_id}',
            'order_history' => 'order:history:{user_id}:page:{page}',
            'category_products' => 'category:products:{id}:sort:{sort}',
            'search_results' => 'search:results:{query}:{filters_hash}',
            'hot_deals' => 'hot:deals:{category}:{limit}',
        ]
    ]
];

CacheKVServiceProvider::registerWithConfig($ecommerceConfig);

// 模拟电商业务操作
class EcommerceService {
    public function getUserProfile($userId) {
        return CacheKVFacade::getByTemplate('user_profile', ['id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取用户档案 {$userId}\n";
            return [
                'id' => $userId,
                'name' => "User {$userId}",
                'email' => "user{$userId}@shop.com",
                'level' => 'VIP',
                'points' => rand(100, 1000)
            ];
        });
    }
    
    public function getProductInfo($productId) {
        return CacheKVFacade::getByTemplate('product_info', ['id' => $productId], function() use ($productId) {
            echo "  -> 从数据库获取产品信息 {$productId}\n";
            return [
                'id' => $productId,
                'name' => "Product {$productId}",
                'price' => rand(100, 1000),
                'stock' => rand(0, 100),
                'rating' => rand(35, 50) / 10
            ];
        });
    }
    
    public function getCartItems($userId) {
        return CacheKVFacade::getByTemplate('cart_items', ['user_id' => $userId], function() use ($userId) {
            echo "  -> 从数据库获取购物车 {$userId}\n";
            return [
                'user_id' => $userId,
                'items' => [
                    ['product_id' => 1, 'quantity' => 2, 'price' => 299],
                    ['product_id' => 2, 'quantity' => 1, 'price' => 599],
                ],
                'total' => 1197
            ];
        });
    }
}

$ecommerce = new EcommerceService();

$userProfile = $ecommerce->getUserProfile(600);
$productInfo = $ecommerce->getProductInfo(700);
$cartItems = $ecommerce->getCartItems(600);

echo "电商用户档案: " . json_encode($userProfile, JSON_UNESCAPED_UNICODE) . "\n";
echo "电商产品信息: " . json_encode($productInfo, JSON_UNESCAPED_UNICODE) . "\n";
echo "电商购物车: " . json_encode($cartItems, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== 配置使用完整指南完成 ===\n";
echo "🎯 配置方案选择建议：\n";
echo "  • 简单项目：使用快速注册\n";
echo "  • 中型项目：使用自定义配置\n";
echo "  • 大型项目：使用配置文件 + 环境管理\n";
echo "  • 企业项目：使用多环境配置 + 业务服务类\n";
echo "\n✨ 所有配置方案都已验证可用！\n";
