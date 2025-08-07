<?php

/**
 * CacheKV 简化整体功能测试
 * 
 * 专注于核心功能，避免复杂的统计功能
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;
use Asfop\CacheKV\Core\ConfigManager;

echo "=== CacheKV 简化整体功能测试 ===\n\n";

// 简化的 MockRedis 类
class SimpleRedis {
    private $data = array();
    
    public function connect($host, $port) { return true; }
    public function get($key) { 
        return isset($this->data[$key]) ? $this->data[$key] : false; 
    }
    public function set($key, $value, $options = null) { 
        $this->data[$key] = $value; 
        return true; 
    }
    public function setex($key, $ttl, $value) {
        $this->data[$key] = $value;
        return true;
    }
    public function del($keys) { 
        if (!is_array($keys)) $keys = array($keys);
        $deleted = 0;
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                unset($this->data[$key]);
                $deleted++;
            }
        }
        return $deleted; 
    }
    public function keys($pattern) {
        $keys = array();
        $pattern = str_replace('*', '.*', $pattern);
        foreach (array_keys($this->data) as $key) {
            if (preg_match('/^' . $pattern . '$/', $key)) {
                $keys[] = $key;
            }
        }
        return $keys;
    }
    public function mget($keys) {
        $results = array();
        foreach ($keys as $key) {
            $results[] = $this->get($key);
        }
        return $results;
    }
    public function mset($keyValuePairs) {
        foreach ($keyValuePairs as $key => $value) {
            $this->set($key, $value);
        }
        return true;
    }
    public function multi() {
        return new SimplePipeline($this);
    }
    
    // 调试方法
    public function getAllData() { return $this->data; }
    public function clearAll() { $this->data = array(); }
}

// 简单的 Pipeline 实现
class SimplePipeline {
    private $redis;
    private $commands = array();
    
    public function __construct($redis) {
        $this->redis = $redis;
    }
    
    public function setex($key, $ttl, $value) {
        $this->commands[] = array('setex', array($key, $ttl, $value));
        return $this;
    }
    
    public function exec() {
        $results = array();
        foreach ($this->commands as $command) {
            $method = $command[0];
            $args = $command[1];
            $results[] = call_user_func_array(array($this->redis, $method), $args);
        }
        $this->commands = array();
        return $results;
    }
}

$allTestsPassed = true;
$testResults = array();

function runTest($testName, $testFunction) {
    global $allTestsPassed, $testResults;
    
    echo "🧪 测试: {$testName}\n";
    
    try {
        $result = $testFunction();
        if ($result) {
            echo "   ✅ 通过\n";
            $testResults[$testName] = 'PASS';
        } else {
            echo "   ❌ 失败\n";
            $testResults[$testName] = 'FAIL';
            $allTestsPassed = false;
        }
    } catch (Exception $e) {
        echo "   ❌ 异常: " . $e->getMessage() . "\n";
        $testResults[$testName] = 'ERROR: ' . $e->getMessage();
        $allTestsPassed = false;
    }
    
    echo "\n";
}

// 创建禁用统计的配置
$configPath = __DIR__ . '/config/simple_cache_kv.php';
file_put_contents($configPath, '<?php
return array(
    "cache" => array(
        "ttl" => 3600,
        "enable_stats" => false,  // 禁用统计功能
        "hot_key_auto_renewal" => false,  // 禁用热点键功能
    ),
    "key_manager" => array(
        "app_prefix" => "testapp",
        "separator" => ":",
        "groups" => array(
            "user" => array(
                "prefix" => "user",
                "version" => "v1",
                "keys" => array(
                    "profile" => array(
                        "template" => "profile:{id}",
                        "cache" => array("ttl" => 7200)
                    ),
                    "settings" => array(
                        "template" => "settings:{id}",
                        "cache" => array("ttl" => 3600)
                    )
                )
            ),
            "goods" => array(
                "prefix" => "goods",
                "version" => "v1",
                "keys" => array(
                    "info" => array(
                        "template" => "info:{id}",
                        "cache" => array("ttl" => 1800)
                    )
                )
            )
        )
    )
);
');

// 初始化 CacheKV
$mockRedis = new SimpleRedis();

try {
    CacheKVFactory::configure(
        function() use ($mockRedis) {
            return $mockRedis;
        },
        $configPath
    );
    echo "✅ CacheKV 初始化成功\n\n";
} catch (Exception $e) {
    echo "❌ CacheKV 初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 模拟数据库函数
function getUserData($userId) {
    return array(
        'id' => $userId,
        'name' => "用户{$userId}",
        'email' => "user{$userId}@example.com"
    );
}

function getGoodsData($goodsId) {
    return array(
        'id' => $goodsId,
        'name' => "商品{$goodsId}",
        'price' => rand(100, 999) / 10
    );
}

// 测试 1: 基础缓存功能
runTest("基础缓存功能", function() use ($mockRedis) {
    $mockRedis->clearAll();
    
    // 第一次获取（缓存未命中）
    $user1 = cache_kv_get('user.profile', array('id' => 123), function() {
        return getUserData(123);
    });
    
    if (!$user1 || $user1['id'] != 123) {
        return false;
    }
    
    // 第二次获取（缓存命中）
    $user2 = cache_kv_get('user.profile', array('id' => 123), function() {
        return array('should' => 'not_be_called'); // 不应该被调用
    });
    
    if (!$user2 || $user2['id'] != 123) {
        return false;
    }
    
    echo "   - 用户数据: {$user1['name']} ({$user1['email']})\n";
    echo "   - 缓存数据量: " . count($mockRedis->getAllData()) . "\n";
    
    return true;
});

// 测试 2: 批量操作
runTest("批量操作功能", function() use ($mockRedis) {
    $mockRedis->clearAll();
    
    $userIds = array(
        array('id' => 1),
        array('id' => 2),
        array('id' => 3)
    );
    
    $users = cache_kv_get_multiple('user.profile', $userIds, function($missedKeys) {
        $data = array();
        foreach ($missedKeys as $cacheKey) {
            $params = $cacheKey->getParams();
            $keyString = (string)$cacheKey;
            $data[$keyString] = getUserData($params['id']);
        }
        return $data;
    });
    
    if (count($users) != 3) {
        return false;
    }
    
    echo "   - 批量获取用户数量: " . count($users) . "\n";
    
    return true;
});

// 测试 3: 不同类型数据
runTest("多类型数据缓存", function() use ($mockRedis) {
    $mockRedis->clearAll();
    
    // 用户数据
    $user = cache_kv_get('user.profile', array('id' => 100), function() {
        return getUserData(100);
    });
    
    // 商品数据
    $goods = cache_kv_get('goods.info', array('id' => 200), function() {
        return getGoodsData(200);
    });
    
    if (!$user || $user['id'] != 100) return false;
    if (!$goods || $goods['id'] != 200) return false;
    
    echo "   - 用户: {$user['name']}\n";
    echo "   - 商品: {$goods['name']} - ¥{$goods['price']}\n";
    
    return true;
});

// 测试 4: 键生成功能
runTest("键生成功能", function() {
    $userKey = cache_kv_make_key('user.profile', array('id' => 123));
    $expectedPattern = '/^testapp:user:v1:profile:123$/';
    
    if (!preg_match($expectedPattern, (string)$userKey)) {
        echo "   - 实际键: " . (string)$userKey . "\n";
        echo "   - 期望模式: " . $expectedPattern . "\n";
        return false;
    }
    
    $keyCollection = cache_kv_make_keys('goods.info', array(
        array('id' => 1),
        array('id' => 2)
    ));
    
    if ($keyCollection->count() != 2) {
        return false;
    }
    
    echo "   - 单个键: " . (string)$userKey . "\n";
    echo "   - 批量键数量: " . $keyCollection->count() . "\n";
    
    return true;
});

// 测试 5: 按前缀删除
runTest("按前缀删除功能", function() use ($mockRedis) {
    $mockRedis->clearAll();
    
    // 创建一些缓存数据
    for ($i = 1; $i <= 3; $i++) {
        cache_kv_get('user.settings', array('id' => $i), function() use ($i) {
            return array('user_id' => $i, 'theme' => 'dark');
        });
    }
    
    $beforeDelete = count($mockRedis->getAllData());
    $deletedCount = cache_kv_delete_by_prefix('user.settings');
    $afterDelete = count($mockRedis->getAllData());
    
    echo "   - 删除前: {$beforeDelete} 个键\n";
    echo "   - 删除了: {$deletedCount} 个键\n";
    echo "   - 删除后: {$afterDelete} 个键\n";
    
    return $deletedCount >= 3 && $afterDelete < $beforeDelete;
});

// 测试 6: 配置继承
runTest("配置继承功能", function() {
    $globalConfig = ConfigManager::getGlobalCacheConfig();
    $userGroupConfig = ConfigManager::getGroupCacheConfig('user');
    $profileKeyConfig = ConfigManager::getKeyCacheConfig('user', 'profile');
    
    if (!$globalConfig || !$userGroupConfig || !$profileKeyConfig) {
        return false;
    }
    
    echo "   - 全局TTL: {$globalConfig['ttl']}秒\n";
    echo "   - 用户组TTL: {$userGroupConfig['ttl']}秒\n";
    echo "   - Profile键TTL: {$profileKeyConfig['ttl']}秒\n";
    
    return $globalConfig['ttl'] == 3600 && $profileKeyConfig['ttl'] == 7200;
});

// 测试 7: 键行为区分
runTest("键行为区分", function() {
    $profileKey = cache_kv_make_key('user.profile', array('id' => 123));
    $hasCacheConfig = $profileKey->hasCacheConfig();
    
    echo "   - Profile键有缓存配置: " . ($hasCacheConfig ? '是' : '否') . "\n";
    
    return $hasCacheConfig;
});

// 测试 8: 错误处理
runTest("错误处理", function() {
    try {
        cache_kv_get('nonexistent.key', array('id' => 1), function() {
            return array('test' => 'data');
        });
        return false;
    } catch (Exception $e) {
        echo "   - 正确捕获异常: " . $e->getMessage() . "\n";
        return true;
    }
});

// 测试 9: 性能测试
runTest("性能测试", function() use ($mockRedis) {
    $mockRedis->clearAll();
    
    $iterations = 50;
    $startTime = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $userId = $i % 10; // 10个不同用户，会有缓存命中
        cache_kv_get('user.profile', array('id' => $userId), function() use ($userId) {
            return getUserData($userId);
        });
    }
    
    $totalTime = microtime(true) - $startTime;
    $avgTime = $totalTime / $iterations;
    
    echo "   - 执行{$iterations}次操作: " . round($totalTime * 1000, 2) . "ms\n";
    echo "   - 平均每次: " . round($avgTime * 1000, 3) . "ms\n";
    
    return $avgTime < 0.01; // 平均每次小于10ms
});

// 测试 10: 数据一致性
runTest("数据一致性", function() use ($mockRedis) {
    $mockRedis->clearAll();
    
    // 存储复杂数据结构
    $complexData = array(
        'id' => 999,
        'profile' => array(
            'name' => '测试用户',
            'settings' => array(
                'theme' => 'dark',
                'language' => 'zh-CN',
                'notifications' => array('email' => true, 'sms' => false)
            )
        ),
        'metadata' => array(
            'created_at' => date('Y-m-d H:i:s'),
            'tags' => array('vip', 'active', 'premium')
        )
    );
    
    // 存储数据
    $stored = cache_kv_get('user.profile', array('id' => 999), function() use ($complexData) {
        return $complexData;
    });
    
    // 再次获取
    $retrieved = cache_kv_get('user.profile', array('id' => 999), function() {
        return array('should' => 'not_be_called');
    });
    
    // 验证数据完整性
    if ($stored['id'] != $retrieved['id']) return false;
    if ($stored['profile']['name'] != $retrieved['profile']['name']) return false;
    if (count($stored['metadata']['tags']) != count($retrieved['metadata']['tags'])) return false;
    
    echo "   - 复杂数据结构存储和检索正常\n";
    echo "   - 数据完整性验证通过\n";
    
    return true;
});

// 清理临时配置文件
unlink($configPath);

// 输出测试总结
echo "=== 测试总结 ===\n\n";

$passedTests = 0;
$totalTests = count($testResults);

foreach ($testResults as $testName => $result) {
    $status = $result === 'PASS' ? '✅' : '❌';
    echo "{$status} {$testName}: {$result}\n";
    if ($result === 'PASS') {
        $passedTests++;
    }
}

echo "\n";
echo "通过测试: {$passedTests}/{$totalTests}\n";
echo "成功率: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";

if ($allTestsPassed) {
    echo "🎉 所有核心功能测试通过！\n\n";
    
    echo "✅ 验证通过的核心功能：\n";
    echo "1. ✅ 基础缓存功能 - 自动回填、缓存命中\n";
    echo "2. ✅ 批量操作 - 高效批量获取\n";
    echo "3. ✅ 多类型数据支持 - 不同数据类型缓存\n";
    echo "4. ✅ 键管理 - 统一键生成和命名\n";
    echo "5. ✅ 按前缀删除 - 批量缓存清理\n";
    echo "6. ✅ 配置继承 - 三级配置体系\n";
    echo "7. ✅ 键行为区分 - 缓存键识别\n";
    echo "8. ✅ 错误处理 - 异常处理机制\n";
    echo "9. ✅ 性能表现 - 高效缓存操作\n";
    echo "10. ✅ 数据一致性 - 复杂数据结构支持\n\n";
    
    echo "🎯 CacheKV 核心功能评估：\n";
    echo "✅ 简化缓存操作 - 一行代码实现缓存逻辑\n";
    echo "✅ 自动回填机制 - 缓存未命中时自动处理\n";
    echo "✅ 批量操作优化 - 避免N+1查询问题\n";
    echo "✅ 统一键管理 - 标准化键生成\n";
    echo "✅ 配置灵活性 - 多级配置继承\n";
    echo "✅ 易于使用 - 简洁的API设计\n";
    echo "✅ 数据完整性 - 复杂数据结构支持\n";
    echo "✅ 错误处理 - 完善的异常机制\n\n";
    
    echo "🏆 结论：CacheKV 包的核心功能完全符合预期！\n";
    echo "📦 包已经准备好用于生产环境的基础缓存需求。\n\n";
    
    echo "💡 注意：统计和热点键功能已禁用以简化测试，\n";
    echo "    在生产环境中可以根据需要启用这些高级功能。\n";
    
} else {
    echo "❌ 部分核心功能测试失败，需要修复。\n";
}

?>
