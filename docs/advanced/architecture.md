# CacheKV 架构文档

## 概述

CacheKV 是一个基于驱动模式设计的 PHP 缓存库，采用分层架构，提供统一的缓存操作接口。其核心设计理念是简化"若无则从数据源获取并回填缓存"这一常见模式。

## 架构设计

### 整体架构图

```
┌─────────────────────────────────────────────────────────────┐
│                    应用层 (Application Layer)                │
├─────────────────────────────────────────────────────────────┤
│  CacheKVFacade (门面)     │    直接使用 CacheKV 实例        │
├─────────────────────────────────────────────────────────────┤
│                    服务层 (Service Layer)                   │
├─────────────────────────────────────────────────────────────┤
│  CacheKVServiceProvider   │         CacheKV                 │
│  (服务提供者)              │       (核心缓存类)               │
├─────────────────────────────────────────────────────────────┤
│                    管理层 (Management Layer)                │
├─────────────────────────────────────────────────────────────┤
│                    CacheManager                             │
│                   (缓存管理器)                               │
├─────────────────────────────────────────────────────────────┤
│                    抽象层 (Abstraction Layer)               │
├─────────────────────────────────────────────────────────────┤
│                   CacheDriver Interface                    │
│                    (缓存驱动接口)                            │
├─────────────────────────────────────────────────────────────┤
│                    驱动层 (Driver Layer)                    │
├─────────────────────────────────────────────────────────────┤
│   RedisDriver     │    ArrayDriver    │   自定义驱动...      │
├─────────────────────────────────────────────────────────────┤
│                    存储层 (Storage Layer)                   │
├─────────────────────────────────────────────────────────────┤
│   Redis Server    │   PHP Array       │   其他存储...        │
└─────────────────────────────────────────────────────────────┘
```

## 核心组件

### 1. CacheKV (核心缓存类)

**职责：**
- 实现核心的缓存回填逻辑
- 提供统一的缓存操作接口
- 管理缓存的生命周期

**核心方法：**
```php
// 核心功能：自动回填缓存
public function get($key, $callback = null, $ttl = null)

// 批量操作：智能处理批量缓存
public function getMultiple($keys, $callback, $ttl = null)

// 标签管理：设置带标签的缓存
public function setWithTag($key, $value, $tags, $ttl = null)

// 批量失效：清除标签下所有缓存
public function clearTag($tag)
```

### 2. CacheDriver Interface (缓存驱动接口)

**职责：**
- 定义统一的缓存操作规范
- 抽象不同存储后端的差异
- 提供可扩展的驱动架构

**核心方法：**
```php
public function get($key);                    // 获取缓存
public function set($key, $value, $ttl);     // 设置缓存
public function getMultiple(array $keys);    // 批量获取
public function setMultiple(array $values, $ttl); // 批量设置
public function tag($key, array $tags);      // 标签关联
public function clearTag($tag);              // 清除标签
public function getStats();                  // 获取统计
public function touch($key, $ttl);           // 更新过期时间
```

### 3. 驱动实现

#### RedisDriver
- **用途：** 生产环境推荐
- **特性：** 高性能、持久化、分布式支持
- **依赖：** Redis 服务器 + predis/predis

#### ArrayDriver  
- **用途：** 开发测试环境
- **特性：** 简单快速、无外部依赖
- **限制：** 仅在当前请求生命周期内有效

### 4. CacheManager (缓存管理器)

**职责：**
- 管理多个缓存驱动实例
- 提供驱动的创建和解析
- 支持自定义驱动扩展

### 5. CacheKVServiceProvider (服务提供者)

**职责：**
- 简化库的初始化过程
- 提供配置管理
- 集成到各种 PHP 框架

### 6. CacheKVFacade (门面)

**职责：**
- 提供静态方法访问
- 简化使用方式
- 适合全局使用场景

## 设计模式

### 1. 策略模式 (Strategy Pattern)
- **应用：** CacheDriver 接口及其实现
- **优势：** 可以在运行时切换不同的缓存策略

### 2. 门面模式 (Facade Pattern)
- **应用：** CacheKVFacade 类
- **优势：** 简化复杂子系统的使用

### 3. 工厂模式 (Factory Pattern)
- **应用：** CacheManager 中的驱动创建
- **优势：** 解耦对象创建和使用

### 4. 服务提供者模式 (Service Provider Pattern)
- **应用：** CacheKVServiceProvider 类
- **优势：** 标准化的服务注册和启动

## 数据流

### 单条数据获取流程

```
用户调用 get($key, $callback)
         ↓
检查缓存是否存在 (driver->get($key))
         ↓
    ┌─────────┐         ┌─────────┐
    │ 缓存命中 │         │ 缓存未命中│
    └─────────┘         └─────────┘
         ↓                   ↓
   更新过期时间          执行回调函数
   (touch方法)           获取数据
         ↓                   ↓
     返回数据            回填缓存
                        (set方法)
                            ↓
                        返回数据
```

### 批量数据获取流程

```
用户调用 getMultiple($keys, $callback)
         ↓
批量检查缓存 (driver->getMultiple($keys))
         ↓
分离命中和未命中的键
         ↓
    ┌─────────┐         ┌─────────┐
    │ 部分命中 │         │ 全部未命中│
    └─────────┘         └─────────┘
         ↓                   ↓
   收集命中数据          执行回调函数
         ↓              获取缺失数据
   执行回调获取              ↓
   缺失数据              批量回填缓存
         ↓              (setMultiple)
   批量回填缓存              ↓
         ↓              合并所有数据
   合并所有数据              ↓
         ↓              返回完整结果
   返回完整结果
```

## 扩展性设计

### 1. 自定义驱动

```php
// 实现 CacheDriver 接口
class CustomDriver implements CacheDriver 
{
    // 实现所有接口方法
}

// 注册自定义驱动
CacheManager::extend('custom', function() {
    return new CustomDriver();
});
```

### 2. 框架集成

通过 ServiceProvider 模式，可以轻松集成到各种框架：
- Laravel: 通过 Laravel Service Provider
- ThinkPHP: 通过 ThinkPHP Service
- Webman: 通过 Webman Plugin

### 3. 中间件支持

可以通过装饰器模式添加中间件功能：
- 缓存加密/解密
- 缓存压缩
- 访问日志记录
- 性能监控

## 性能考虑

### 1. 批量操作优化
- 使用 `getMultiple` 和 `setMultiple` 减少网络往返
- Redis 驱动使用 pipeline 技术

### 2. 内存管理
- ArrayDriver 在请求结束时自动释放内存
- 避免大对象的长期缓存

### 3. 网络优化
- Redis 连接复用
- 合理的超时设置

## 安全考虑

### 1. 缓存穿透防护
- 自动缓存 null 值
- 防止恶意请求绕过缓存

### 2. 缓存雪崩防护
- TTL 随机化支持
- 分布式锁机制（可扩展）

### 3. 数据安全
- 支持缓存数据加密（可扩展）
- 敏感数据的 TTL 控制

## 监控和调试

### 1. 统计信息
```php
$stats = $cache->getStats();
// 返回：hits, misses, hit_rate
```

### 2. 日志支持
- 可扩展的日志记录机制
- 支持不同级别的日志

### 3. 调试工具
- 缓存键的枚举
- 缓存内容的查看
- 性能分析工具

## 最佳实践

### 1. 键命名规范
```php
// 推荐的键命名模式
$userKey = "user:{$userId}";
$productKey = "product:{$productId}";
$listKey = "products:category:{$categoryId}:page:{$page}";
```

### 2. TTL 设置策略
```php
// 根据数据特性设置不同的 TTL
$cache->set('user:profile:' . $id, $profile, 3600);    // 1小时
$cache->set('product:info:' . $id, $product, 86400);   // 1天
$cache->set('config:system', $config, 604800);         // 1周
```

### 3. 标签使用规范
```php
// 合理的标签分组
$cache->setWithTag('user:' . $id, $user, ['users', 'user_' . $id]);
$cache->setWithTag('post:' . $id, $post, ['posts', 'user_posts_' . $userId]);
```

这个架构设计确保了 CacheKV 的高可扩展性、高性能和易用性，同时保持了代码的清晰和可维护性。
