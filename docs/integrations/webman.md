# 在 Webman 中使用 CacheKV

CacheKV 可以很好地集成到 Webman 框架中，利用 Webman 的启动文件和配置系统。

## 1. 安装 CacheKV

首先，通过 Composer 安装 CacheKV：

```bash
composer require asfop/cache-kv
```

## 2. 发布配置文件 (可选)

如果您想修改 CacheKV 的默认配置（例如，更改默认驱动或 TTL），您可以手动复制 `vendor/asfop/cache-kv/src/Config/cachekv.php` 到 Webman 的 `config` 目录。

```bash
cp vendor/asfop/cache-kv/src/Config/cachekv.php config/cachekv.php
```

然后，您可以编辑 `config/cachekv.php` 文件来调整设置。

## 3. 配置 Redis 实例和注册服务提供者

在 Webman 中，您通常会在 `config/bootstrap.php` 文件中进行应用程序的初始化和依赖注入。这是配置 CacheKV 的 Redis 实例和注册服务提供者的理想位置。

打开 `config/bootstrap.php` 文件，并添加以下代码：

```php
<?php

use Webman\Bootstrap;
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVServiceProvider;

Bootstrap::exec(function () {
    // 1. 配置 CacheKV 的 Redis 实例获取方式
    // 这将使用您在 Webman 配置中定义的 Redis 连接。
    CacheKV::setRedisFactory(function () {
        // 假设您在 config/redis.php 中配置了 Redis 连接
        // Webman 通常通过 illuminate/redis 或直接使用 Redis 扩展
        // 这里我们直接使用 PHP 的 Redis 扩展，并从 Webman 配置中获取连接信息
        $redisConfig = config('redis'); // 获取 Webman 的 Redis 配置
        
        $redis = new \Redis();
        $redis->connect($redisConfig['default']['host'] ?? '127.0.0.1', $redisConfig['default']['port'] ?? 6379);
        if (isset($redisConfig['default']['password']) && $redisConfig['default']['password']) {
            $redis->auth($redisConfig['default']['password']);
        }
        if (isset($redisConfig['default']['database'])) {
            $redis->select($redisConfig['default']['database']);
        }
        return $redis;
    });

    // 2. 注册 CacheKV 服务提供者
    // 您可以选择性地传递一个数组来覆盖默认配置。
    CacheKVServiceProvider::register();

    // 现在 CacheKV 已准备好在您的整个应用程序中使用。
});
```

**注意**：请根据您实际的 Webman Redis 配置路径调整 `config('redis')`。通常 Webman 的 Redis 配置在 `config/redis.php` 中，并且可能包含多个连接。

## 4. 使用 CacheKV

一旦配置完成，您就可以在控制器、服务或任何业务逻辑中使用 `CacheManager` 来获取缓存实例：

```php
<?php

namespace app\controller;

use support\Request;
use Asfop\CacheKV\Cache\CacheManager;

class Index
{
    public function index(Request $request)
    {
        $cache = CacheManager::resolve('redis'); // 获取 Redis 缓存实例

        $data = $cache->get('webman_data', function () {
            // 从数据库或其他源获取数据
            return 'Hello from CacheKV in Webman!';
        }, 60); // 缓存 60 秒

        return response('Cached Data: ' . $data);
    }

    public function clearCache(Request $request)
    {
        $cache = CacheManager::resolve('redis');
        $cache->forget('webman_data');
        return response('Cache for webman_data cleared.');
    }
}
```

通过这些步骤，CacheKV 将无缝集成到您的 Webman 应用程序中，为您提供强大的缓存功能。