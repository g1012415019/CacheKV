# 处理空数据或不存在的数据（缓存穿透防护）

## 场景描述
当应用程序频繁查询一个在缓存和数据源中都不存在的键时，每次查询都会穿透缓存直接访问数据源，这被称为“缓存穿透”。恶意攻击者可能利用这一点，通过大量查询不存在的键来耗尽数据源资源。

## 问题痛点
- **数据源压力增大**: 大量不存在的查询直接打到数据库或后端服务，导致其负载过高甚至崩溃。
- **资源浪费**: 每次查询都进行不必要的计算和网络请求。
- **服务不稳定**: 缓存穿透可能导致服务响应变慢或不可用。

## 使用 CacheKV 后的解决方案

CacheKV 的 `get` 方法在回调函数返回 `null` 时，也会将 `null` 值缓存起来。这意味着即使数据源返回空结果，该空结果也会被缓存一段时间，从而有效防止了后续对相同不存在键的查询穿透到数据源。

### 示例代码

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKV;

// 模拟从数据库获取用户，可能返回 null
function findUserInDatabase(int $userId): ?array
{
    echo "从数据库查找用户 ID: {$userId}...\n";
    // 模拟数据库查询延迟
    sleep(1);
    // 假设只有 ID 为 100 的用户存在
    if ($userId === 100) {
        return ['id' => 100, 'name' => 'Existing User'];
    }
    return null; // 用户不存在
}

// 1. 初始化 CacheKV 实例
$arrayDriver = new ArrayDriver();
$cache = new CacheKV($arrayDriver, 3600);

// 2. 查询一个不存在的用户
$nonExistentUserId = 999;
echo "第一次查询不存在的用户 {$nonExistentUserId} (应从数据库获取并缓存 null)...\n";
$user = $cache->get("user:{$nonExistentUserId}", function() use ($nonExistentUserId) {
    return findUserInDatabase($nonExistentUserId);
}, 60); // 缓存 null 60 秒
var_dump($user);

echo "\n第二次查询不存在的用户 {$nonExistentUserId} (应从缓存获取 null)...\n";
$user = $cache->get("user:{$nonExistentUserId}", function() use ($nonExistentUserId) {
    echo "从数据库查找用户 ID: {$nonExistentUserId}...\n"; // 这行不应该被执行
    return findUserInDatabase($nonExistentUserId);
}, 60);
var_dump($user);

// 3. 查询一个存在的用户
$existentUserId = 100;
echo "\n第一次查询存在的用户 {$existentUserId} (应从数据库获取并缓存)...\n";
$user = $cache->get("user:{$existentUserId}", function() use ($existentUserId) {
    return findUserInDatabase($existentUserId);
}, 60);
print_r($user);

echo "\n第二次查询存在的用户 {$existentUserId} (应从缓存获取)...\n";
$user = $cache->get("user:{$existentUserId}", function() use ($existentUserId) {
    echo "从数据库查找用户 ID: {$existentUserId}...\n"; // 这行不应该被执行
    return findUserInDatabase($existentUserId);
}, 60);
print_r($user);

?>
```

## 优势
- **有效防止缓存穿透**: 即使是空结果也会被缓存，避免了对数据源的无效查询。
- **减轻数据源压力**: 减少了不必要的数据库或后端服务访问，保护了数据源的稳定性。
- **提高系统性能**: 减少了无效的网络请求和计算，提高了应用程序的整体响应速度。
- **简化逻辑**: 开发者无需手动判断和缓存空结果，CacheKV 自动处理。

```