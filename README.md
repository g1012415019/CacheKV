# 介绍

eloquent 提供了模型关联关系中的一对一和一对多查询。这两种情况下，查询出的数据可以进行缓存以提高性能
在一对一关联情况下的数据缓存的一种尝试 

# 初衷

在多表连接查询的时候，我的一般做法，我会先查主表，然后再array_column条件id去匹配副表信息.

例如， `users` 和 `phones` 两张表，它们通过 `user_id` 进行关联

**表结构**

1. users 表：

| 字段名   | 类型      | 描述   |
|-------|---------|------|
| id    | Integer | 主键   |
| name  | String  | 用户名  |
| email | String  | 邮箱地址 |

2. phones 表：

| 字段名          | 类型      | 描述                 |
|--------------|---------|--------------------|
| id           | Integer | 主键                 |
| user_id      | Integer | 外键，关联到 users 表的 id |
| phone_number | String  | 电话号码               |

实现方式

```php
use App\Models\User;
use App\Models\Phone;

$userData = User::select('id', 'name', 'email')
                ->get();

// 获取所有用户的 id
$userIds = $userData->pluck('id')->toArray(); 

// 获取每个用户的电话号码
$phoneData = Phone::whereIn('user_id', $userIds)
                  ->select('user_id', 'phone_number')
                  ->get()
                  ->groupBy('user_id');

// 将电话号码数据合并到用户数据中
foreach ($userData as $user) {
    if ($phoneData->has($user->id)) {
        $user->phones = $phoneData[$user->id]->pluck('phone_number')->toArray();
    } else {
        $user->phones = []; // 如果没有电话号码，则设为空数组或 null，视情况而定
    }
}

// 现在 $userData 中每个 $user 对象都有 phones 属性，包含了该用户的所有电话号码

return $userData;

```

**为了简化上述操作，进行了封装，并且对查询出来的结果使用redis将数据缓存**

## 使用案例
***
1. 获取用户id为10000的信息

```php

use App\Models\User;
use App\Models\Phone;

$userEloquent = new UserEloquent();
$users=$userEloquent->getById(10000,['info','phone']);
// 结果
 Array
(
    "info" => Array
    (
        "id" => 10000,
        "name" => "Jane",
        "email" => "jane@example.com"
    ),
    "phone" => Array
    (
        "id" => 1,
        "user_id" => 10000,
        "phone_number" => "13681985439"
    )
)
```
2. 获取用户10000和10001的信息

```php

use App\Models\User;
use App\Models\Phone;

$userEloquent = new UserEloquent();
$users=$userEloquent->getByIds([10000,10001],['info','phone']);
// 结果
Array
(
    10000 => Array
    (
        "info" => Array
        (
            "id" => 10000,
            "name" => "Jane",
            "email" => "jane@example.com"
        ),
        "phone" => Array
        (
            "id" => 1,
            "user_id" => 10000,
            "phone_number" => "13681985439"
        )
    ),

    10001 => Array
    (
        "info" => Array
        (
            "id" => 10001,
            "name" => "Jane Doe",
            "email" => "jane.doe@example.com"
        ),
        "phone" => Array
        (
            "id" => 2,
            "user_id" => 10001,
            "phone_number" => "13712345678"
        )
    )
    // 可能还有其他用户的信息，取决于查询到的用户数量
)
```
### 如何实现上述查询
```php
//可能存在bug
composer require asfop/eloquent

```
#### 在Hyperf中使用
创建`Eloquent` 文件夹 目录结构如下
```shell
├── User
│   ├── UserDrive.php
│   ├── UserEloquent.php
│   └── Attribute
│       ├── Info.php
│       └── Phone.php
```
- `User` 模型 一对一关联
- `UserDrive.php` 属性名对应的数据查询类
- `UserEloquent.php` 自定义快捷查询的类。可定义查询单个用户名，和多个用户函数名
- `Attribute/Info.php` 基础信息实现类
- `Attribute/Phone.php` Phone信息实现类

UserEloquent.php 解释

```php
<?php

namespace App\Eloquent\Activity;

use App\Log\Log;
use Asfop\Eloquent\Cache;
use Asfop\Eloquent\Eloquent;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ActivityEloquent
{
    /**
     * 缓存键的前缀，控制整个缓存版本，版本号更改后所有用户缓存失效
     */
    const CACHE_KEY_PREFIX = "c:eloquent:user:v1";

    /**
     * 根据一组用户ID获取多个信息
     *
     * @param array $ids
     * @param array $attrs
     * @return array
     */
    public function getByIds(array $ids, array $attrs = []): array
    {
        try {
            // 获取 Redis 实例并创建缓存对象
            $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
            $cache = new Cache($redis);

            // 创建数据驱动对象并实例化 Eloquent 类
            $drive = new UserDrive(); // 假设这是用户数据驱动的实例化
            $eloquent = new Eloquent($cache, $drive, self::CACHE_KEY_PREFIX);

            // 调用 Eloquent 实例的方法获取信息列表
            return $eloquent->getInfoList(array_unique($ids), $attrs);
        } catch (\Exception $exception) {
            // 记录错误日志
            Log::error("user_eloquent_get_by_ids", [$exception->getMessage(), $exception->getFile(), $exception->getLine()]);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $exception) {
            // 记录错误日志
            Log::error("user_eloquent_get_by_ids", [$exception->getMessage(), $exception->getFile(), $exception->getLine()]);
        }
        
        return [];
    }

    /**
     * 根据用户ID获取单个信息
     *
     * @param int $id
     * @param array $attrs
     * @return array
     */
    public function getById(int $id, array $attrs = []): array
    {
        try {
            // 获取 Redis 实例并创建缓存对象
            $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
            $cache = new Cache($redis);

            // 创建数据驱动对象并实例化 Eloquent 类
            $drive = new UserDrive(); // 假设这是用户数据驱动的实例化
            $eloquent = new Eloquent($cache, $drive, self::CACHE_KEY_PREFIX);

            // 调用 Eloquent 实例的方法获取信息列表
            return $eloquent->getInfoList([$id], $attrs);
        } catch (\Exception $exception) {
            // 记录错误日志
            Log::error("user_eloquent_get_by_id", [$exception->getMessage(), $exception->getFile(), $exception->getLine()]);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $exception) {
            // 记录错误日志
            Log::error("user_eloquent_get_by_id", [$exception->getMessage(), $exception->getFile(), $exception->getLine()]);
        }
        
        return [];
    }

    /**
     * 清除特定用户特定属性的缓存
     *
     * @param int $id
     * @param string $attr
     * @return void
     */
    public function forgetCache(int $id, string $attr): void
    {
        try {
            // 获取 Redis 实例并创建缓存对象
            $redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
            $cache = new Cache($redis);

            // 创建数据驱动对象并实例化 Eloquent 类
            $drive = new UserDrive(); // 假设这是用户数据驱动的实例化
            $eloquent = new Eloquent($cache, $drive, self::CACHE_KEY_PREFIX);

            // 调用 Eloquent 实例的方法清除缓存
            $eloquent->forgetCache($id, $attr);
        } catch (\Exception $exception) {
            // 记录错误日志
            Log::error("user_eloquent_forget_cache", [$exception->getMessage()]);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $exception) {
            // 记录错误日志
            Log::error("user_eloquent_forget_cache", [$exception->getMessage()]);
        }
    }
}

```

Info.php 解释
```php
<?php

namespace App\Eloquent\User\Attribute;

use App\Models\User;
use Asfop\Eloquent\attribute\Base;

class Info extends Base
{
    /**
     * 当前属性的版本缓存版本，用于在当前属性查询字段新增或减少后更新缓存
     * @var string
     */
    protected $cacheVersion = "v1";

    /**
     * 获取此类的名称或类型标识
     * @return string
     */
    public static function getNames(): string
    {
        return 'info';
    }

    /**
     * @inheritDoc
     */
    public function getField(): array
    {
        return ["id", "name", "email"];
    }

    /**
     * 根据给定的 id 数组从数据库获取成员信息
     * 当缓存不存在时会执行该方法，存在缓存后则不再执行
     * @inheritDoc
     */
    public function getInfoByIds(): array
    {
        $userData = User::whereIn('uid', $this->getIds())
            ->select($this->getField())
            ->get();

        return $userData;
    }

    /**
     * 根据数据进行转换处理，每次调用都会执行此方法
     * @inheritDoc
     */
    public function transform($data): array
    {
        if (empty($data)) {
            return [];
        }

        return [
            "id" => $data["id"],
            "email" => $data["email"],
            "name" => $data["name"],
        ];
    }
}

```

## 开发计划

- [x] 基本功能够使用
- [x] 提供基础文档
- [x] 查询功能加上数据缓存,缓存加上版本控制，缓存支持清空
- [ ] 支持是否使用缓存开关
- [ ] 支持那些属性查询不走缓存
- [ ] 支持更多的缓存方式如文件,Memcache缓存方式，
- [ ] 支持并发重复请求
- [ ] 支持多级缓存，内存缓存->redis缓存
- [ ] 命名优化，功能优化
- [ ] 完善测试用例
