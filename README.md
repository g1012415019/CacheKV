# Query Cache

[![Latest Version on Packagist](https://img.shields.io/packagist/v/asfop/query-cache.svg?style=flat-square)](https://packagist.org/packages/asfop/query-cache)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHP Version Require](https://img.shields.io/packagist/php/asfop/query-cache?style=flat-square)](https://packagist.org/packages/asfop/query-cache)

**Query Cache** 是一个灵活的、与 ORM 无关的数据库查询缓存层。它旨在帮助您高效地缓存数据库查询结果，支持单个记录和批量记录的获取，从而显著提升应用程序的性能。

本库的核心理念是提供一个通用的缓存机制，让您能够手动控制数据查询和缓存的逻辑，而不依赖于任何特定的 ORM（如 Eloquent、Doctrine 等）。

## 核心特性

-   **ORM 无关**: 不依赖任何特定的 ORM，您可以将其集成到任何 PHP 项目中。
-   **灵活的数据库适配器**: 通过 `DatabaseAdapter` 接口，您可以轻松接入任何数据库连接（如 PDO、MySQLi 等）。
-   **可插拔的缓存驱动**: 通过 `CacheDriver` 接口，您可以自由选择和扩展缓存后端（如内存、Redis、Memcached 等）。
-   **高效的批量查询**: `getByIds` 方法支持批量获取数据，有效解决 N+1 查询问题。
-   **自动缓存与失效**: 查询结果自动缓存，并提供手动清除指定缓存的方法。
-   **可定制的缓存键**: 通过 `KeyGenerator` 接口，您可以自定义缓存键的生成策略。
-   **兼容性强**: 支持 PHP 7.0 及以上版本。

## 解决的问题

在没有 `QueryCache` 包的情况下，当您需要对基于 ID 列表的数据库查询（例如 `SELECT * FROM users WHERE id IN (1, 5, 10)`）进行缓存时，通常会面临以下挑战：

-   **重复的手动缓存逻辑**: 每次需要缓存一个查询时，您都必须手动编写大量重复的代码，包括生成缓存键、检查缓存、从数据库获取数据（如果缓存未命中）、以及将结果存入缓存。这导致代码冗余、难以维护且容易出错。
-   **`IN` 查询的复杂缓存键管理**: 对于 `IN` 查询，缓存键必须唯一地表示特定的 ID 集合。手动管理这些键（例如，确保 `users:1,5,10` 和 `users:10,5,1` 映射到同一个键）对于大型或动态变化的 ID 列表来说，既困难又低效。
-   **多表关联查询的缓存复杂性**: 缓存涉及多个表（例如用户及其个人资料）的复杂数据结构，并确保当任何关联数据发生变化时缓存能够正确失效，是一个巨大的挑战。
-   **缓存失效的挑战**: 手动实现健壮的缓存失效策略（例如，当底层数据更新时清除缓存）非常容易出错，并且可能导致应用程序提供过期数据。

## 解决方案

`QueryCache` 包通过提供一个统一、自动化且可扩展的解决方案，解决了上述基于 ID 列表的数据库查询缓存的挑战。它将复杂的缓存管理逻辑抽象化，让开发者能够专注于业务逻辑。

解决方案的核心特点包括：

-   **自动化缓存工作流**: `QueryCache::getByIds()` 方法封装了完整的缓存处理流程：自动生成缓存键、检查缓存、在缓存未命中时查询数据库，并将结果存储到缓存中。
-   **标准化键生成**: `KeyGenerator` 确保为任何给定的查询参数生成一致且唯一的缓存键，极大地简化了 `IN` 查询键的管理。
-   **解耦的架构**: 通过 `DatabaseAdapter` 和 `CacheDriver` 接口，本包独立于特定的 ORM 或缓存后端，提供了极高的灵活性和易用性。
-   **支持关联数据**: `getByIds` 方法的 `relations` 参数允许缓存包含多个相关实体（可能来自不同表）的复杂数据结构。

## 优点

使用 `QueryCache` 包将带来以下显著优势：

-   **减少样板代码**: 大幅减少了手动编写的缓存逻辑，使您的代码库更简洁、更易于维护。
-   **提升应用性能**: 通过从缓存中提供数据，显著减少了数据库查询次数，从而加快了响应速度并降低了数据库负载。
-   **简化缓存管理**: 自动化了缓存键的生成和数据的存取，将开发者从繁琐的缓存操作中解放出来。
-   **高度灵活和可扩展**: 其解耦的设计允许轻松替换数据库适配器和缓存驱动，以适应不同的项目需求和技术栈。
-   **缓解 N+1 查询问题**: `getByIds` 方法天然支持批量获取数据，有助于解决基于 ID 查找的 N+1 查询问题。
-   **ORM 无关性**: 可以无缝集成到任何 PHP 项目中，无论您使用何种 ORM 或数据库抽象层。

## 缺点/局限性

尽管 `QueryCache` 提供了强大的功能，但当前版本也存在一些局限性或需要注意的地方：

-   **手动缓存失效**: 尽管提供了 `forgetCache` 方法，但对于数据变更引起的自动、健壮的缓存失效（例如，基于标签或事件驱动的失效）并未内置，需要开发者手动实现或结合外部机制。
-   **查询类型限制**: 主要设计用于基于 ID 的 `SELECT` 查询。它不直接支持缓存复杂的 `WHERE` 子句（除了 `IN`）、聚合查询或写操作。
-   **初始配置开销**: 对于非常简单的缓存需求，初始配置 `DatabaseAdapter`、`CacheDriver` 和 `attributeConfig` 可能会带来一定的开销。
-   **潜在的数据不一致**: 如果没有配合完善的缓存失效策略，当底层数据库记录发生变化而缓存未及时更新时，存在提供过期数据的风险。
-   **无内置分布式锁**: 在高并发场景下，为了防止缓存击穿（即多个请求同时重建同一个缓存条目），可能需要额外实现分布式锁机制。


## 安装

您可以通过 Composer 安装本扩展包：

```bash
composer require asfop/query-cache
```

## 工作原理

`QueryCache` 核心类协调 `DatabaseAdapter`（负责数据库查询）、`CacheDriver`（负责缓存存储）和 `KeyGenerator`（负责缓存键生成）。当您请求数据时，它会首先尝试从缓存中获取。如果缓存未命中，则通过 `DatabaseAdapter` 从数据库中获取数据，然后将数据存入缓存，并返回给调用方。

## 基础用法

首先，您需要实例化 `QueryCache` 类，并为其提供 `DatabaseAdapter` 和 `CacheDriver` 的实现。

**1. 准备数据库适配器 (以 PDO 为例):**

```php
<?php

use Asfop\QueryCache\Database\Drivers\PdoAdapter;

// 假设您已经有一个 PDO 实例
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 创建测试表
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
$pdo->exec("CREATE TABLE user_info (user_id INTEGER PRIMARY KEY, email TEXT, bio TEXT)");

// 插入一些数据
$pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");
$pdo->exec("INSERT INTO users (id, name) VALUES (2, 'Bob')");
$pdo->exec("INSERT INTO user_info (user_id, email, bio) VALUES (1, 'alice@example.com', 'Software Engineer')");
$pdo->exec("INSERT INTO user_info (user_id, email, bio) VALUES (2, 'bob@example.com', 'Project Manager')");

$dbAdapter = new PdoAdapter($pdo);
```

**2. 准备缓存驱动 (以 ArrayDriver 为例):**

```php
<?php

use Asfop\QueryCache\Cache\Drivers\ArrayDriver;

$cacheDriver = new ArrayDriver();
```

**3. 实例化 `QueryCache`:**

```php
<?php

use Asfop\QueryCache\QueryCache;

$queryCache = new QueryCache($dbAdapter, $cacheDriver);
```

**4. 定义属性配置:**

您需要为每个要查询的“属性”或“关联”定义其对应的数据库表、ID 列等信息。

```php
<?php

$attributeConfig = [
    'user' => [ // 主实体 'user' 的配置
        'table' => 'users',
        'id_column' => 'id',
        'columns' => ['id', 'name'],
        'ttl' => 3600, // 默认缓存时间
    ],
    'info' => [ // 'info' 属性的配置
        'table' => 'user_info',
        'id_column' => 'user_id', // 'user_info' 表通过 'user_id' 关联
        'columns' => ['user_id', 'email', 'bio'],
        'ttl' => 3600,
    ],
    // 您可以添加更多属性，例如 'phone', 'address' 等
    // 'phone' => [
    //     'table' => 'user_phones',
    //     'id_column' => 'user_id',
    //     'columns' => ['phone_number'],
    //     'ttl' => 3600,
    // ],
];
```

### 获取单个记录 (`getById`)

```php
// 获取 ID 为 1 的用户及其 info
$userId = 1;
$data = $queryCache->getById(
    'user', // 实体名称
    'users', // 主表名
    $userId,
    ['user', 'info'], // 要获取的属性
    $attributeConfig // 属性配置
);

print_r($data);
/*
Array
(
    [user] => Array
        (
            [id] => 1
            [name] => Alice
        )
    [info] => Array
        (
            [user_id] => 1
            [email] => alice@example.com
            [bio] => Software Engineer
        )
)
*/

// 第二次获取，将从缓存中读取
$dataFromCache = $queryCache->getById('user', 'users', $userId, ['user', 'info'], $attributeConfig);
// ...
```

### 获取多个记录 (`getByIds`)

```php
// 获取 ID 为 1 和 2 的用户及其 info
$userIds = [1, 2];
$batchData = $queryCache->getByIds(
    'user', // 实体名称
    'users', // 主表名
    $userIds,
    ['user', 'info'], // 要获取的属性
    $attributeConfig // 属性配置
);

print_r($batchData);
/*
Array
(
    [1] => Array
        (
            [user] => Array
                (
                    [id] => 1
                    [name] => Alice
                )
            [info] => Array
                (
                    [user_id] => 1
                    [email] => alice@example.com
                    [bio] => Software Engineer
                )
        )
    [2] => Array
        (
            [user] => Array
                (
                    [id] => 2
                    [name] => Bob
                )
            [info] => Array
                (
                    [user_id] => 2
                    [email] => bob@example.com
                    [bio] => Project Manager
                )
        )
)
*/

// 第二次获取，将从缓存中读取
$batchDataFromCache = $queryCache->getByIds('user', 'users', $userIds, ['user', 'info'], $attributeConfig);
// ...
```

### 清除缓存 (`forgetCache`)

```php
// 清除 ID 为 1 的用户的 'info' 属性缓存
$queryCache->forgetCache('user', 1, 'info');

// 清除 ID 为 2 的用户的 'user' 属性缓存
$queryCache->forgetCache('user', 2, 'user');
```

## 高级用法

### 自定义缓存驱动

您可以实现 `Asfop\QueryCache\Cache\CacheDriver` 接口来创建自己的缓存驱动（例如 Redis、Memcached），然后通过 `CacheManager` 注册并使用它。

```php
<?php

use Asfop\QueryCache\Cache\CacheManager;
use Asfop\QueryCache\Cache\CacheDriver;

// 假设您有一个 Redis 客户端实例
class MyRedisClient { /* ... */ }

class MyRedisCacheDriver implements CacheDriver
{
    private $redisClient;

    public function __construct(MyRedisClient $redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function get(string $key) { /* ... */ }
    public function set(string $key, $value, int $ttl): bool { /* ... */ }
    public function forget(string $key): bool { /* ... */ }
    public function has(string $key): bool { /* ... */ }
}

// 在您项目的初始化脚本中注册
CacheManager::extend('redis', function () {
    $redisClient = new MyRedisClient(); // 实例化您的 Redis 客户端
    return new MyRedisCacheDriver($redisClient);
});

// 然后在实例化 QueryCache 时使用
$queryCache = new QueryCache($dbAdapter, CacheManager::resolve('redis'));
```

### 自定义数据库适配器

`Query Cache` 库不包含任何具体的数据库连接实现。您需要实现 `Asfop\QueryCache\Database\DatabaseAdapter` 接口来创建自己的数据库适配器，以支持您项目所使用的数据库连接或 ORM。

以下是一个基于 PDO 的示例实现，您可以根据自己的需求进行调整：

```php
<?php

namespace MyProject\Database;

use Asfop\QueryCache\Database\DatabaseAdapter;
use PDO;

class MyPdoAdapter implements DatabaseAdapter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(string $table, string $idColumn, $id, array $columns = ['*']): ?array
    {
        $cols = implode(',', $columns);
        $stmt = $this->pdo->prepare("SELECT {$cols} FROM `{$table}` WHERE `{$idColumn}` = :id LIMIT 1");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findMany(string $table, string $idColumn, array $ids, array $columns = ['*']): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $cols = implode(',', $columns);

        $stmt = $this->pdo->prepare("SELECT {$cols} FROM `{$table}` WHERE `{$idColumn}` IN ({$placeholders})");
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id);
        }
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $indexedResults = [];
        foreach ($results as $row) {
            $indexedResults[$row[$idColumn]] = $row;
        }

        return $indexedResults;
    }
}

// 然后在实例化 QueryCache 时使用
$myCustomDbAdapter = new MyPdoAdapter($pdo); // 传入您的 PDO 实例
$queryCache = new QueryCache($myCustomDbAdapter, $cacheDriver);
```

### 自定义缓存键生成器

您可以实现 `Asfop\QueryCache\Key\KeyGenerator` 接口来创建自己的缓存键生成器，并在实例化 `QueryCache` 时传入。

```php
<?php

use Asfop\QueryCache\Key\KeyGenerator;

class MyCustomKeyGenerator implements KeyGenerator
{
    public function generate(string $entityName, $identifier, string $relationName): string { /* ... */ }
}

$myKeyGenerator = new MyCustomKeyGenerator();
$queryCache = new QueryCache($dbAdapter, $cacheDriver, $myKeyGenerator);
```

## 测试

本包提供了一套完整的测试。要运行测试，请在项目根目录下执行以下命令：

```bash
./vendor/bin/phpunit
```

要生成代码覆盖率报告：

```bash
./vendor/bin/phpunit --coverage-html build/coverage
```

## 贡献

欢迎各种形式的贡献！如果您有任何 Bug 反馈或功能请求，请随时提交 Pull Request 或创建 Issue。

## 许可证

Query Cache 是一个遵循 [MIT 许可](LICENSE) 的开源软件。
