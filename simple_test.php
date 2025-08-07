<?php
/**
 * CacheKV 简单测试用例
 */

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 定义Redis类常量
class Redis {
    const PIPELINE = 1;
}

// 简单的模拟Redis类
class SimpleRedis {
    const PIPELINE = 1; // 模拟Redis::PIPELINE常量
    
    private $data = array();
    
    public function connect($host, $port) { return true; }
    public function get($key) { return isset($this->data[$key]) ? $this->data[$key] : null; }
    public function set($key, $value, $ttl = null) { $this->data[$key] = $value; return true; }
    public function setex($key, $ttl, $value) { $this->data[$key] = $value; return true; }
    public function delete($key) { unset($this->data[$key]); return true; }
    public function exists($key) { return isset($this->data[$key]); }
    public function mget($keys) { 
        $result = array();
        foreach ($keys as $key) {
            $result[] = $this->get($key);
        }
        return $result;
    }
    public function mset($keyValues, $ttl = null) {
        foreach ($keyValues as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }
    public function flushDB() { $this->data = array(); return true; }
    
    // 统计相关的空方法
    public function pipeline() { return $this; }
    public function multi() { return $this; }
    public function exec() { return array(); }
    public function incr($key) { return 1; }
    public function incrBy($key, $increment) { return $increment; }
    public function hincrby($key, $field, $increment) { return $increment; }
    public function hgetall($key) { return array(); }
    public function zadd($key, $score, $member) { return 1; }
    public function zrevrange($key, $start, $stop, $withscores = false) { return array(); }
}

// 创建简单配置
$configContent = '<?php
return array(
    "cache" => array(
        "ttl" => 3600,
        "enable_stats" => false, // 关闭统计功能
        "enable_null_cache" => true,
        "null_cache_ttl" => 300,
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
                        "cache" => array("ttl" => 1800)
                    ),
                ),
            ),
        ),
    ),
);';

file_put_contents(__DIR__ . '/simple_config.php', $configContent);

// 配置CacheKV
$redis = new SimpleRedis();
$redis->connect('127.0.0.1', 6379);

CacheKVFactory::configure(function() use ($redis) {
    return $redis;
}, __DIR__ . '/simple_config.php');

echo "🚀 开始简单测试\n";

// 测试1: 基础功能
echo "\n=== 测试1: 基础功能 ===\n";

// 第一次获取（缓存未命中）
echo "第一次获取用户资料:\n";
$user1 = cache_kv_get('user.profile', ['id' => 123], function() {
    echo "🔍 从数据库查询用户 123\n";
    return array('id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com');
});
echo "结果: " . json_encode($user1, JSON_UNESCAPED_UNICODE) . "\n";

// 第二次获取（缓存命中）
echo "\n第二次获取用户资料:\n";
$user2 = cache_kv_get('user.profile', ['id' => 123], function() {
    echo "❌ 这个回调不应该被执行（缓存应该命中）\n";
    return array('id' => 123, 'name' => 'John Doe', 'email' => 'john@example.com');
});
echo "结果: " . json_encode($user2, JSON_UNESCAPED_UNICODE) . "\n";

// 验证两次结果相同
if ($user1 === $user2) {
    echo "✅ 缓存功能正常工作\n";
} else {
    echo "❌ 缓存功能异常\n";
}

// 测试2: 批量操作
echo "\n=== 测试2: 批量操作 ===\n";

$paramsList = [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
];

$users = cache_kv_get_multiple('user.profile', $paramsList, function($missedKeys) {
    echo "缓存未命中的键数量: " . count($missedKeys) . "\n";
    
    $results = array();
    foreach ($missedKeys as $cacheKey) {
        $params = $cacheKey->getParams();
        $keyString = (string)$cacheKey;
        echo "🔍 查询用户 {$params['id']}\n";
        $results[$keyString] = array(
            'id' => $params['id'],
            'name' => "User {$params['id']}",
            'email' => "user{$params['id']}@example.com"
        );
    }
    return $results;
});

echo "批量获取结果:\n";
foreach ($users as $key => $user) {
    echo "  {$key}: {$user['name']}\n";
}

// 测试3: 不同数据类型
echo "\n=== 测试3: 不同数据类型 ===\n";

// 字符串
$str = cache_kv_get('user.profile', ['id' => 'str'], function() {
    return "这是一个字符串";
});
echo "字符串: {$str}\n";

// 数组
$arr = cache_kv_get('user.profile', ['id' => 'arr'], function() {
    return ['key1' => 'value1', 'key2' => 'value2'];
});
echo "数组: " . json_encode($arr, JSON_UNESCAPED_UNICODE) . "\n";

// null值
$null = cache_kv_get('user.profile', ['id' => 'null'], function() {
    return null;
});
echo "null值: " . var_export($null, true) . "\n";

// 布尔值
$bool = cache_kv_get('user.profile', ['id' => 'bool'], function() {
    return false;
});
echo "布尔值: " . var_export($bool, true) . "\n";

// 测试4: 获取键对象
echo "\n=== 测试4: 获取键对象 ===\n";

$keys = cache_kv_get_keys('user.profile', [
    ['id' => 'key1'],
    ['id' => 'key2']
]);

foreach ($keys as $keyString => $keyObj) {
    echo "键: {$keyString}\n";
    echo "参数: " . json_encode($keyObj->getParams()) . "\n";
    echo "有缓存配置: " . ($keyObj->hasCacheConfig() ? '是' : '否') . "\n";
    echo "---\n";
}

echo "\n✅ 所有测试完成！\n";

// 清理
unlink(__DIR__ . '/simple_config.php');
echo "✅ 清理完成\n";
