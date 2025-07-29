# CacheKV

[![Latest Version on Packagist](https://img.shields.io/packagist/v/asfop/data-cache.svg?style=flat-square)](https://packagist.org/packages/asfop/data-cache)
[![Software License](https://img.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHP Version Require](https://img.shields.io/packagist/php/asfop/data-cache?style=flat-square)](https://packagist.org/packages/asfop/data-cache)

**CacheKV** 是一个专注于简化数据获取流程的 PHP 键值对缓存封装库。它通过提供统一的接口，自动化了‘先从缓存读取，若无则从数据源获取并回填缓存’这一常见模式。该库支持单条及批量数据操作、基于标签的缓存失效管理，并提供基础的性能统计功能，旨在显著提升应用程序的数据访问效率。

## 核心特性

-   **多驱动支持**：兼容 Redis/Memcached 等多种缓存驱动。
-   **批量操作优化**：针对批量数据获取场景进行优化，减少回源次数。
-   **缓存标签系统**：支持按分类管理缓存项，方便批量失效。
-   **智能统计监控**：提供缓存命中率等实时统计数据。
-   **自动序列化**：统一处理数据存储格式，简化开发。
-   **自动化回源回填**：封装“缓存-回源-回填”模式，减少样板代码。
-   **基于访问频率的自动续期（滑动过期）**：热点数据在被访问时自动延长其过期时间，确保常用数据始终保持缓存。

## 解决的问题与使用场景对比

在没有统一缓存管理机制的应用程序中，开发者常面临以下挑战：

-   **重复的缓存逻辑**: 每次需要缓存数据时，都需手动编写重复代码，包括键生成、数据序列化/反序列化、缓存检查、数据源回源及缓存回填等，导致代码冗余、难以维护且易出错。
-   **复杂的缓存失效**: 手动管理缓存失效（例如，当数据源更新时）容易遗漏，特别是对于逻辑上相关的多个缓存项，批量失效操作复杂且易导致数据不一致。
-   **缓存后端耦合**: 应用程序代码直接依赖特定缓存客户端（如 `Redis` 扩展），导致缓存技术栈难以更换，增加了系统重构成本。
-   **缺乏统一接口**: 不同模块可能采用不同的缓存实现方式，导致缓存管理分散，难以统一监控和优化。

**CacheKV** 通过提供一个统一、自动化且可扩展的解决方案，有效应对上述挑战：

-   **封装缓存逻辑**: 将缓存的存取、序列化、过期管理和数据源回源逻辑封装在库内部，减少业务代码中的样板代码。
-   **简化缓存失效**: 引入标签机制，使得相关缓存项可以被分组管理，并通过标签进行批量失效，大大简化了缓存失效策略的实现。
-   **解耦缓存后端**: 通过 `CacheDriver` 接口实现缓存后端与业务逻辑的解耦，允许开发者根据需求灵活选择和切换缓存技术。
-   **统一缓存接口**: 提供一套标准化的 API，确保整个应用程序的缓存操作一致性，便于管理和性能优化。

### 场景一：用户信息缓存（单条数据获取）

**问题**: 频繁从数据库获取用户信息，导致数据库压力大，响应慢。

**没有 CacheKV 时的实现（伪代码）**

```php
function getUserInfo($userId) {
    // 1. 手动构造缓存键
    $cacheKey = "user_" . $userId;
    
    // 2. 尝试从缓存获取
    $data = $cache->get($cacheKey);
    
    if ($data) {
        return $data; // 缓存命中
    }
    
    // 3. 缓存不存在则查询数据库
    $user = /* 数据库查询操作，例如：SELECT * FROM users WHERE id = $userId */;
    
    // 4. 设置缓存（需手动处理序列化、过期时间、异常等）
    $cache->set($cacheKey, $user, 3600); // 缓存 1 小时
    
    return $user;
}
// 痛点：代码冗余，缓存逻辑与业务逻辑混杂，手动管理过期和序列化，易引入缓存击穿问题。
```

**使用 CacheKV 后的实现**

```php
```php
function getUserInfo($userId) {
    // CacheKV 自动化处理缓存逻辑：先查缓存，无则执行回调函数从数据源获取并回填
    return $cache->get("user_" . $userId, function() use ($userId) {
        return /* 数据库查询操作，例如：SELECT * FROM users WHERE id = $userId */;
    }, 3600); // 统一设置过期时间
}
```
// 优势：代码简洁，缓存逻辑自动化，过期时间集中管理，有效防止缓存击穿。
```

### 场景二：商品批量查询（多条数据获取）

**问题**: 需要根据多个 ID 批量获取商品信息，传统方式容易导致 N+1 查询或复杂的缓存回填逻辑。

**没有 CacheKV 时的实现（伪代码）**

```php
function getProducts($productIds) {
    $result = [];
    
    // 1. 循环尝试从缓存获取每个商品
    foreach ($productIds as $id) {
        $cacheKey = "product_" . $id;
        if ($data = $cache->get($cacheKey)) {
            $result[$id] = $data; // 缓存命中
        } else {
            $missingIds[] = $id; // 记录未命中的 ID
        }
    }
    
    // 2. 批量查询缺失数据
    if (!empty($missingIds)) {
        $products = /* 数据库批量查询操作，例如：SELECT * FROM products WHERE id IN (...) */;
        
        // 3. 循环设置缓存并合并结果
        foreach ($products as $product) {
            $cache->set("product_" . $product['id'], $product, 3600);
            $result[$product['id']] = $product;
        }
    }
    
    return $result;
}
// 痛点：N+1 查询问题，手动处理缓存命中/未命中逻辑，代码复杂且易出错。
```

**使用 CacheKV 后的实现**

```php
```php
function getProducts($productIds) {
    // CacheKV 自动处理批量缓存逻辑：批量查询缓存，对未命中项批量回源并回填
    return $cache->getMultiple(array_map(fn($id) => "product_" . $id, $productIds), function($missingKeys) {
        $missingProductIds = array_map(fn($key) => (int) str_replace("product_", "", $key), $missingKeys);
        return /* 数据库批量查询操作，例如：SELECT * FROM products WHERE id IN (...) */;
    }, 3600);
}
```
// 优势：自动批量处理，统一缓存策略，有效避免 N+1 查询问题，代码简洁高效。
```

### 场景三：外部 API 响应缓存

**问题**: 频繁请求第三方 API，导致请求次数过多，响应时间长，且可能超出 API 调用限制。

**没有 CacheKV 时的实现（伪代码）**

```php
function getWeatherData($city) {
    $cacheKey = "weather_" . $city;
    $data = $cache->get($cacheKey);
    if ($data) {
        return $data;
    }
    
    $response = /* 调用第三方天气 API */;
    $weatherData = json_decode($response, true);
    
    $cache->set($cacheKey, $weatherData, 600); // 缓存 10 分钟
    return $weatherData;
}
// 痛点：手动处理 API 响应缓存，逻辑分散，难以统一管理。
```

**使用 CacheKV 后的实现**

```php
```php
function getWeatherData($city) {
    return $cache->get("weather_" . $city, function() use ($city) {
        $response = /* 调用第三方天气 API */;
        return json_decode($response, true);
    }, 600);
}
```
// 优势：将 API 调用逻辑封装在回调中，CacheKV 自动处理缓存，减少重复 API 请求。
```

### 场景四：处理空数据或不存在的数据（缓存穿透防护）

**问题**: 频繁查询不存在的数据（例如恶意请求），导致每次都穿透缓存直接访问数据源，增加数据源压力。

**没有 CacheKV 时的实现（伪代码）**

```php
function getNonExistentUser($userId) {
    $cacheKey = "user_" . $userId;
    $data = $cache->get($cacheKey);
    
    // 无法区分是缓存了 null 还是未缓存
    if ($data !== null) { 
        return $data;
    }
    
    $user = /* 数据库查询，可能返回 null */;
    
    // 如果 $user 为 null，不缓存，下次还会穿透
    if ($user !== null) {
        $cache->set($cacheKey, $user, 3600);
    }
    return $user;
}
// 痛点：无法有效缓存空结果，导致缓存穿透。
```

**使用 CacheKV 后的实现**

```php
function getNonExistentUser($userId) {
    // CacheKV 会缓存回调函数返回的任何值，包括 null，有效防止缓存穿透
    return $cache->get("user_" . $userId, function() use ($userId) {
        return /* 数据库查询，即使返回 null 也会被缓存 */;
    }, 3600); 
}
// 优势：自动缓存空结果，有效防止缓存穿透，减轻数据源压力。
```

### 场景五：基于标签的批量缓存失效

**问题**: 当某个分类下的数据发生变化时，需要手动清除所有相关缓存项，操作繁琐且容易遗漏。

**没有 CacheKV 时的实现（伪代码）**

```php
// 假设有多个商品缓存项：product_1, product_2, product_category_A_list, product_tag_new_list

function updateProductCategory($categoryId) {
    // 更新数据库中的商品分类
    /* ... */

    // 手动清除所有相关的缓存键，容易遗漏
    $cache->del("product_1");
    $cache->del("product_2");
    $cache->del("product_category_" . $categoryId . "_list");
    // ... 更多相关的键
}
// 痛点：缓存失效逻辑复杂，难以维护，容易导致数据不一致。
```

**使用 CacheKV 后的实现**

```php
// 存储时为相关缓存项打上标签
$cache->setWithTag('product:1', $product1Data, 'category:electronics', 3600);
$cache->setWithTag('product:2', $product2Data, 'category:electronics', 3600);
$cache->setWithTag('category:electronics:list', $categoryList, 'category:electronics', 3600);

function updateProductCategory($categoryId) {
    // 更新数据库中的商品分类
    /* ... */

    // 一键清除所有关联 'category:electronics' 标签的缓存项
    $cache->clearTag('category:' . $categoryId);
}
// 优势：通过标签系统，实现相关缓存项的批量、原子性失效，大大简化缓存管理。
```

### 场景六：热点数据自动续期（滑动过期）

**问题**: 某些数据被频繁访问，但其过期时间固定，导致热点数据频繁失效和重建，增加数据源压力。

**没有 CacheKV 时的实现（伪代码）**

```php
function getHotItem($itemId) {
    $cacheKey = "item_" . $itemId;
    $data = $cache->get($cacheKey);
    if ($data) {
        return $data;
    }
    
    $item = /* 从数据源获取热点数据 */;
    $cache->set($cacheKey, $item, 300); // 固定缓存 5 分钟
    return $item;
}
// 痛点：热点数据即使被频繁访问，也会在固定时间后失效，导致频繁回源。
```
**使用 CacheKV 后的实现**

```php
function getHotItem($itemId) {
    // CacheKV 在获取数据时会自动续期，确保热点数据始终保持缓存
    return $cache->get("item_" . $itemId, function() use ($itemId) {
        return /* 从数据源获取热点数据 */;
    }, 300); // 每次访问，缓存有效期自动延长至 5 分钟
}
// 优势：热点数据自动续期，减少回源，降低数据源压力，提升用户体验。
```

### 对比总结（核心差异）

| 功能点         | 传统实现               | CacheKV 方案           |
| :------------- | :--------------------- | :----------------------- |
| 缓存读取       | 需手动判断是否存在     | 自动处理回源逻辑         |
| 批量操作       | 循环单条执行           | 原生批量操作优化         |
| 缓存失效       | 需手动维护关联关系     | 标签系统自动管理         |
| 缓存后端       | 代码耦合               | 接口解耦，灵活切换       |
| 缓存穿透防护   | 需额外逻辑处理空结果   | 自动缓存空结果           |
| 热点数据管理   | 固定过期，频繁回源     | 基于访问频率自动续期     |
| 监控统计       | 难以实现               | 内置命中率统计           |

## 工作原理

`CacheKV` 的核心在于其对缓存操作流程的自动化和抽象。当应用程序请求数据时：

1.  **缓存查询**: `CacheKV` 首先尝试从配置的 `CacheDriver` 中获取数据。
2.  **缓存命中**: 如果数据存在且未过期（缓存命中），则直接返回缓存数据，并根据配置**自动延长其过期时间（滑动过期）**，避免了对原始数据源的访问。
3.  **缓存未命中**: 如果缓存中不存在所需数据或数据已过期，`CacheKV` 会调用开发者提供的回调函数（通常用于从数据库、API 等原始数据源获取数据）。
4.  **数据回填**: 从数据源获取到数据后，`CacheKV` 会自动将这些数据存储到缓存中，以便后续请求可以直接从缓存获取。
5.  **标签管理**: 当使用 `setWithTag` 存储数据时，`CacheKV` 会在缓存中记录该数据与指定标签的关联。当调用 `clearTag` 时，所有与该标签关联的缓存项将被批量清除。

## 安装

您可以通过 Composer 安装本扩展包：

```bash
composer require asfop/cache-kv
```

## 基础用法

首先，实例化 `CacheKV` 类，并为其提供一个 `CacheDriver` 实现：

```php
<?php

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKV;

// 1. 初始化 CacheKV 实例 (使用内存数组作为缓存后端)
$arrayDriver = new ArrayDriver();
$cache = new CacheKV($arrayDriver, 3600); // 默认缓存有效期 3600 秒

// 2. 存储单个缓存项
$cache->set('user:1', ['id' => 1, 'name' => 'Alice'], 60); // 缓存 60 秒

// 3. 获取单个缓存项
$user = $cache->get('user:1');
print_r($user);

// 4. 存储带标签的缓存项
$cache->setWithTag('user:123', ['id' => 123, 'name' => 'Bob'], 'users', 300); // 关联 'users' 标签
$cache->setWithTag('user:456', ['id' => 456, 'name' => 'Charlie'], ['users', 'vip'], 300); // 关联多个标签

// 5. 批量获取缓存项
$userIds = [1, 2, 3];
$users = $cache->getMultiple(
    array_map(fn($id) => 'user:' . $id, $userIds), // 待获取的缓存键数组
    function (array $missingKeys) {
        // 回调函数：当缓存未命中时，从数据源批量获取数据
        echo "从数据源获取缺失的键: " . implode(', ', $missingKeys) . "\n";
        $fetchedData = [];
        foreach ($missingKeys as $key) {
            $id = (int) str_replace('user:', '', $key);
            $fetchedData[$key] = ['id' => $id, 'name' => 'User ' . $id . ' (from DB)'];
        }
        return $fetchedData;
    },
    60 // 缓存有效期 60 秒
);
print_r($users);

// 6. 通过标签清除缓存
$cache->clearTag('users'); // 清除所有关联 'users' 标签的缓存项

// 7. 清除单个缓存项
$cache->forget('user:1');

// 8. 获取缓存统计信息
$stats = $cache->getStats();
print_r($stats);
```

## 高级用法

### 自定义缓存驱动

您可以实现 `Asfop\CacheKV\Cache\CacheDriver` 接口来创建自己的缓存驱动（例如，集成特定的 Redis 客户端或 Memcached），然后将其传递给 `CacheKV` 构造函数。这使得底层缓存技术栈的切换对业务代码透明。

```php
<?php

use Asfop\CacheKV\CacheKV;

// 示例：一个简化的 Redis 客户端
class MyRedisClient { /* ... */ }

class MyRedisCacheDriver implements CacheDriver
{
    private $redisClient;

    public function __construct(MyRedisClient $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    // 实现 CacheDriver 接口的所有方法：get, getMultiple, set, setMultiple, forget, has, tag, clearTag, getStats
    public function get(string $key) { /* ... */ }
    public function getMultiple(array $keys): array { /* ... */ }
    public function set(string $key, $value, int $ttl): bool { /* ... */ }
    public function setMultiple(array $values, int $ttl): bool { /* ... */ }
    public function forget(string $key): bool { /* ... */ }
    public function has(string $key): bool { /* ... */ }
    public function tag(string $key, array $tags): bool { /* ... */ }
    public function clearTag(string $tag): bool { /* ... */ }
    public function getStats(): array { /* ... */ }
}

// 实例化您的自定义驱动
$redisClient = new MyRedisClient();
$myCustomCacheDriver = new MyRedisCacheDriver($redisClient);

// 在实例化 CacheKV 时使用自定义驱动
$cache = new CacheKV($myCustomCacheDriver, 3600);
```

### 动态切换缓存驱动

在应用程序的入口文件或初始化阶段，您可以根据配置动态地选择并实例化不同的缓存驱动，然后将其传递给 `CacheKV`。这使得您可以在不修改业务逻辑的情况下，轻松切换缓存后端或使用不同的缓存实例。

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\CacheKV;

// --- 1. 定义你的缓存配置 ---
// 假设这是你的应用配置文件或环境变量
$appConfig = [
    'cache_driver' => 'redis_main', // 可以是 'array', 'redis_main', 'redis_secondary'
    'redis_main_host' => '127.0.0.1',
    'redis_main_port' => 6379,
    'redis_secondary_host' => '127.0.0.1',
    'redis_secondary_port' => 6380,
    // ... 其他配置
];

// --- 2. 注册自定义缓存驱动 ---
// 注册 'redis_main' 驱动
CacheManager::extend('redis_main', function () use ($appConfig) {
    $redis = new Redis(); // 假设 Redis 扩展已安装
    $redis->connect($appConfig['redis_main_host'], $appConfig['redis_main_port']);
    return new RedisDriver($redis);
});

// 注册 'redis_secondary' 驱动 (另一个 Redis 实例)
CacheManager::extend('redis_secondary', function () use ($appConfig) {
    $redis = new Redis();
    $redis->connect($appConfig['redis_secondary_host'], $appConfig['redis_secondary_port']);
    return new RedisDriver($redis);
});

// 注册 'array' 驱动 (如果需要，ArrayDriver 不需要额外配置)
CacheManager::extend('array', function () {
    return new ArrayDriver();
});

// --- 3. 根据配置选择并初始化 CacheKV ---
try {
    $selectedDriverName = $appConfig['cache_driver'];
    $cacheDriver = CacheManager::resolve($selectedDriverName);
    $CacheKV = new CacheKV($cacheDriver, 3600); // 默认 TTL 3600 秒

    echo "CacheKV 已使用驱动: " . $selectedDriverName . "\n";

    // --- 4. 使用 CacheKV ---
    $key = 'my_app_data';
    $value = ['message' => 'Hello from CacheKV!'];

    $CacheKV->set($key, $value);
    $retrieved = $CacheKV->get($key);

    echo "获取到的数据: " . json_encode($retrieved) . "\n";

} catch (InvalidArgumentException $e) {
    echo "错误: " . $e->getMessage() . "\n";
} catch (RedisException $e) { // 捕获 Redis 连接错误
    echo "Redis 连接错误: " . $e->getMessage() . "\n";
}

?>
```

### 动态切换缓存驱动

在应用程序的入口文件或初始化阶段，您可以根据配置动态地选择并实例化不同的缓存驱动，然后将其传递给 `CacheKV`。这使得您可以在不修改业务逻辑的情况下，轻松切换缓存后端或使用不同的缓存实例。

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\CacheKV;

// --- 1. 定义你的缓存配置 ---
// 假设这是你的应用配置文件或环境变量
$appConfig = [
    'cache_driver' => 'redis_main', // 可以是 'array', 'redis_main', 'redis_secondary'
    'redis_main_host' => '127.0.0.1',
    'redis_main_port' => 6379,
    'redis_secondary_host' => '127.0.0.1',
    'redis_secondary_port' => 6380,
    // ... 其他配置
];

// --- 2. 注册自定义缓存驱动 ---
// 注册 'redis_main' 驱动
CacheManager::extend('redis_main', function () use ($appConfig) {
    $redis = new Redis(); // 假设 Redis 扩展已安装
    $redis->connect($appConfig['redis_main_host'], $appConfig['redis_main_port']);
    return new RedisDriver($redis);
});

// 注册 'redis_secondary' 驱动 (另一个 Redis 实例)
CacheManager::extend('redis_secondary', function () use ($appConfig) {
    $redis = new Redis();
    $redis->connect($appConfig['redis_secondary_host'], $appConfig['redis_secondary_port']);
    return new RedisDriver($redis);
});

// 注册 'array' 驱动 (如果需要，ArrayDriver 不需要额外配置)
CacheManager::extend('array', function () {
    return new ArrayDriver();
});

// --- 3. 根据配置选择并初始化 CacheKV ---
try {
    $selectedDriverName = $appConfig['cache_driver'];
    $cacheDriver = CacheManager::resolve($selectedDriverName);
    $CacheKV = new CacheKV($cacheDriver, 3600); // 默认 TTL 3600 秒

    echo "CacheKV 已使用驱动: " . $selectedDriverName . "\n";

    // --- 4. 使用 CacheKV ---
    $key = 'my_app_data';
    $value = ['message' => 'Hello from CacheKV!'];

    $CacheKV->set($key, $value);
    $retrieved = $CacheKV->get($key);

    echo "获取到的数据: " . json_encode($retrieved) . "\n";

} catch (InvalidArgumentException $e) {
    echo "错误: " . $e->getMessage() . "\n";
} catch (RedisException $e) { // 捕获 Redis 连接错误
    echo "Redis 连接错误: " . $e->getMessage() . "\n";
}

?>
```

### 使用静态门面

为了更便捷地使用 `CacheKV`，您可以选择使用静态门面。这允许您通过静态方法直接调用 `CacheKV` 的功能，而无需在每个需要缓存的地方都注入 `CacheKV` 实例。

**初始化门面**

在应用程序的入口文件或服务提供者中，您需要将 `CacheKV` 实例绑定到门面：

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFacade;

// 假设你已经根据配置初始化了 $CacheKV 实例
$arrayDriver = new ArrayDriver();
$CacheKVInstance = new CacheKV($arrayDriver, 3600);

// 将 CacheKV 实例设置到门面中
CacheKVFacade::setInstance($CacheKVInstance);

// 现在你可以在应用的任何地方通过静态方法使用 CacheKV
$key = 'facade_data';
$value = ['message' => 'Hello from Facade!'];

CacheKVFacade::set($key, $value);
$retrieved = CacheKVFacade::get($key);

echo "通过门面获取到的数据: " . json_encode($retrieved) . "\n";

?>
```

**使用门面**

一旦门面被初始化，您就可以在代码的任何地方直接使用静态方法：

```php
<?php

use Asfop\CacheKV\CacheKVFacade;

// 获取数据
$user = CacheKVFacade::get('user:1', function() {
    // 从数据源获取
    return ['id' => 1, 'name' => 'Alice'];
});

// 存储数据
CacheKVFacade::set('product:101', ['name' => 'New Product'], 3600);

// 批量获取
$products = CacheKVFacade::getMultiple(['product:1', 'product:2'], function($missingKeys) {
    // 批量从数据源获取
    return [];
});

// 清除标签
CacheKVFacade::clearTag('users');

// 获取统计
$stats = CacheKVFacade::getStats();

?>
```

### 多级缓存

多级缓存允许您将多个缓存驱动组合起来，形成一个缓存层级。例如，您可以将一个快速的内存缓存（`ArrayDriver`）作为一级缓存，一个持久化的 Redis 缓存（`RedisDriver`）作为二级缓存。当请求数据时，系统会首先尝试从一级缓存获取，如果未命中，则会尝试从二级缓存获取，并回填到一级缓存。

**初始化多级缓存驱动**

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\Cache\Drivers\MultiLevelCacheDriver;
use Asfop\CacheKV\CacheKV;

// 1. 初始化各个缓存驱动实例
$arrayDriver = new ArrayDriver(); // 一级缓存：内存

$redis = new Redis(); // 假设 Redis 扩展已安装
$redis->connect('127.0.0.1', 6379);
$redisDriver = new RedisDriver($redis); // 二级缓存：Redis

// 2. 创建 MultiLevelCacheDriver，按优先级从高到低传入驱动
$multiLevelDriver = new MultiLevelCacheDriver([
    $arrayDriver, // 优先级最高
    $redisDriver  // 优先级次之
]);

// 3. 使用 MultiLevelCacheDriver 初始化 CacheKV
$CacheKV = new CacheKV($multiLevelDriver, 3600); // 默认 TTL 3600 秒

// --- 4. 使用 CacheKV ---
$key = 'multi_level_data';
$value = ['message' => 'Hello from Multi-Level Cache!'];

// 第一次设置，会同时写入 ArrayDriver 和 RedisDriver
$CacheKV->set($key, $value);

// 第一次获取，会从 ArrayDriver 获取（命中一级缓存）
$retrieved = $CacheKV->get($key);
echo "获取到的数据 (一级缓存): " . json_encode($retrieved) . "\n";

// 清除一级缓存，模拟一级缓存失效
$arrayDriver->forget($key);

// 第二次获取，会从 RedisDriver 获取（命中二级缓存），并回填到 ArrayDriver
$retrieved = $CacheKV->get($key);
echo "获取到的数据 (二级缓存并回填): " . json_encode($retrieved) . "\n";

// 此时 ArrayDriver 应该又有了这个数据
if ($arrayDriver->has($key)) {
    echo "数据已回填到一级缓存。\n";
}

?>
```

### 动态切换缓存驱动

在应用程序的入口文件或初始化阶段，您可以根据配置动态地选择并实例化不同的缓存驱动，然后将其传递给 `CacheKV`。这使得您可以在不修改业务逻辑的情况下，轻松切换缓存后端或使用不同的缓存实例。

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\CacheKV;

// --- 1. 定义你的缓存配置 ---
// 假设这是你的应用配置文件或环境变量
$appConfig = [
    'cache_driver' => 'redis_main', // 可以是 'array', 'redis_main', 'redis_secondary'
    'redis_main_host' => '127.0.0.1',
    'redis_main_port' => 6379,
    'redis_secondary_host' => '127.0.0.1',
    'redis_secondary_port' => 6380,
    // ... 其他配置
];

// --- 2. 注册自定义缓存驱动 ---
// 注册 'redis_main' 驱动
CacheManager::extend('redis_main', function () use ($appConfig) {
    $redis = new Redis(); // 假设 Redis 扩展已安装
    $redis->connect($appConfig['redis_main_host'], $appConfig['redis_main_port']);
    return new RedisDriver($redis);
});

// 注册 'redis_secondary' 驱动 (另一个 Redis 实例)
CacheManager::extend('redis_secondary', function () use ($appConfig) {
    $redis = new Redis();
    $redis->connect($appConfig['redis_secondary_host'], $appConfig['redis_secondary_port']);
    return new RedisDriver($redis);
});

// 注册 'array' 驱动 (如果需要，ArrayDriver 不需要额外配置)
CacheManager::extend('array', function () {
    return new ArrayDriver();
});

// --- 3. 根据配置选择并初始化 CacheKV ---
try {
    $selectedDriverName = $appConfig['cache_driver'];
    $cacheDriver = CacheManager::resolve($selectedDriverName);
    $CacheKV = new CacheKV($cacheDriver, 3600); // 默认 TTL 3600 秒

    echo "CacheKV 已使用驱动: " . $selectedDriverName . "\n";

    // --- 4. 使用 CacheKV ---
    $key = 'my_app_data';
    $value = ['message' => 'Hello from CacheKV!'];

    $CacheKV->set($key, $value);
    $retrieved = $CacheKV->get($key);

    echo "获取到的数据: " . json_encode($retrieved) . "\n";

} catch (InvalidArgumentException $e) {
    echo "错误: " . $e->getMessage() . "\n";
} catch (RedisException $e) { // 捕获 Redis 连接错误
    echo "Redis 连接错误: " . $e->getMessage() . "\n";
}

?>
```

### 使用静态门面

为了更便捷地使用 `CacheKV`，您可以选择使用静态门面。这允许您通过静态方法直接调用 `CacheKV` 的功能，而无需在每个需要缓存的地方都注入 `CacheKV` 实例。

**初始化门面**

在应用程序的入口文件或服务提供者中，您需要将 `CacheKV` 实例绑定到门面：

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFacade;

// 假设你已经根据配置初始化了 $CacheKV 实例
$arrayDriver = new ArrayDriver();
$CacheKVInstance = new CacheKV($arrayDriver, 3600);

// 将 CacheKV 实例设置到门面中
CacheKVFacade::setInstance($CacheKVInstance);

// 现在你可以在应用的任何地方通过静态方法使用 CacheKV
$key = 'facade_data';
$value = ['message' => 'Hello from Facade!'];

CacheKVFacade::set($key, $value);
$retrieved = CacheKVFacade::get($key);

echo "通过门面获取到的数据: " . json_encode($retrieved) . "\n";

?>
```

**使用门面**

一旦门面被初始化，您就可以在代码的任何地方直接使用静态方法：

```php
<?php

use Asfop\CacheKV\CacheKVFacade;

// 获取数据
$user = CacheKVFacade::get('user:1', function() {
    // 从数据源获取
    return ['id' => 1, 'name' => 'Alice'];
});

// 存储数据
CacheKVFacade::set('product:101', ['name' => 'New Product'], 3600);

// 批量获取
$products = CacheKVFacade::getMultiple(['product:1', 'product:2'], function($missingKeys) {
    // 批量从数据源获取
    return [];
});

// 清除标签
CacheKVFacade::clearTag('users');

// 获取统计
$stats = CacheKVFacade::getStats();

?>
```

### 多级缓存

多级缓存允许您将多个缓存驱动组合起来，形成一个缓存层级。例如，您可以将一个快速的内存缓存（`ArrayDriver`）作为一级缓存，一个持久化的 Redis 缓存（`RedisDriver`）作为二级缓存。当请求数据时，系统会首先尝试从一级缓存获取，如果未命中，则会尝试从二级缓存获取，并回填到一级缓存。

**初始化多级缓存驱动**

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\Cache\Drivers\MultiLevelCacheDriver;
use Asfop\CacheKV\CacheKV;

// 1. 初始化各个缓存驱动实例
$arrayDriver = new ArrayDriver(); // 一级缓存：内存

$redis = new Redis(); // 假设 Redis 扩展已安装
$redis->connect('127.0.0.1', 6379);
$redisDriver = new RedisDriver($redis); // 二级缓存：Redis

// 2. 创建 MultiLevelCacheDriver，按优先级从高到低传入驱动
$multiLevelDriver = new MultiLevelCacheDriver([
    $arrayDriver, // 优先级最高
    $redisDriver  // 优先级次之
]);

// 3. 使用 MultiLevelCacheDriver 初始化 CacheKV
$CacheKV = new CacheKV($multiLevelDriver, 3600); // 默认 TTL 3600 秒

// --- 4. 使用 CacheKV ---
$key = 'multi_level_data';
$value = ['message' => 'Hello from Multi-Level Cache!'];

// 第一次设置，会同时写入 ArrayDriver 和 RedisDriver
$CacheKV->set($key, $value);

// 第一次获取，会从 ArrayDriver 获取（命中一级缓存）
$retrieved = $CacheKV->get($key);
echo "获取到的数据 (一级缓存): " . json_encode($retrieved) . "\n";

// 清除一级缓存，模拟一级缓存失效
$arrayDriver->forget($key);

// 第二次获取，会从 RedisDriver 获取（命中二级缓存），并回填到 ArrayDriver
$retrieved = $CacheKV->get($key);
echo "获取到的数据 (二级缓存并回填): " . json_encode($retrieved) . "\n";

// 此时 ArrayDriver 应该又有了这个数据
if ($arrayDriver->has($key)) {
    echo "数据已回填到一级缓存。\n";
}

?>

