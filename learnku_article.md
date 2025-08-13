# 用 Amazon Q AI 写了个 PHP 缓存库，解决"若无则获取并回填"这个老问题

## 遇到的问题

做项目时经常写这样的代码：

### 单个数据获取
```php
// 原生 Redis 写法 - 每次都要写这么一堆
$cacheKey = "user:profile:{$userId}";
$data = $redis->get($cacheKey);
if ($data === false) {
    $data = $this->getUserFromDatabase($userId);
    $redis->setex($cacheKey, 3600, json_encode($data));
} else {
    $data = json_decode($data, true);
}

// Laravel Cache 写法 - 简单一些，但键名还是要手动拼接
$user = Cache::remember("user.profile.{$id}", 3600, function() use ($id) {
    return getUserFromDatabase($id);
});
```

### 批量数据获取
```php
// 原生 Redis 写法 - 更复杂，要处理哪些有缓存哪些没有
$keys = [];
foreach ($ids as $id) {
    $keys[] = "user:profile:{$id}";
}
$cached = $redis->mget($keys);
$result = [];
$missed = [];
foreach ($ids as $index => $id) {
    if ($cached[$index] !== false) {
        $result[$id] = json_decode($cached[$index], true);
    } else {
        $missed[] = $id;
    }
}
if (!empty($missed)) {
    $freshData = getUsersFromDatabase($missed);
    foreach ($freshData as $id => $data) {
        $result[$id] = $data;
        $redis->setex("user:profile:{$id}", 3600, json_encode($data));
    }
}

// Laravel Cache 写法 - 会产生N次数据库查询
$users = [];
foreach ($ids as $id) {
    $users[$id] = Cache::remember("user.profile.{$id}", 3600, function() use ($id) {
        return getUserFromDatabase($id); // 每个用户都查一次数据库
    });
}
```

这种"先查缓存，没有就查数据库，然后存回缓存"的套路到处都是。单个数据还好，批量操作就比较繁琐了。

更麻烦的是缓存键的命名，团队里每个人都有自己的风格：

```php
// 不同的命名风格
$key1 = "user_profile_{$id}";      // 下划线风格
$key2 = "user:profile:{$id}";      // 冒号风格  
$key3 = "userProfile{$id}";        // 驼峰风格
$key4 = "cache_user_profile_{$id}"; // 带前缀
```

结果就是：
- 同样的数据，可能有好几个不同的缓存键
- 开发环境和生产环境的缓存容易混淆
- 代码升级后，旧缓存还在那里，新代码读到旧数据就容易出问题

想着能不能写个工具简化一下，正好最近在用 Amazon Q AI，就让它帮忙写了个库。

## 解决方案

现在变成这样：

### 单个数据获取
```php
// 原来需要7-8行代码，现在1行搞定
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只有缓存没有时才执行
});
```

### 批量数据获取
```php
// 要获取多个用户的数据
$users = kv_get_multi('user.profile', [
    ['id' => 1], 
    ['id' => 2], 
    ['id' => 3]
], function($missedKeys) {
    // 这个函数只会收到缓存中没有的键
    // 比如缓存中有id=1的数据，这里就只会收到id=2,3
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $params = $cacheKey->getParams();
        $data[(string)$cacheKey] = getUserFromDatabase($params['id']);
    }
    return $data;
});
```

批量操作会把所有没有缓存的数据一次性查出来，避免了N+1查询问题。

### 统一的键管理
```php
// 不用再手写键名了
$key = kv_key('user.profile', ['id' => 123]);
// 自动生成: app:user:v1:profile:123

// 批量生成键名
$keys = kv_keys('user.profile', [
    ['id' => 1], 
    ['id' => 2], 
    ['id' => 3]
]);
```

### 删除缓存
```php
kv_delete('user.profile', ['id' => 123]); // 删除这个用户的缓存
kv_delete_full('user.profile');           // 删除所有用户资料缓存
```

## 键管理怎么做的

在配置文件里定义好模板：

```php
// 配置文件
'user.profile' => [
    'template' => 'profile:{id}', // 键的模板
    'ttl' => 7200                 // 缓存2小时
]
```

使用时只需要说明要什么数据：

```php
$user = kv_get('user.profile', ['id' => 123], $callback);
// 库会自动生成标准的键名：app:user:v1:profile:123
```

这个键名的结构是：`应用前缀:组名:版本:具体键`

好处：
- **统一规范**：所有人用的键名格式都一样
- **环境隔离**：开发、测试、生产环境自动用不同前缀
- **版本控制**：升级时改个版本号，自动避开旧缓存
- **集中管理**：所有键的定义都在配置文件里，好维护

## 怎么用

安装：
```bash
composer require asfop/cache-kv
```

配置：
```php
use Asfop\CacheKV\Core\CacheKVFactory;

// 告诉库怎么连Redis
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});
```

使用：
```php
// 获取数据
$data = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 删除缓存
kv_delete('user.profile', ['id' => 123]);
```

## 开发过程和感受

整个开发过程主要是和 Amazon Q 对话：

1. **我说需求**：想要个简化缓存操作的库
2. **AI 给建议**：建议用工厂模式，还提出了键管理的想法
3. **逐步完善**：一步步加功能，批量操作、统计、热点键续期等
4. **优化代码**：AI 帮忙重构了好几次，让代码更简洁
5. **写文档**：README 和各种文档也是 AI 帮忙写的

**AI 的优势：**
- 写代码确实快，特别是这种工具库
- 架构设计有想法，键管理系统就是它建议的
- 文档写得不错，比我自己写的清楚

**AI 的局限：**
- 需求还是要人来想清楚
- 生成的代码要仔细检查
- 复杂的业务逻辑还是得人来设计

总的来说，AI 更像个很厉害的助手，能大大提高效率，但不能完全替代思考。

## 主要功能

- **自动回填**：缓存没有时自动查数据库并存回缓存
- **批量优化**：一次获取多个数据，避免N+1查询
- **统一键管理**：标准化的键命名，支持环境隔离和版本控制
- **灵活删除**：可以删除单个缓存，也可以按前缀批量删除
- **统计监控**：可以看缓存命中率、热点键等
- **热点续期**：访问频繁的缓存自动延长过期时间

## 项目地址

- **GitHub**: https://github.com/g1012415019/CacheKV
- **Packagist**: https://packagist.org/packages/asfop/cache-kv

代码都开源了，觉得有用的话给个 star 😊

---

*就是个解决重复代码的小工具，让缓存操作简单一些。没什么高深技术，但确实能提高开发效率。*
