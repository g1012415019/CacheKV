# 在 Laravel 中使用 CacheKV

CacheKV 可以轻松集成到 Laravel 应用程序中，利用 Laravel 的服务提供者和配置系统。

## 1. 安装 CacheKV

首先，通过 Composer 安装 CacheKV：

```bash
composer require asfop/cache-kv
```

## 2. 发布配置文件 (可选)

如果您想修改 CacheKV 的默认配置（例如，更改默认驱动或 TTL），您可以发布其配置文件。CacheKV 库本身没有提供 Artisan 命令来发布配置，但您可以手动复制 `src/Config/cachekv.php` 到 Laravel 的 `config` 目录。

将 `vendor/asfop/cache-kv/src/Config/cachekv.php` 复制到 `config/cachekv.php`：

```bash
cp vendor/asfop/cache-kv/src/Config/cachekv.php config/cachekv.php
```

然后，您可以编辑 `config/cachekv.php` 文件来调整设置。

## 3. 注册服务提供者

CacheKV 附带一个服务提供者，您需要在 Laravel 应用程序中注册它。在 `config/app.php` 文件的 `providers` 数组中添加以下内容：

```php
// config/app.php

return [
    // ...
    'providers' => [
        // ...
        Asfop\CacheKV\CacheKVServiceProvider::class,
    ],
    // ...
];
```

## 4. 配置 Redis 实例

由于 CacheKV 允许您通过闭包定义 Redis 实例的获取方式，您可以在 Laravel 的 `AppServiceProvider` 或您自己的自定义服务提供者中进行此配置。这确保了在 CacheKV 尝试连接 Redis 之前，您的 Redis 客户端已正确设置。

在 `app/Providers/AppServiceProvider.php` 的 `register` 方法中添加以下代码：

```php
// app/Providers/AppServiceProvider.php

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Asfop\CacheKV\CacheKV;
use Redis; // 确保引入了 Redis 门面或 Redis 扩展的类

class AppServiceProvider extends ServiceProvider
{
    /**
     * 注册任何应用程序服务。
     */
    public function register(): void
    {
        // 配置 CacheKV 的 Redis 实例获取方式
        CacheKV::setRedisFactory(function () {
            // 使用 Laravel 的 Redis 门面来获取 Redis 实例
            // 这将使用您在 config/database.php 中配置的 Redis 连接
            return Redis::connection(); 
            
            // 或者，如果您想直接使用 PHP Redis 扩展：
            // $redis = new \Redis();
            // $redis->connect(env('REDIS_HOST', '127.0.0.1'), env('REDIS_PORT', 6379));
            // $redis->auth(env('REDIS_PASSWORD'));
            // $redis->select(env('REDIS_DB', 0));
            // return $redis;
        });
    }

    /**
     * 引导任何应用程序服务。
     */
    public function boot(): void
    {
        //
    }
}
```

**注意**：在 `AppServiceProvider` 中使用 `Redis::connection()` 是推荐的方式，因为它利用了 Laravel 强大的数据库和 Redis 配置管理。确保您的 `.env` 文件和 `config/database.php` 中有正确的 Redis 配置。

## 5. 使用 CacheKV

一旦配置完成，您就可以在应用程序的任何地方使用 `CacheManager` 来获取缓存实例：

```php
<?php

namespace App\Http\Controllers;

use Asfop\CacheKV\Cache\CacheManager;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function showUser(Request $request, $userId)
    {
        $cache = CacheManager::resolve('redis'); // 获取 Redis 缓存实例

        $user = $cache->get('user:' . $userId, function () use ($userId) {
            // 从数据库或其他源获取用户数据
            return \App\Models\User::find($userId)->toArray();
        }, 3600); // 缓存 1 小时

        return response()->json($user);
    }

    public function updateUser(Request $request, $userId)
    {
        $cache = CacheManager::resolve('redis');

        // 更新用户数据...
        // \App\Models\User::where('id', $userId)->update($request->all());

        // 清除相关缓存
        $cache->forget('user:' . $userId);
        $cache->clearTag('users'); // 如果您使用了标签

        return response()->json(['message' => 'User updated and cache cleared.']);
    }
}
```

通过这些步骤，CacheKV 将无缝集成到您的 Laravel 应用程序中，为您提供强大的缓存功能。