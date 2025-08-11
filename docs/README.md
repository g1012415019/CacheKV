# CacheKV 完整文档

CacheKV 是一个专注于简化缓存操作的 PHP 库，核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式。

## 🎯 核心特性

- **自动回填缓存**：缓存未命中时自动执行回调并缓存结果
- **批量操作优化**：高效的批量获取，避免N+1查询问题
- **按前缀删除**：支持按键前缀批量删除缓存，相当于按 tag 删除
- **热点键自动续期**：自动检测并延长热点数据的缓存时间
- **统计监控**：实时统计命中率、热点键等性能指标
- **统一键管理**：标准化键生成，支持环境隔离和版本管理

## 📦 安装

```bash
composer require g1012415019/cache-kv
```

## ⚡ 快速开始

### 基础配置

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 配置Redis连接
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});
```

### 基础使用

```php
// 单个数据获取
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 批量数据获取
$users = kv_get_multi('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
], function($missedKeys) {
    $results = [];
    foreach ($missedKeys as $cacheKey) {
        $params = $cacheKey->getParams();
        $results[(string)$cacheKey] = getUserFromDatabase($params['id']);
    }
    return $results;
});
```

## 🔧 助手函数 API

CacheKV 提供了简洁易用的助手函数：

### 核心操作
- `kv_get($template, $params, $callback, $ttl)` - 获取缓存
- `kv_get_multi($template, $paramsList, $callback)` - 批量获取

### 键管理
- `kv_key($template, $params)` - 创建键字符串
- `kv_keys($template, $paramsList)` - 批量创建键
- `kv_get_keys($template, $paramsList)` - 获取键对象

### 删除操作
- `kv_delete_prefix($template, $params)` - 按前缀删除
- `kv_delete_full($prefix)` - 按完整前缀删除

### 统计功能
- `kv_stats()` - 获取统计信息
- `kv_hot_keys($limit)` - 获取热点键
- `kv_clear_stats()` - 清空统计

### 配置管理
- `kv_config()` - 获取配置对象

## 🏗️ 架构设计

### 核心组件

1. **CacheKVFactory** - 工厂类，负责初始化和配置
2. **CacheKV** - 核心缓存操作类
3. **KeyManager** - 键管理器，负责键的生成和管理
4. **DriverInterface** - 驱动接口，支持多种缓存后端
5. **KeyStats** - 统计功能，监控缓存性能

### 数据流程

```
用户调用 kv_get()
    ↓
KeyManager 生成 CacheKey
    ↓
CacheKV 检查缓存
    ↓
缓存命中 → 返回数据
    ↓
缓存未命中 → 执行回调 → 缓存结果 → 返回数据
```

## ⚙️ 配置系统

### 配置文件结构

```php
<?php
return [
    // 全局缓存配置
    'cache' => [
        'ttl' => 3600,                    // 默认TTL
        'enable_stats' => true,           // 启用统计
        'hot_key_auto_renewal' => true,   // 热点键自动续期
    ],
    
    // 键管理配置
    'key_manager' => [
        'app_prefix' => 'app',            // 应用前缀
        'separator' => ':',               // 分隔符
        'groups' => [
            'user' => [
                'prefix' => 'user',
                'version' => 'v1',
                'cache' => [
                    'ttl' => 7200,        // 组级TTL覆盖
                ],
                'keys' => [
                    'profile' => ['template' => '{id}'],
                    'settings' => ['template' => '{id}'],
                ]
            ]
        ]
    ]
];
```

### 配置优先级

1. 函数参数（最高优先级）
2. 键级配置
3. 组级配置
4. 全局配置（最低优先级）

## 🔑 键管理系统

### 键模板格式

键模板使用 `group.key` 格式：

```php
// 模板格式：'group.key'
kv_get('user.profile', ['id' => 123]);
// 生成键：app:user:v1:123

kv_get('product.info', ['id' => 456, 'lang' => 'en']);
// 生成键：app:product:v1:456:en
```

### 键生成规则

完整的键格式：`{app_prefix}:{group_prefix}:{version}:{template_result}`

- `app_prefix`: 应用前缀，用于环境隔离
- `group_prefix`: 组前缀，用于分类管理
- `version`: 版本号，用于缓存版本控制
- `template_result`: 模板渲染结果

## 📊 统计与监控

### 统计指标

- **命中率**：缓存命中次数 / 总请求次数
- **热点键**：访问频率最高的缓存键
- **操作统计**：get、set、delete 操作次数

### 监控示例

```php
// 获取性能统计
$stats = kv_stats();
echo "命中率: {$stats['hit_rate']}\n";

// 获取热点键
$hotKeys = kv_hot_keys(10);
foreach ($hotKeys as $key => $count) {
    echo "热点键: {$key} ({$count} 次访问)\n";
}
```

## 🔥 热点键自动续期

### 工作原理

1. **统计访问频率**：记录每个键的访问次数
2. **识别热点键**：访问次数超过阈值的键被标记为热点
3. **自动续期**：热点键在访问时自动延长TTL
4. **限制最大值**：续期不会超过配置的最大TTL

### 配置示例

```php
'cache' => [
    'hot_key_auto_renewal' => true,     // 启用自动续期
    'hot_key_threshold' => 100,         // 热点阈值
    'hot_key_extend_ttl' => 7200,       // 延长2小时
    'hot_key_max_ttl' => 86400,         // 最大24小时
]
```

## 🗑️ 缓存失效策略

### 按前缀删除

```php
// 删除特定用户的所有缓存
kv_delete_prefix('user.profile', ['id' => 123]);

// 删除所有用户资料缓存
kv_delete_prefix('user.profile');

// 删除整个用户组的缓存
kv_delete_prefix('user');
```

### 版本控制失效

通过修改版本号使整个组的缓存失效：

```php
// 配置文件中修改版本号
'user' => [
    'version' => 'v2',  // 从 v1 升级到 v2
]
```

## 🚀 性能优化

### 批量操作优化

```php
// ❌ 避免循环调用单个操作
foreach ($userIds as $id) {
    $users[] = kv_get('user.profile', ['id' => $id]);
}

// ✅ 使用批量操作
$paramsList = array_map(function($id) {
    return ['id' => $id];
}, $userIds);
$users = kv_get_multi('user.profile', $paramsList, $callback);
```

### 配置优化

```php
// 生产环境优化配置
'cache' => [
    'enable_stats' => false,            // 禁用统计减少开销
    'hot_key_auto_renewal' => false,    // 禁用热点键检测
    'ttl_random_range' => 300,          // 添加TTL随机性避免雪崩
]
```

## 🛠️ 驱动支持

### Redis 驱动（推荐）

```php
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->select(1); // 选择数据库
    return $redis;
});
```

### Array 驱动（测试用）

```php
use Asfop\CacheKV\Drivers\ArrayDriver;

CacheKVFactory::configure(function() {
    return new ArrayDriver();
});
```

## 🔧 高级用法

### 自定义回调逻辑

```php
// 复杂的回调逻辑
$user = kv_get('user.profile', ['id' => 123], function() use ($userId) {
    // 1. 从主数据库查询
    $user = $this->primaryDb->getUser($userId);
    
    // 2. 如果主库没有，尝试从备库
    if (!$user) {
        $user = $this->secondaryDb->getUser($userId);
    }
    
    // 3. 数据处理
    if ($user) {
        $user['avatar_url'] = $this->generateAvatarUrl($user['avatar']);
        $user['permissions'] = $this->getUserPermissions($userId);
    }
    
    return $user;
}, 7200); // 自定义TTL
```

### 条件缓存

```php
// 根据条件决定是否缓存
$data = kv_get('api.response', ['endpoint' => $endpoint], function() use ($endpoint) {
    $response = $this->callExternalApi($endpoint);
    
    // 只缓存成功的响应
    if ($response['status'] === 'success') {
        return $response;
    }
    
    // 返回 null 不会被缓存
    return null;
});
```

## 🚨 错误处理

### 异常处理

```php
try {
    $data = kv_get('user.profile', ['id' => 123], function() {
        return getUserFromDatabase(123);
    });
} catch (CacheException $e) {
    // 处理缓存配置错误
    logger()->error('Cache error: ' . $e->getMessage());
    $data = getUserFromDatabase(123); // 降级处理
}
```

### 降级策略

```php
// 缓存服务不可用时的降级处理
function getUserWithFallback($userId) {
    try {
        return kv_get('user.profile', ['id' => $userId], function() use ($userId) {
            return getUserFromDatabase($userId);
        });
    } catch (Exception $e) {
        // 缓存服务异常，直接查询数据库
        logger()->warning('Cache service unavailable, fallback to database');
        return getUserFromDatabase($userId);
    }
}
```

## 📚 相关文档

- **[快速开始](QUICK_START.md)** - 5分钟快速上手指南
- **[配置参考](CONFIG.md)** - 所有配置选项的详细说明
- **[API 参考](API.md)** - 完整的API文档
- **[统计功能](STATS.md)** - 性能监控和热点键管理

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request 来改进 CacheKV。

## 📄 许可证

MIT License - 详见 [LICENSE](../LICENSE) 文件
