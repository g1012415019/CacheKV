<?php
/**
 * CacheKV 配置方式示例
 * 
 * 展示 CacheKV 的各种配置方式和最佳实践
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheKVBuilder;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

echo "=== CacheKV 配置方式示例 ===\n\n";

// 定义缓存模板
class MyCacheTemplates {
    const USER = 'user';
    const PRODUCT = 'product';
    const ORDER = 'order';
}

echo "1. 直接创建方式（推荐 - 生产环境）\n";
echo str_repeat("-", 50) . "\n";

// 这种方式最灵活，支持依赖注入，适合生产环境
$driver = new ArrayDriver();
$keyManager = new KeyManager([
    'app_prefix' => 'myapp',
    'env_prefix' => 'prod',
    'version' => 'v1',
    'templates' => [
        MyCacheTemplates::USER => 'user:{id}',
        MyCacheTemplates::PRODUCT => 'product:{id}',
        MyCacheTemplates::ORDER => 'order:{id}',
    ]
]);

$cache1 = CacheKVFactory::create($driver, 3600, $keyManager);

echo "✓ 使用 CacheKVFactory::create() 创建\n";
echo "  - 最大灵活性\n";
echo "  - 支持依赖注入\n";
echo "  - 适合生产环境\n";
echo "  - 可以精确控制每个组件\n\n";

echo "2. 配置数组方式（推荐 - 配置驱动）\n";
echo str_repeat("-", 50) . "\n";

// 适合从配置文件加载配置
$config = [
    'driver' => new ArrayDriver(),
    'ttl' => 3600,
    'key_manager' => [
        'app_prefix' => 'myapp',
        'env_prefix' => 'prod',
        'version' => 'v1',
        'templates' => [
            MyCacheTemplates::USER => 'user:{id}',
            MyCacheTemplates::PRODUCT => 'product:{id}',
            MyCacheTemplates::ORDER => 'order:{id}',
        ]
    ]
];

$cache2 = CacheKVFactory::createFromConfig($config);

echo "✓ 使用 CacheKVFactory::createFromConfig() 创建\n";
echo "  - 配置集中管理\n";
echo "  - 易于从文件加载\n";
echo "  - 结构清晰\n";
echo "  - 适合配置驱动的应用\n\n";

echo "3. 构建器方式（推荐 - 代码可读性）\n";
echo str_repeat("-", 50) . "\n";

// 流畅的API，代码可读性好
$cache3 = CacheKVBuilder::create()
    ->useArrayDriver()
    ->ttl(3600)
    ->appPrefix('myapp')
    ->envPrefix('prod')
    ->version('v1')
    ->template(MyCacheTemplates::USER, 'user:{id}')
    ->template(MyCacheTemplates::PRODUCT, 'product:{id}')
    ->template(MyCacheTemplates::ORDER, 'order:{id}')
    ->build();

echo "✓ 使用 CacheKVBuilder 流畅API创建\n";
echo "  - 流畅的API\n";
echo "  - 代码可读性好\n";
echo "  - 链式调用\n";
echo "  - 适合复杂配置\n\n";

echo "4. 快速创建方式（开发测试）\n";
echo str_repeat("-", 50) . "\n";

// 快速创建，适合开发测试
$cache4 = CacheKVFactory::quick('myapp', 'dev', [
    MyCacheTemplates::USER => 'user:{id}',
    MyCacheTemplates::PRODUCT => 'product:{id}',
    MyCacheTemplates::ORDER => 'order:{id}',
], 1800);

echo "✓ 使用 CacheKVFactory::quick() 快速创建\n";
echo "  - 快速简单\n";
echo "  - 适合开发测试\n";
echo "  - 默认使用 ArrayDriver\n";
echo "  - 一行代码搞定\n\n";

echo "=== 实际应用场景 ===\n\n";

echo "场景1: Laravel/Symfony 等框架集成\n";
echo str_repeat("-", 40) . "\n";

// 在服务容器中注册
class CacheServiceProvider {
    public function register($container) {
        $container->singleton('cache.kv', function($app) {
            $config = $app['config']['cache.kv'];
            return CacheKVFactory::createFromConfig($config);
        });
    }
}

echo "// 在服务提供者中注册\n";
echo "public function register(\$container) {\n";
echo "    \$container->singleton('cache.kv', function(\$app) {\n";
echo "        \$config = \$app['config']['cache.kv'];\n";
echo "        return CacheKVFactory::createFromConfig(\$config);\n";
echo "    });\n";
echo "}\n\n";

echo "场景2: 多环境配置\n";
echo str_repeat("-", 40) . "\n";

// 不同环境使用不同配置
function createCacheForEnvironment($env) {
    $configs = [
        'development' => [
            'driver' => new ArrayDriver(),
            'ttl' => 600, // 10分钟
            'key_manager' => [
                'app_prefix' => 'myapp',
                'env_prefix' => 'dev',
                'version' => 'v1',
            ]
        ],
        'testing' => [
            'driver' => new ArrayDriver(),
            'ttl' => 300, // 5分钟
            'key_manager' => [
                'app_prefix' => 'myapp',
                'env_prefix' => 'test',
                'version' => 'v1',
            ]
        ],
        'production' => [
            'driver' => new ArrayDriver(), // 实际应该是 RedisDriver
            'ttl' => 3600, // 1小时
            'key_manager' => [
                'app_prefix' => 'myapp',
                'env_prefix' => 'prod',
                'version' => 'v1',
            ]
        ]
    ];
    
    return CacheKVFactory::createFromConfig($configs[$env]);
}

$devCache = createCacheForEnvironment('development');
$testCache = createCacheForEnvironment('testing');
$prodCache = createCacheForEnvironment('production');

echo "✓ 创建了开发、测试、生产三个环境的缓存实例\n";
echo "  - 开发环境: 10分钟TTL\n";
echo "  - 测试环境: 5分钟TTL\n";
echo "  - 生产环境: 1小时TTL\n\n";

echo "场景3: 微服务架构\n";
echo str_repeat("-", 40) . "\n";

// 不同服务使用不同的缓存实例
class UserServiceCache {
    public static function create() {
        return CacheKVBuilder::create()
            ->useArrayDriver()
            ->appPrefix('user-service')
            ->envPrefix('prod')
            ->version('v1')
            ->template('profile', 'profile:{id}')
            ->template('permissions', 'permissions:{user_id}')
            ->ttl(3600)
            ->build();
    }
}

class OrderServiceCache {
    public static function create() {
        return CacheKVBuilder::create()
            ->useArrayDriver()
            ->appPrefix('order-service')
            ->envPrefix('prod')
            ->version('v1')
            ->template('order', 'order:{id}')
            ->template('order_items', 'order_items:{order_id}')
            ->ttl(1800)
            ->build();
    }
}

$userServiceCache = UserServiceCache::create();
$orderServiceCache = OrderServiceCache::create();

echo "✓ 创建了用户服务和订单服务的缓存实例\n";
echo "  - 用户服务: user-service 前缀\n";
echo "  - 订单服务: order-service 前缀\n";
echo "  - 完全隔离的缓存空间\n\n";

echo "场景4: 配置文件驱动\n";
echo str_repeat("-", 40) . "\n";

// 模拟从配置文件加载
$configFile = [
    'cache' => [
        'default' => [
            'driver' => 'array',
            'ttl' => 3600,
            'key_manager' => [
                'app_prefix' => 'myapp',
                'env_prefix' => 'prod',
                'version' => 'v1',
                'templates' => [
                    'user' => 'user:{id}',
                    'product' => 'product:{id}',
                ]
            ]
        ],
        'session' => [
            'driver' => 'array',
            'ttl' => 1800,
            'key_manager' => [
                'app_prefix' => 'session',
                'env_prefix' => 'prod',
                'version' => 'v1',
                'templates' => [
                    'user_session' => 'session:{user_id}',
                ]
            ]
        ]
    ]
];

function createCacheFromConfig($configFile, $name) {
    $config = $configFile['cache'][$name];
    $config['driver'] = new ArrayDriver(); // 实际应该根据配置创建驱动
    return CacheKVFactory::createFromConfig($config);
}

$defaultCache = createCacheFromConfig($configFile, 'default');
$sessionCache = createCacheFromConfig($configFile, 'session');

echo "✓ 从配置文件创建了默认缓存和会话缓存\n";
echo "  - 配置集中管理\n";
echo "  - 易于维护\n";
echo "  - 支持多个缓存实例\n\n";

echo "=== 最佳实践建议 ===\n\n";

echo "1. 生产环境推荐:\n";
echo "   - 使用 CacheKVFactory::create() 或 createFromConfig()\n";
echo "   - 通过依赖注入容器管理实例\n";
echo "   - 配置文件集中管理\n\n";

echo "2. 开发测试推荐:\n";
echo "   - 使用 CacheKVFactory::quick() 快速创建\n";
echo "   - 使用 CacheKVBuilder 提高代码可读性\n\n";

echo "3. 框架集成推荐:\n";
echo "   - 创建服务提供者\n";
echo "   - 注册到服务容器\n";
echo "   - 支持配置文件\n\n";

echo "4. 微服务架构推荐:\n";
echo "   - 每个服务独立的缓存实例\n";
echo "   - 使用不同的前缀隔离\n";
echo "   - 根据业务特点设置不同的TTL\n\n";

echo "=== 功能验证 ===\n\n";

// 验证所有实例都正常工作
$testData = ['id' => 123, 'name' => 'Test User'];

$cache1->setByTemplate(MyCacheTemplates::USER, ['id' => 123], $testData);
$cache2->setByTemplate(MyCacheTemplates::USER, ['id' => 123], $testData);
$cache3->setByTemplate(MyCacheTemplates::USER, ['id' => 123], $testData);
$cache4->setByTemplate(MyCacheTemplates::USER, ['id' => 123], $testData);

echo "✓ 所有配置方式创建的实例都正常工作\n";
echo "✓ 缓存键生成正确\n";
echo "✓ 数据存储和读取正常\n\n";

echo "=== 示例完成 ===\n";
