# CacheKV 入门

## 什么是 CacheKV？

CacheKV 是一个灵活、高性能的 PHP 键值缓存库。它旨在简化常见的缓存模式，并提供强大的功能来管理缓存数据。主要功能包括：

-   **自动获取**：轻松从缓存中检索数据，如果找不到项目，则自动回退到数据源。
-   **批量操作**：高效地一次性获取和设置多个缓存项。
-   **基于标签的失效**：使用标签对相关缓存项进行分组，方便地使整个组失效。
-   **滑动过期**：自动延长频繁访问的缓存项的生命周期。
-   **驱动无关**：通过统一的接口支持各种缓存后端（例如 Redis、Array）。
-   **可扩展**：允许您定义自定义缓存驱动程序并与现有 Redis 实例集成。

## 安装

CacheKV 可以通过 Composer 安装：

```bash
composer require asfop/cache-kv
```

## 基本用法

要开始使用 CacheKV，您通常需要设置您的 Redis 实例（如果您使用 Redis 驱动程序），然后注册 `CacheKVServiceProvider`。

以下是如何在 `index.php` 或应用程序引导文件中使用 CacheKV 的基本示例：

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\CacheKVServiceProvider;

// 1. 定义一个闭包来创建 Redis 实例
// 这允许您控制 Redis 客户端的实例化方式。
CacheKV::setRedisFactory(function () {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    // 您可以在此处添加更多 Redis 配置，例如身份验证或数据库选择
    // $redis->auth('您的密码');
    // $redis->select(0); // 选择数据库 0
    return $redis;
});

// 2. 注册 CacheKV 服务提供者
// 这将根据您的配置初始化默认缓存存储。
CacheKVServiceProvider::register();

// 3. 获取缓存实例
// 默认情况下，它将解析 cachekv.php 中配置的 'redis' 驱动程序
$cache = CacheManager::resolve('redis');

// 4. 基本缓存操作

// 设置一个带有 TTL（生存时间）为 60 秒的值
$cache->set('my_key', 'my_value', 60);

// 从缓存中获取一个值
$value = $cache->get('my_key');
echo "my_key 的值: " . $value . "\n"; // 输出: my_key 的值: my_value

// 使用回调获取值（如果缓存中未找到，则执行回调并缓存其结果）
$data = $cache->get('another_key', function () {
    echo "从源获取数据...\n";
    return 'data_from_source';
}, 300); // 缓存 300 秒
echo "another_key 的值: " . $data . "\n";

// 忘记一个键
$cache->forget('my_key');
$value = $cache->get('my_key');
echo "忘记后 my_key 的值: " . ($value === null ? 'null' : $value) . "\n"; // 输出: 忘记后 my_key 的值: null

// 设置标签（用于基于标签的失效）
$cache->setWithTag('user:1:profile', ['name' => 'Alice'], 'users', 3600);
$cache->setWithTag('user:2:profile', ['name' => 'Bob'], 'users', 3600);

// 清除与 'users' 标签关联的所有项目
$cache->clearTag('users');

$user1Profile = $cache->get('user:1:profile');
echo "清除标签后用户 1 的配置文件: " . ($user1Profile === null ? 'null' : json_encode($user1Profile)) . "\n"; // 输出: 清除标签后用户 1 的配置文件: null

// 批量操作
$keysToFetch = ['item:1', 'item:2', 'item:3'];
$fetchedItems = $cache->getMultiple($keysToFetch, function ($missingKeys) {
    echo "获取缺失的项目: " . implode(', ', $missingKeys) . "\n";
    $data = [];
    foreach ($missingKeys as $key) {
        $data[$key] = 'data_for_' . str_replace('item:', '', $key);
    }
    return $data;
}, 600);

print_r($fetchedItems);

```

## 配置

CacheKV 使用一个简单的配置文件，位于 `src/Config/cachekv.php`。您可以通过将数组传递给 `CacheKVServiceProvider::register()` 方法来覆盖这些设置。

`src/Config/cachekv.php` 示例：

```php
<?php
return [
    'default' => 'redis', // 默认使用的缓存存储
    'stores' => [
        'redis' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\RedisDriver::class, // Redis 驱动类
            'ttl' => 3600, // 此存储的默认 TTL（可选）
            'ttl_jitter' => 60, // 为 TTL 添加随机抖动（可选）
        ],
        // 您可以在此处定义其他存储，例如数组驱动
        'array' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\ArrayDriver::class,
            'ttl' => 300,
        ],
    ],
];
```

## 在 Bootscript 或应用程序引导中注册

对于大多数 PHP 应用程序，您将有一个中央引导文件（例如 `bootstrap.php`、`app.php` 或 `index.php` 本身），您可以在其中设置应用程序的服务和依赖项。这是注册 CacheKV 的理想位置。

以下是您的引导脚本的典型结构：

```php
<?php

// 自动加载 Composer 依赖项
require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVServiceProvider;

// 1. 配置您的 Redis 连接（或其他驱动程序特定设置）
// 当 RedisDriver 需要 Redis 实例时，将调用此闭包。
CacheKV::setRedisFactory(function () {
    $redis = new Redis();
    $redis->connect('your_redis_host', 6379);
    // $redis->auth('您的 Redis 密码');
    // $redis->select(1); // 使用数据库 1 进行缓存
    return $redis;
});

// 2. 注册 CacheKV 服务提供者
// 您可以选择性地传递一个数组来覆盖默认配置。
CacheKVServiceProvider::register([
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'ttl' => 7200, // 将 Redis 存储的默认 TTL 覆盖为 2 小时
        ],
    ],
]);

// 现在 CacheKV 已准备好在您的整个应用程序中使用。
// 您可以使用 CacheManager::resolve('store_name') 解析实例；

// 您在应用程序逻辑中可能如何使用它的示例：
// $cache = \Asfop\CacheKV\Cache\CacheManager::resolve('redis');
// $data = $cache->get('some_data', function() { /* 获取数据 */ });

```

通过遵循此模式，您可以确保 CacheKV 在应用程序的任何部分尝试使用它之前，已使用您所需的 Redis 连接和配置正确初始化。这种方法使您的 Redis 连接逻辑集中化且易于管理。