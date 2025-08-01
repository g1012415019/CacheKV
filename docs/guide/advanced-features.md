# 高级特性

CacheKV 提供了多种高级特性，帮助您构建更加智能和高效的缓存系统。

## 滑动过期机制

滑动过期是指当缓存项被访问时，自动延长其过期时间。这对于热点数据特别有用。

### 基本用法

```php
// 启用滑动过期
$user = $cache->getByTemplate('user', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 3600, true); // 最后一个参数启用滑动过期
```

### 适用场景

- **用户会话管理** - 活跃用户自动延长会话
- **热点内容缓存** - 热门文章保持缓存
- **API 响应缓存** - 频繁查询的 API 保持新鲜

详细信息请参考：[滑动过期机制详解](../examples/sliding-expiration.md)

## 缓存穿透预防

CacheKV 自动缓存空值，防止缓存穿透攻击。

### 自动防穿透

```php
$user = $cache->getByTemplate('user', ['id' => 999999], function() {
    return getUserFromDatabase(999999); // 返回 null
});

// 即使返回 null，也会被缓存，防止重复查询数据库
```

### 防护效果

- **防止恶意攻击** - 大量查询不存在的数据
- **保护数据库** - 减少无效查询
- **提升性能** - 空值也能享受缓存加速

详细信息请参考：[缓存穿透预防策略](../examples/cache-penetration.md)

## 智能批量操作

CacheKV 的批量操作能够智能处理缓存命中和未命中的情况。

### 批量获取优化

```php
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    // 只查询缓存中不存在的数据
    return getUsersFromDatabase($missingKeys);
});
```

### 性能优势

- **避免 N+1 查询** - 批量查询替代循环查询
- **智能命中处理** - 自动分离命中和未命中
- **减少网络开销** - 批量操作减少网络往返

## 标签系统

基于标签的缓存管理，支持批量失效相关缓存。

### 标签设置

```php
// 设置带标签的缓存
$cache->setByTemplateWithTag('user', ['id' => 123], $userData, ['users', 'vip_users']);
```

### 批量清除

```php
// 清除所有用户相关缓存
$cache->clearTag('users');
```

### 标签设计最佳实践

```php
// 层次化标签设计
$tags = [
    'users',           // 全局用户标签
    'user_123',        // 特定用户标签
    'profiles',        // 功能模块标签
    'vip_users'        // 业务分组标签
];
```

详细信息请参考：[标签失效管理](../examples/tag-invalidation.md)

## 统一键管理

KeyManager 提供统一的缓存键命名和管理。

### 键命名规范

```
{app_prefix}:{env_prefix}:{version}:{business_key}
```

### 环境隔离

```php
// 不同环境使用不同的键前缀
$devKeyManager = new KeyManager(['env_prefix' => 'dev']);
$prodKeyManager = new KeyManager(['env_prefix' => 'prod']);
```

### 版本管理

```php
// 数据结构升级时使用新版本
$v1KeyManager = new KeyManager(['version' => 'v1']);
$v2KeyManager = new KeyManager(['version' => 'v2']);
```

详细信息请参考：[Key 管理指南](key-management.md)

## 性能监控

CacheKV 提供详细的性能统计信息。

### 获取统计

```php
$stats = $cache->getStats();
/*
返回:
[
    'hits' => 85,        // 命中次数
    'misses' => 15,      // 未命中次数
    'hit_rate' => 85.0   // 命中率
]
*/
```

### 性能分析

```php
if ($stats['hit_rate'] < 70) {
    // 缓存命中率过低，需要优化
    $this->optimizeCacheStrategy();
}
```

## 容错机制

CacheKV 提供多种容错机制，确保系统稳定性。

### 驱动降级

```php
try {
    $cache = new CacheKV(new RedisDriver(), 3600, $keyManager);
} catch (Exception $e) {
    // Redis 不可用时降级到内存缓存
    $cache = new CacheKV(new ArrayDriver(), 3600, $keyManager);
}
```

### 缓存失败处理

```php
try {
    $data = $cache->getByTemplate('user', ['id' => 123], $callback);
} catch (CacheException $e) {
    // 缓存失败时直接从数据源获取
    $data = getUserFromDatabase(123);
}
```

## 扩展开发

CacheKV 支持自定义驱动和扩展开发。

### 自定义驱动

```php
class CustomDriver implements CacheDriver
{
    public function get(string $key): mixed
    {
        // 自定义获取逻辑
    }
    
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        // 自定义设置逻辑
    }
    
    // 实现其他接口方法...
}
```

### 中间件支持

```php
class CacheMiddleware
{
    public function handle($request, Closure $next)
    {
        // 缓存中间件逻辑
        return $next($request);
    }
}
```

## 最佳实践总结

### 1. 合理设置 TTL

```php
// 根据数据特性设置不同的过期时间
$cache->getByTemplate('user', ['id' => $id], $callback, 3600);      // 用户信息：1小时
$cache->getByTemplate('product', ['id' => $id], $callback, 7200);   // 商品信息：2小时
$cache->getByTemplate('price', ['id' => $id], $callback, 600);      // 价格信息：10分钟
```

### 2. 使用批量操作

```php
// ✅ 推荐：批量获取
$users = $cache->getMultiple($userKeys, $batchCallback);

// ❌ 避免：循环单次获取
foreach ($userIds as $id) {
    $users[] = $cache->getByTemplate('user', ['id' => $id], $callback);
}
```

### 3. 合理使用标签

```php
// 按业务维度设置标签
$cache->setByTemplateWithTag('user', ['id' => $id], $data, ['users', "user_{$id}"]);
```

### 4. 监控缓存效果

```php
$stats = $cache->getStats();
if ($stats['hit_rate'] < 70) {
    // 优化缓存策略
}
```

## 下一步

了解了高级特性后，建议您：

1. 查看 [性能优化指南](performance.md) 了解优化技巧
2. 阅读 [实战案例](../examples/) 了解实际应用
3. 参考 [API 文档](../reference/api.md) 了解详细接口

---

**通过这些高级特性，您可以构建更加智能和高效的缓存系统！** 🚀
