# 用户信息缓存（单条数据获取）

## 场景描述
在许多应用程序中，用户信息是频繁被访问的数据。如果每次请求都直接从数据库获取，会导致数据库压力增大，响应时间变慢。

## 问题痛点
- **数据库压力**: 频繁的数据库查询会增加数据库服务器的负载。
- **响应时间**: 从数据库获取数据通常比从缓存获取慢，影响用户体验。
- **代码冗余**: 每次获取用户信息时，都需要手动编写重复的缓存逻辑（检查缓存、从数据库获取、回填缓存）。

## 使用 DataCache 后的解决方案

DataCache 封装了“先从缓存读取，若无则从数据源获取并回填缓存”的模式，极大地简化了代码。

### 示例代码

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Asfop\DataCache\Cache\Drivers\ArrayDriver;
use Asfop\DataCache\DataCache;

// 假设这是你的数据库查询函数
function fetchUserFromDatabase(int $userId): array
{
    // 模拟数据库查询延迟
    sleep(1); 
    return ['id' => $userId, 'name' => 'User ' . $userId, 'email' => 'user' . $userId . '@example.com'];
}

// 1. 初始化 DataCache 实例 (使用内存数组作为缓存后端)
$arrayDriver = new ArrayDriver();
$cache = new DataCache($arrayDriver, 3600); // 默认缓存有效期 3600 秒

// 2. 获取用户信息
$userId = 1;

echo "第一次获取用户 {$userId} 信息 (应从数据库获取并缓存)...
";
$user = $cache->get("user:{$userId}", function() use ($userId) {
    echo "从数据库获取用户 {$userId} 信息...
";
    return fetchUserFromDatabase($userId);
}, 60); // 缓存 60 秒
print_r($user);

echo "
第二次获取用户 {$userId} 信息 (应从缓存获取)...
";
$user = $cache->get("user:{$userId}", function() use ($userId) {
    echo "从数据库获取用户 {$userId} 信息...
"; // 这行不应该被执行
    return fetchUserFromDatabase($userId);
}, 60);
print_r($user);

// 模拟用户数据更新，清除缓存
echo "
模拟用户 {$userId} 数据更新，清除缓存...
";
$cache->forget("user:{$userId}");

echo "
第三次获取用户 {$userId} 信息 (缓存已清除，应再次从数据库获取)...
";
$user = $cache->get("user:{$userId}", function() use ($userId) {
    echo "从数据库获取用户 {$userId} 信息...
";
    return fetchUserFromDatabase($userId);
}, 60);
print_r($user);

?>
```

## 优势
- **代码简洁**: 无需手动编写复杂的缓存判断和回填逻辑。
- **性能提升**: 减少了对数据库的直接访问，提高了数据获取速度。
- **防止缓存击穿**: 即使缓存中没有数据，也会通过回调函数从数据源获取并回填，避免了大量请求直接打到数据库。
- **统一管理**: 缓存的过期时间集中管理，易于维护。
