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

这种"检查缓存 → 未命中则获取 → 回填缓存"的模式到处都是，每次都要写一遍，还容易出错。想着能不能简化一下，就试着用 Amazon Q AI 来帮忙写个库。

## 成果

最终做出来的效果是这样的：

```php
// 原来需要 7-8 行的逻辑，现在一行搞定
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只在缓存未命中时执行
});
```

批量操作也很简单：

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

// 清空相关缓存
kv_delete('user.profile', ['id' => 123]);        // 删除指定用户的缓存
kv_delete_prefix('user.profile', ['id' => 123]); // 删除 user:profile:123 相关的所有缓存
kv_delete_full('user.profile');                  // 删除所有 user.profile.* 的缓存
```

## 开发过程

整个开发过程主要是和 Amazon Q 对话，描述需求，然后它帮忙写代码。大概的流程：

1. **需求描述**：我说想要一个简化缓存操作的库
2. **架构设计**：Q 建议了工厂模式 + 键管理的架构
3. **功能实现**：逐步实现核心功能、批量操作、统计等
4. **代码优化**：Q 帮忙重构了几次，让代码更简洁
5. **文档编写**：README 和各种文档也是 Q 帮忙写的

说实话，AI 写代码的效率确实高，特别是这种有明确需求的工具库。当然也不是完全不用动脑子，需要不断地描述需求、提出改进意见。

## 主要特性

- **自动回填**：缓存未命中时自动执行回调并缓存结果
- **批量优化**：避免 N+1 查询问题
- **统计监控**：命中率、热点键统计
- **按前缀删除**：相当于按 tag 删除缓存
- **热点续期**：自动延长热点数据缓存时间
- **简洁API**：提供了 `kv_delete`、`kv_delete_prefix`、`kv_delete_full` 等直观的删除函数

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

**局限：**
- 还是需要人来把控需求和方向
- 复杂的业务逻辑还是得自己想
- 生成的代码需要仔细review

总的来说，AI 更像是一个很厉害的编程助手，能大大提高开发效率，但不能完全替代思考。

## 项目地址

- **GitHub**: https://github.com/g1012415019/CacheKV
- **Packagist**: https://packagist.org/packages/asfop/cache-kv

代码都开源了，有兴趣的可以看看。如果觉得有用，给个 star 就很开心了 😊

---

*这个库主要解决的就是缓存操作的重复代码问题，没什么高深的技术，就是让常见操作更简单一些。*
