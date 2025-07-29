# 在 ThinkPHP 中使用 CacheKV

CacheKV 可以很好地集成到 ThinkPHP 6.0+ 应用程序中。以下是集成步骤。

## 1. 安装 CacheKV

首先，通过 Composer 安装 CacheKV：

```bash
composer require asfop/cache-kv
```

## 2. 发布配置文件 (可选)

如果您想修改 CacheKV 的默认配置（例如，更改默认驱动或 TTL），您可以手动复制 `vendor/asfop/cache-kv/src/Config/cachekv.php` 到 ThinkPHP 的 `config` 目录。

```bash
cp vendor/asfop/cache-kv/src/Config/cachekv.php config/cachekv.php
```

然后，您可以编辑 `config/cachekv.php` 文件来调整设置。

## 3. 注册服务提供者

在 ThinkPHP 中，您通常会在 `app/provider.php` 文件中注册服务。打开 `app/provider.php` 并添加 CacheKV 的服务提供者：

```php
// app/provider.php

return [
    // ... 其他服务
    Asfop\CacheKV\CacheKVServiceProvider::class,
];
```

## 4. 配置 Redis 实例

CacheKV 允许您通过闭包定义 Redis 实例的获取方式。在 ThinkPHP 中，您可以在 `app/event.php` 中监听 `AppInit` 事件，或者在自定义的服务类中进行此配置。推荐在 `app/event.php` 中进行，以确保在应用程序初始化时设置好。

在 `app/event.php` 文件中添加以下监听器：

```php
// app/event.php

return [
    'bind'      => [

    ],
    'listen'    => [
        'AppInit'  => [
            function () {
                \Asfop\CacheKV\CacheKV::setRedisFactory(function () {
                    // 使用 ThinkPHP 的 Redis 配置来获取 Redis 实例
                    // 这将使用您在 config/cache.php 或 config/redis.php 中配置的 Redis 连接
                    // 假设您在 config/cache.php 中配置了名为 'redis' 的连接
                    $options = config('cache.stores.redis'); // 根据您的实际配置路径调整
                    
                    $redis = new \Redis();
                    $redis->connect($options['host'] ?? '127.0.0.1', $options['port'] ?? 6379);
                    if (isset($options['password']) && $options['password']) {
                        $redis->auth($options['password']);
                    }
                    if (isset($options['select'])) {
                        $redis->select($options['select']);
                    }
                    return $redis;
                });
            },
        ],
        // ... 其他事件监听
    ],
    'subscribe' => [

    ],
];
```

**注意**：请根据您实际的 ThinkPHP Redis 配置路径调整 `config('cache.stores.redis')`。通常 Redis 配置在 `config/cache.php` 或 `config/redis.php` 中。

## 5. 使用 CacheKV

一旦配置完成，您就可以在应用程序的任何地方使用 `CacheManager` 来获取缓存实例：

```php
<?php

namespace app\controller;

use app\BaseController;
use Asfop\CacheKV\Cache\CacheManager;

class Index extends BaseController
{
    public function index()
    {
        $cache = CacheManager::resolve('redis'); // 获取 Redis 缓存实例

        $data = $cache->get('my_data', function () {
            // 从数据库或其他源获取数据
            return 'Hello from CacheKV in ThinkPHP!';
        }, 60); // 缓存 60 秒

        return 'Cached Data: ' . $data;
    }

    public function clearCache()
    {
        $cache = CacheManager::resolve('redis');
        $cache->forget('my_data');
        return 'Cache for my_data cleared.';
    }
}
```

通过这些步骤，CacheKV 将无缝集成到您的 ThinkPHP 应用程序中，为您提供强大的缓存功能。