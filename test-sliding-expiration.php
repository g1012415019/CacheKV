<?php

require_once 'vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

echo "🧪 测试滑动过期功能\n";
echo str_repeat("=", 50) . "\n";

// 创建缓存实例，设置较短的默认 TTL 用于测试
$cache = new CacheKV(new ArrayDriver(), 5); // 5秒默认过期

// 测试1: 验证滑动过期功能
echo "📋 测试1: 滑动过期功能\n";

$key = 'sliding_test';
$value = 'test_value';

// 设置缓存，指定 3 秒过期
$cache->set($key, $value, 3);
echo "✅ 设置缓存: {$key} = {$value} (3秒过期)\n";

// 立即获取，应该命中缓存并延长过期时间
sleep(1);
$result1 = $cache->get($key);
echo "✅ 1秒后获取: {$result1} (应该延长到3秒过期)\n";

// 再等2秒，如果没有滑动过期，缓存应该已经过期
sleep(2);
$result2 = $cache->get($key);
echo "✅ 再等2秒后获取: " . ($result2 ?: 'null') . " (滑动过期生效，仍然有效)\n";

// 再等2秒，现在应该过期了
sleep(2);
$result3 = $cache->get($key);
echo "✅ 再等2秒后获取: " . ($result3 ?: 'null') . " (现在应该过期了)\n";

echo "\n";

// 测试2: 验证带回调的滑动过期
echo "📋 测试2: 带回调的滑动过期\n";

$callCount = 0;
$callback = function() use (&$callCount) {
    $callCount++;
    echo "  🔄 执行回调函数 (第{$callCount}次)\n";
    return "callback_value_{$callCount}";
};

// 第一次获取，缓存未命中，执行回调
$result1 = $cache->get('callback_test', $callback, 3);
echo "✅ 第一次获取: {$result1}\n";

// 立即再次获取，应该从缓存获取，不执行回调
$result2 = $cache->get('callback_test', $callback, 3);
echo "✅ 立即再次获取: {$result2} (应该从缓存获取，不执行回调)\n";

// 等待1秒后获取，应该仍然从缓存获取并延长过期时间
sleep(1);
$result3 = $cache->get('callback_test', $callback, 3);
echo "✅ 1秒后获取: {$result3} (滑动过期，延长TTL)\n";

echo "\n";

// 测试3: 验证不同TTL的滑动过期
echo "📋 测试3: 不同TTL的滑动过期\n";

$cache->set('ttl_test', 'original_value', 2);
echo "✅ 设置缓存: ttl_test = original_value (2秒过期)\n";

sleep(1);
// 使用不同的TTL获取，应该使用新的TTL延长过期时间
$result = $cache->get('ttl_test', null, 5);
echo "✅ 1秒后用5秒TTL获取: {$result} (应该延长到5秒过期)\n";

sleep(3);
// 3秒后仍然应该有效（因为延长到了5秒）
$result = $cache->get('ttl_test');
echo "✅ 3秒后获取: " . ($result ?: 'null') . " (应该仍然有效)\n";

echo "\n";

// 显示统计信息
$stats = $cache->getStats();
echo "📊 缓存统计:\n";
echo "   命中次数: {$stats['hits']}\n";
echo "   未命中次数: {$stats['misses']}\n";
echo "   命中率: {$stats['hit_rate']}%\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎉 滑动过期功能测试完成！\n";

echo "\n💡 滑动过期功能说明：\n";
echo "- 当缓存命中时，会自动延长缓存的过期时间\n";
echo "- 延长的时间使用传入的TTL参数，如果没有则使用默认TTL\n";
echo "- 这样可以确保热点数据始终保持在缓存中\n";
echo "- 只有在数据不再被访问时才会真正过期\n";
