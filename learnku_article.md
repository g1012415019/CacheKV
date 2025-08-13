# 用 Amazon Q AI 写了个 PHP 缓存库，解决"若无则获取并回填"这个老问题

## 背景

最近在项目中频繁遇到这样的代码：

```php
$cacheKey = "user:profile:{$userId}";
$data = $redis->get($cacheKey);
if ($data === false) {
    $data = $this->getUserFromDatabase($userId);
    $redis->setex($cacheKey, 3600, json_encode($data));
} else {
    $data = json_decode($data, true);
}
```

这种"检查缓存 → 未命中则获取 → 回填缓存"的模式到处都是，每次都要写一遍，还容易出错。更麻烦的是缓存键的管理，经常出现：

- 键名不统一：`user_profile_123` vs `user:profile:123`
- 环境混乱：开发和生产环境的缓存互相干扰
- 版本问题：代码升级后旧缓存还在，导致数据不一致
- 批量操作复杂：要获取多个用户数据时，代码变得很冗长

想着能不能简化一下，就试着用 Amazon Q AI 来帮忙写个库。

## 成果

最终做出来的效果是这样的：

### 基础使用
```php
// 原来需要 7-8 行的逻辑，现在一行搞定
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
});
```

### 批量操作
```php
$users = kv_get_multi('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
], function($missedKeys) {
    // 只查询缓存中没有的数据
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $params = $cacheKey->getParams();
        $data[(string)$cacheKey] = getUserFromDatabase($params['id']);
    }
    return $data;
});
```

### 键管理
```php
// 统一的键生成
$key = kv_key('user.profile', ['id' => 123]);
// 输出: app:user:v1:profile:123

// 批量键生成
$keys = kv_keys('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
]);
// 输出: ['app:user:v1:profile:1', 'app:user:v1:profile:2', 'app:user:v1:profile:3']
```

### 缓存清理
```php
// 清空相关缓存
kv_delete('user.profile', ['id' => 123]);        // 删除指定用户的缓存
kv_delete_prefix('user.profile', ['id' => 123]); // 删除 user:profile:123 相关的所有缓存
kv_delete_full('user.profile');                  // 删除所有 user.profile.* 的缓存
```

## 为什么要做键管理

在开发这个库的过程中，发现缓存键管理是个很容易被忽视但很重要的问题：

### 传统方式的问题

```php
// 不同开发者可能写出不同的键名
$key1 = "user_profile_{$id}";           // 下划线风格
$key2 = "user:profile:{$id}";           // 冒号风格  
$key3 = "userProfile{$id}";             // 驼峰风格
$key4 = "cache_user_profile_{$id}";     // 带前缀

// 环境问题
$key = "user:profile:{$id}";            // 开发和生产用同样的键
$key = "dev_user:profile:{$id}";        // 手动加环境前缀，容易忘记

// 版本问题
$key = "user:profile:{$id}";            // v1.0 的数据结构
// 升级到 v2.0 后，缓存中还是旧数据，但代码期望新格式
```

### 统一键管理的好处

```php
// 配置文件中定义键模板
'user.profile' => [
    'template' => 'profile:{id}',
    'ttl' => 7200
]

// 使用时只需要模板名和参数
$user = kv_get('user.profile', ['id' => 123], $callback);
// 实际生成的键: app:user:v1:profile:123
//               ↑   ↑   ↑  ↑
//            应用前缀 组名 版本 模板
```

这样做的好处：
- **统一性**：所有键都遵循相同的命名规范
- **环境隔离**：不同环境自动使用不同前缀
- **版本管理**：升级时修改版本号，自动避开旧缓存
- **易维护**：键的定义集中管理，修改方便

## 开发过程

整个开发过程主要是和 Amazon Q 对话，描述需求，然后它帮忙写代码。大概的流程：

1. **需求描述**：我说想要一个简化缓存操作的库
2. **架构设计**：Q 建议了工厂模式 + 键管理的架构
3. **功能实现**：逐步实现核心功能、批量操作、统计等
4. **键管理设计**：这个是我提出的需求，Q 帮忙设计了模板系统
5. **代码优化**：Q 帮忙重构了几次，让代码更简洁
6. **文档编写**：README 和各种文档也是 Q 帮忙写的

说实话，AI 写代码的效率确实高，特别是这种有明确需求的工具库。当然也不是完全不用动脑子，需要不断地描述需求、提出改进意见。

## 主要特性

- **自动回填**：缓存未命中时自动执行回调并缓存结果
- **批量优化**：避免 N+1 查询问题
- **统计监控**：命中率、热点键统计
- **按前缀删除**：相当于按 tag 删除缓存
- **热点续期**：自动延长热点数据缓存时间
- **简洁API**：提供了 `kv_delete`、`kv_delete_prefix`、`kv_delete_full` 等直观的删除函数
- **键管理**：统一的键命名规范，支持环境隔离和版本管理

## 与其他方案的对比

### 对比原生 Redis 操作

**原生方式：**
```php
// 单个获取
$key = "user:profile:{$id}";
$data = $redis->get($key);
if ($data === false) {
    $data = getUserFromDatabase($id);
    $redis->setex($key, 3600, json_encode($data));
} else {
    $data = json_decode($data, true);
}

// 批量获取 - 复杂的逻辑
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
```

**CacheKV 方式：**
```php
// 单个获取
$user = kv_get('user.profile', ['id' => $id], function() use ($id) {
    return getUserFromDatabase($id);
});

// 批量获取
$users = kv_get_multi('user.profile', 
    array_map(fn($id) => ['id' => $id], $ids),
    function($missedKeys) {
        $missed = array_map(fn($key) => $key->getParams()['id'], $missedKeys);
        $freshData = getUsersFromDatabase($missed);
        $result = [];
        foreach ($missedKeys as $key) {
            $id = $key->getParams()['id'];
            $result[(string)$key] = $freshData[$id];
        }
        return $result;
    }
);
```

### 对比 Laravel Cache

**Laravel 方式：**
```php
// 单个获取
$user = Cache::remember("user.profile.{$id}", 3600, function() use ($id) {
    return getUserFromDatabase($id);
});

// 批量获取 - Laravel 没有直接支持，需要自己实现
$users = [];
foreach ($ids as $id) {
    $users[$id] = Cache::remember("user.profile.{$id}", 3600, function() use ($id) {
        return getUserFromDatabase($id);
    });
}
// 这样会产生 N 次数据库查询，性能很差
```

**CacheKV 的优势：**
- 原生支持批量操作，避免 N+1 问题
- 统一的键管理，不需要手动拼接键名
- 更丰富的删除操作（按前缀删除等）
- 内置统计和热点键管理

## 安装使用

```bash
composer require asfop/cache-kv
```

```php
use Asfop\CacheKV\Core\CacheKVFactory;

// 配置 Redis
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});

// 获取缓存
$data = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 清空缓存
kv_delete('user.profile', ['id' => 123]);        // 删除指定用户缓存
kv_delete_full('user.profile');                  // 删除所有用户资料缓存
```

## 一些思考

用 AI 写代码这事儿，感觉有几个点：

**优势：**
- 效率确实高，特别是写工具库这种相对标准的代码
- 能快速生成文档、测试用例
- 对于架构设计有不错的建议
- 在键管理这种细节设计上，AI 提供了很好的思路

**局限：**
- 还是需要人来把控需求和方向
- 复杂的业务逻辑还是得自己想
- 生成的代码需要仔细review
- 对于性能优化，还是需要人工调优

**关于键管理的思考：**
这个功能其实是在开发过程中逐渐意识到重要性的。一开始只想解决"若无则获取"的问题，但在实际使用中发现键的管理同样重要。AI 在这方面给了很好的建议，提出了模板系统的设计，这比我最初想的简单字符串拼接要好很多。

总的来说，AI 更像是一个很厉害的编程助手，能大大提高开发效率，但不能完全替代思考。特别是在产品设计和用户体验方面，还是需要人来把控。

## 项目地址

- **GitHub**: https://github.com/g1012415019/CacheKV
- **Packagist**: https://packagist.org/packages/asfop/cache-kv

代码都开源了，有兴趣的可以看看。如果觉得有用，给个 star 就很开心了 😊

---

*这个库主要解决的就是缓存操作的重复代码问题，加上统一的键管理，让常见操作更简单一些。没什么高深的技术，就是让开发更高效一点。*
