<?php
/**
 * CacheKV 高级功能示例
 * 
 * 展示 CacheKV 的高级功能和最佳实践
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "=== CacheKV 高级功能示例 ===\n\n";

// 高级缓存模板定义
class AdvancedCacheTemplates {
    const USER_PROFILE = 'user_profile';
    const USER_POSTS = 'user_posts';
    const POST_COMMENTS = 'post_comments';
    const CATEGORY_PRODUCTS = 'category_products';
    const ANALYTICS_DATA = 'analytics_data';
    const RATE_LIMIT = 'rate_limit';
}

// 配置多环境支持
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'advanced_demo',
        'env_prefix' => 'prod',
        'version' => 'v2', // 版本升级示例
        'templates' => [
            AdvancedCacheTemplates::USER_PROFILE => 'user:profile:{id}',
            AdvancedCacheTemplates::USER_POSTS => 'user:posts:{user_id}:{page}',
            AdvancedCacheTemplates::POST_COMMENTS => 'post:comments:{post_id}:{sort}',
            AdvancedCacheTemplates::CATEGORY_PRODUCTS => 'category:products:{category_id}:{page}:{filters_hash}',
            AdvancedCacheTemplates::ANALYTICS_DATA => 'analytics:{type}:{date}:{granularity}',
            AdvancedCacheTemplates::RATE_LIMIT => 'rate_limit:{user_id}:{action}:{window}',
        ]
    ]
]);

$cache = CacheKVFactory::store();

echo "1. 复杂参数的缓存键生成\n";
echo str_repeat("-", 40) . "\n";

// 复杂查询参数的缓存
$filters = ['price_min' => 100, 'price_max' => 500, 'brand' => 'Apple'];
$filtersHash = md5(json_encode($filters));

$products = $cache->getByTemplate(
    AdvancedCacheTemplates::CATEGORY_PRODUCTS,
    [
        'category_id' => 1,
        'page' => 1,
        'filters_hash' => $filtersHash
    ],
    function() use ($filters) {
        echo "  [DB] 查询分类商品，筛选条件: " . json_encode($filters) . "\n";
        return [
            'products' => [
                ['id' => 1, 'name' => 'iPhone 15', 'price' => 999],
                ['id' => 2, 'name' => 'MacBook Pro', 'price' => 1999],
            ],
            'total' => 2,
            'filters' => $filters
        ];
    }
);

echo "找到 {$products['total']} 个商品\n\n";

echo "2. 分层缓存策略\n";
echo str_repeat("-", 40) . "\n";

class LayeredCacheService {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function getUserWithPosts($userId, $page = 1) {
        // 第一层：用户基本信息（长期缓存）
        $user = $this->cache->getByTemplate(
            AdvancedCacheTemplates::USER_PROFILE,
            ['id' => $userId],
            function() use ($userId) {
                echo "  [DB] 查询用户 {$userId} 基本信息\n";
                return [
                    'id' => $userId,
                    'name' => "User {$userId}",
                    'email' => "user{$userId}@example.com"
                ];
            },
            7200 // 2小时
        );
        
        // 第二层：用户文章列表（短期缓存）
        $posts = $this->cache->getByTemplate(
            AdvancedCacheTemplates::USER_POSTS,
            ['user_id' => $userId, 'page' => $page],
            function() use ($userId, $page) {
                echo "  [DB] 查询用户 {$userId} 的文章，第 {$page} 页\n";
                return [
                    ['id' => 1, 'title' => 'First Post', 'created_at' => '2024-01-01'],
                    ['id' => 2, 'title' => 'Second Post', 'created_at' => '2024-01-02'],
                ];
            },
            600 // 10分钟
        );
        
        return [
            'user' => $user,
            'posts' => $posts
        ];
    }
}

$layeredService = new LayeredCacheService($cache);
$userWithPosts = $layeredService->getUserWithPosts(1);
echo "用户: {$userWithPosts['user']['name']}\n";
echo "文章数: " . count($userWithPosts['posts']) . "\n\n";

echo "3. 缓存预热策略\n";
echo str_repeat("-", 40) . "\n";

class CacheWarmupService {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function warmupPopularUsers($userIds) {
        echo "  开始预热用户缓存...\n";
        
        // 批量预热用户数据
        $userKeys = [];
        foreach ($userIds as $userId) {
            $userKeys[] = $this->cache->makeKey(AdvancedCacheTemplates::USER_PROFILE, ['id' => $userId]);
        }
        
        $this->cache->getMultiple($userKeys, function($missingKeys) {
            echo "  [Warmup] 预热 " . count($missingKeys) . " 个用户的缓存\n";
            $result = [];
            foreach ($missingKeys as $key) {
                if (preg_match('/user:profile:(\d+)$/', $key, $matches)) {
                    $userId = (int)$matches[1];
                    $result[$key] = [
                        'id' => $userId,
                        'name' => "Popular User {$userId}",
                        'email' => "user{$userId}@example.com",
                        'followers' => rand(1000, 10000)
                    ];
                }
            }
            return $result;
        });
        
        echo "  预热完成\n";
    }
}

$warmupService = new CacheWarmupService($cache);
$warmupService->warmupPopularUsers([10, 11, 12, 13, 14]);
echo "\n";

echo "4. 防缓存穿透策略\n";
echo str_repeat("-", 40) . "\n";

class AntiPenetrationService {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function getUser($userId) {
        return $this->cache->getByTemplate(
            AdvancedCacheTemplates::USER_PROFILE,
            ['id' => $userId],
            function() use ($userId) {
                echo "  [DB] 查询用户 {$userId}\n";
                
                // 模拟用户不存在的情况
                if ($userId == 999) {
                    echo "  [DB] 用户 {$userId} 不存在\n";
                    return null; // 返回 null，CacheKV 会缓存这个 null 值
                }
                
                return [
                    'id' => $userId,
                    'name' => "User {$userId}",
                    'email' => "user{$userId}@example.com"
                ];
            },
            300 // 对于不存在的数据，使用较短的缓存时间
        );
    }
}

$antiPenetrationService = new AntiPenetrationService($cache);

// 第一次查询不存在的用户
echo "查询不存在的用户 999:\n";
$user999 = $antiPenetrationService->getUser(999);
echo "结果: " . ($user999 ? 'found' : 'not found') . "\n";

// 第二次查询，会从缓存获取 null 值，不会再查询数据库
echo "再次查询用户 999（从缓存获取 null）:\n";
$user999Again = $antiPenetrationService->getUser(999);
echo "结果: " . ($user999Again ? 'found' : 'not found') . "\n\n";

echo "5. 限流缓存应用\n";
echo str_repeat("-", 40) . "\n";

class RateLimitService {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function checkRateLimit($userId, $action, $maxRequests = 10, $windowSeconds = 60) {
        $windowStart = floor(time() / $windowSeconds) * $windowSeconds;
        
        $key = $this->cache->makeKey(AdvancedCacheTemplates::RATE_LIMIT, [
            'user_id' => $userId,
            'action' => $action,
            'window' => $windowStart
        ]);
        
        $currentCount = $this->cache->get($key, function() {
            return 0;
        });
        
        if ($currentCount >= $maxRequests) {
            return false; // 超出限制
        }
        
        // 增加计数
        $this->cache->set($key, $currentCount + 1, $windowSeconds);
        
        return true; // 允许请求
    }
}

$rateLimitService = new RateLimitService($cache);

echo "测试用户 1 的 API 调用限流（每分钟最多 3 次）:\n";
for ($i = 1; $i <= 5; $i++) {
    $allowed = $rateLimitService->checkRateLimit(1, 'api_call', 3, 60);
    echo "  第 {$i} 次调用: " . ($allowed ? '允许' : '被限流') . "\n";
}
echo "\n";

echo "6. 分析数据缓存\n";
echo str_repeat("-", 40) . "\n";

class AnalyticsService {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function getDailyStats($date, $type = 'pv') {
        return $this->cache->getByTemplate(
            AdvancedCacheTemplates::ANALYTICS_DATA,
            [
                'type' => $type,
                'date' => $date,
                'granularity' => 'daily'
            ],
            function() use ($date, $type) {
                echo "  [Analytics] 计算 {$date} 的 {$type} 数据\n";
                // 模拟复杂的分析计算
                return [
                    'date' => $date,
                    'type' => $type,
                    'value' => rand(1000, 10000),
                    'calculated_at' => date('Y-m-d H:i:s')
                ];
            },
            3600 // 1小时缓存，分析数据计算成本高
        );
    }
    
    public function getHourlyStats($date, $hour, $type = 'pv') {
        return $this->cache->getByTemplate(
            AdvancedCacheTemplates::ANALYTICS_DATA,
            [
                'type' => $type,
                'date' => $date,
                'granularity' => "hourly_{$hour}"
            ],
            function() use ($date, $hour, $type) {
                echo "  [Analytics] 计算 {$date} {$hour}:00 的 {$type} 数据\n";
                return [
                    'date' => $date,
                    'hour' => $hour,
                    'type' => $type,
                    'value' => rand(100, 1000),
                    'calculated_at' => date('Y-m-d H:i:s')
                ];
            },
            1800 // 30分钟缓存
        );
    }
}

$analyticsService = new AnalyticsService($cache);

$dailyPV = $analyticsService->getDailyStats('2024-01-01', 'pv');
echo "2024-01-01 PV: {$dailyPV['value']}\n";

$hourlyPV = $analyticsService->getHourlyStats('2024-01-01', 14, 'pv');
echo "2024-01-01 14:00 PV: {$hourlyPV['value']}\n\n";

echo "7. 缓存键模式匹配\n";
echo str_repeat("-", 40) . "\n";

// 设置一些测试数据
$cache->set('user:profile:1', ['name' => 'User 1']);
$cache->set('user:profile:2', ['name' => 'User 2']);
$cache->set('user:posts:1:1', ['title' => 'Post 1']);
$cache->set('product:info:1', ['name' => 'Product 1']);

// 查找所有用户资料缓存
$userProfileKeys = $cache->keys('*user:profile:*');
echo "用户资料缓存键: " . implode(', ', $userProfileKeys) . "\n";

// 查找所有用户相关缓存
$userKeys = $cache->keys('*user:*');
echo "用户相关缓存键: " . implode(', ', $userKeys) . "\n\n";

echo "8. 缓存版本管理\n";
echo str_repeat("-", 40) . "\n";

// 模拟版本升级场景
echo "当前版本: v2\n";
echo "升级到 v3 后，缓存键会自动隔离\n";

// 创建新版本的配置
CacheKVFactory::setDefaultConfig([
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => new ArrayDriver(),
            'ttl' => 3600
        ]
    ],
    'key_manager' => [
        'app_prefix' => 'advanced_demo',
        'env_prefix' => 'prod',
        'version' => 'v3', // 版本升级
        'templates' => [
            AdvancedCacheTemplates::USER_PROFILE => 'user:profile:{id}',
        ]
    ]
]);

$cacheV3 = CacheKVFactory::store();
$keyV3 = $cacheV3->makeKey(AdvancedCacheTemplates::USER_PROFILE, ['id' => 1]);
echo "v3 版本的用户缓存键: {$keyV3}\n";
echo "v2 和 v3 版本的缓存完全隔离\n\n";

echo "=== 高级功能演示完成 ===\n";
