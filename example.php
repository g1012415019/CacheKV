<?php

require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheKVServiceProvider;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

// 示例1: 直接使用 CacheKV
echo "=== 示例1: 直接使用 CacheKV ===\n";

$driver = new ArrayDriver();
$cache = new CacheKV($driver, 3600);

// 基本操作
$cache->set('user:1', array('name' => 'John', 'age' => 30));
$user = $cache->get('user:1');
echo "用户信息: " . json_encode($user) . "\n";

// 带回调的获取
$product = $cache->get('product:1', function() {
    echo "从数据库获取产品信息...\n";
    return array('id' => 1, 'name' => 'iPhone', 'price' => 999);
});
echo "产品信息: " . json_encode($product) . "\n";

// 再次获取（应该从缓存获取）
$product2 = $cache->get('product:1', function() {
    echo "这不应该被执行\n";
    return null;
});
echo "产品信息（缓存）: " . json_encode($product2) . "\n";

// 标签操作
$cache->setWithTag('post:1', array('title' => 'Hello World'), array('posts', 'featured'));
$cache->setWithTag('post:2', array('title' => 'PHP Tutorial'), array('posts', 'tutorial'));

echo "文章1: " . json_encode($cache->get('post:1')) . "\n";
echo "文章2: " . json_encode($cache->get('post:2')) . "\n";

// 清除标签
$cache->clearTag('posts');
echo "清除posts标签后:\n";
echo "文章1: " . json_encode($cache->get('post:1')) . "\n";
echo "文章2: " . json_encode($cache->get('post:2')) . "\n";

// 批量操作
$keys = array('batch:1', 'batch:2', 'batch:3');
$results = $cache->getMultiple($keys, function($missingKeys) {
    echo "批量获取缺失的键: " . implode(', ', $missingKeys) . "\n";
    $data = array();
    foreach ($missingKeys as $key) {
        $data[$key] = array('key' => $key, 'value' => rand(1, 100));
    }
    return $data;
});

echo "批量结果: " . json_encode($results) . "\n";

// 统计信息
echo "缓存统计: " . json_encode($cache->getStats()) . "\n";

echo "\n=== 示例2: 使用门面 ===\n";

// 使用服务提供者注册
$config = array(
    'default' => 'array',
    'stores' => array(
        'array' => array(
            'driver' => ArrayDriver::class
        )
    ),
    'default_ttl' => 1800
);

CacheKVServiceProvider::register($config);

// 使用门面
CacheKVFacade::set('facade:test', 'Hello from Facade!');
echo "门面测试: " . CacheKVFacade::get('facade:test') . "\n";

// 检查是否存在
echo "键是否存在: " . (CacheKVFacade::has('facade:test') ? 'Yes' : 'No') . "\n";

// 删除
CacheKVFacade::forget('facade:test');
echo "删除后是否存在: " . (CacheKVFacade::has('facade:test') ? 'Yes' : 'No') . "\n";

echo "\n=== 示例完成 ===\n";
