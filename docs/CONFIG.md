# 配置参考

本文档详细说明了CacheKV的所有配置选项。

## 配置文件结构

```php
<?php
return array(
    'cache' => array(
        // 全局缓存配置
    ),
    'key_manager' => array(
        // 键管理配置
    ),
);
```

## 全局缓存配置 (`cache`)

### 基础缓存配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `ttl` | `int` | `3600` | 默认缓存时间（秒） |
| `null_cache_ttl` | `int` | `300` | 空值缓存时间（秒） |
| `enable_null_cache` | `bool` | `true` | 是否启用空值缓存 |
| `ttl_random_range` | `int` | `300` | TTL随机范围（秒），用于避免缓存雪崩 |

```php
'cache' => array(
    'ttl' => 3600,                      // 默认缓存1小时
    'null_cache_ttl' => 300,            // 空值缓存5分钟
    'enable_null_cache' => true,        // 启用空值缓存
    'ttl_random_range' => 300,          // TTL随机±5分钟
),
```

### 统计配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `enable_stats` | `bool` | `true` | 是否启用统计功能 |

```php
'cache' => array(
    'enable_stats' => true,             // 启用统计（推荐）
),
```

### 热点键自动续期配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `hot_key_auto_renewal` | `bool` | `true` | 是否启用热点键自动续期 |
| `hot_key_threshold` | `int` | `100` | 热点键阈值（访问次数） |
| `hot_key_extend_ttl` | `int` | `7200` | 热点键延长TTL（秒） |
| `hot_key_max_ttl` | `int` | `86400` | 热点键最大TTL（秒） |

```php
'cache' => array(
    'hot_key_auto_renewal' => true,     // 启用热点键自动续期
    'hot_key_threshold' => 100,         // 访问100次算热点
    'hot_key_extend_ttl' => 7200,       // 热点时延长到2小时
    'hot_key_max_ttl' => 86400,         // 最大24小时
),
```

### 标签配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `tag_prefix` | `string` | `'tag:'` | 标签前缀 |

```php
'cache' => array(
    'tag_prefix' => 'tag:',             // 标签前缀
),
```

## 键管理配置 (`key_manager`)

### 基础配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `app_prefix` | `string` | `'app'` | 应用前缀，用于区分不同应用 |
| `separator` | `string` | `':'` | 键分隔符 |

```php
'key_manager' => array(
    'app_prefix' => 'myapp',            // 应用前缀
    'separator' => ':',                 // 分隔符
),
```

### 分组配置 (`groups`)

每个分组代表一类相关的缓存键。

```php
'key_manager' => array(
    'groups' => array(
        'user' => array(                // 分组名
            'prefix' => 'user',         // 分组前缀
            'version' => 'v1',          // 分组版本
            'description' => '用户相关数据缓存',
            
            // 组级缓存配置（可选，覆盖全局配置）
            'cache' => array(
                'ttl' => 7200,          // 该组默认缓存2小时
                'hot_key_threshold' => 50,
            ),
            
            // 键定义
            'keys' => array(
                'kv' => array(          // KV类型的键
                    'profile' => array(
                        'template' => 'profile:{id}',
                        'description' => '用户基础资料',
                        // 键级缓存配置（可选，最高优先级）
                        'cache' => array(
                            'ttl' => 10800,    // 用户资料缓存3小时
                        )
                    ),
                ),
                'other' => array(       // 其他类型的键
                    'session' => array(
                        'template' => 'session:{token}',
                        'description' => '用户会话',
                    ),
                ),
            ),
        ),
    ),
),
```

#### 分组配置项

| 配置项 | 类型 | 必需 | 说明 |
|--------|------|------|------|
| `prefix` | `string` | 是 | 分组前缀 |
| `version` | `string` | 是 | 分组版本 |
| `description` | `string` | 否 | 分组描述 |
| `cache` | `array` | 否 | 组级缓存配置 |
| `keys` | `array` | 是 | 键定义 |

#### 键定义

键分为两种类型：

1. **KV类型** (`kv`)：会应用缓存配置的键
2. **其他类型** (`other`)：仅用于键生成的键

```php
'keys' => array(
    'kv' => array(
        'profile' => array(
            'template' => 'profile:{id}',           // 键模板
            'description' => '用户基础资料',         // 描述
            'cache' => array(                       // 键级缓存配置
                'ttl' => 10800,
                'hot_key_threshold' => 30,
            )
        ),
    ),
    'other' => array(
        'session' => array(
            'template' => 'session:{token}',        // 键模板
            'description' => '用户会话标识',         // 描述
        ),
    ),
),
```

## 配置继承和优先级

CacheKV使用三级配置继承：

```
全局配置 (cache)
    ↓ 继承
组级配置 (groups.{group}.cache)
    ↓ 继承
键级配置 (groups.{group}.keys.kv.{key}.cache)
```

### 示例

```php
return array(
    'cache' => array(
        'ttl' => 3600,                  // 全局：1小时
        'hot_key_threshold' => 100,     // 全局：100次
    ),
    'key_manager' => array(
        'groups' => array(
            'user' => array(
                'cache' => array(
                    'ttl' => 7200,              // 组级：2小时（覆盖全局）
                    'hot_key_threshold' => 50,  // 组级：50次（覆盖全局）
                ),
                'keys' => array(
                    'kv' => array(
                        'profile' => array(
                            'cache' => array(
                                'ttl' => 10800,        // 键级：3小时（最高优先级）
                                // hot_key_threshold 继承组级：50次
                            )
                        ),
                        'settings' => array(
                            // 继承组级配置：ttl=7200, hot_key_threshold=50
                        ),
                    ),
                ),
            ),
        ),
    ),
);
```

**最终配置结果：**
- `user.profile`: TTL=10800秒, 热点阈值=50次
- `user.settings`: TTL=7200秒, 热点阈值=50次

## 环境配置

### 开发环境

```php
return array(
    'cache' => array(
        'ttl' => 300,                   // 开发环境短缓存
        'enable_stats' => true,         // 启用统计便于调试
        'hot_key_auto_renewal' => false, // 关闭自动续期
    ),
    'key_manager' => array(
        'app_prefix' => 'dev_myapp',    // 开发环境前缀
    ),
);
```

### 生产环境

```php
return array(
    'cache' => array(
        'ttl' => 3600,                  // 生产环境长缓存
        'enable_stats' => true,         // 启用统计监控性能
        'hot_key_auto_renewal' => true, // 启用自动续期
        'hot_key_threshold' => 1000,    // 生产环境更高阈值
    ),
    'key_manager' => array(
        'app_prefix' => 'prod_myapp',   // 生产环境前缀
    ),
);
```

### 测试环境

```php
return array(
    'cache' => array(
        'ttl' => 60,                    // 测试环境极短缓存
        'enable_stats' => false,        // 关闭统计减少干扰
        'hot_key_auto_renewal' => false,
    ),
    'key_manager' => array(
        'app_prefix' => 'test_myapp',   // 测试环境前缀
    ),
);
```

## 配置验证

CacheKV会在启动时验证配置的正确性：

### 必需配置项

- `key_manager.groups` 必须存在且不为空
- 每个分组必须有 `prefix` 和 `version`
- 每个分组必须有 `keys` 定义

### 配置错误示例

```php
// ❌ 错误：缺少分组配置
'key_manager' => array(
    'app_prefix' => 'myapp',
    // 缺少 groups 配置
),

// ❌ 错误：分组配置不完整
'key_manager' => array(
    'groups' => array(
        'user' => array(
            'prefix' => 'user',
            // 缺少 version 和 keys
        ),
    ),
),

// ❌ 错误：无效的TTL值
'cache' => array(
    'ttl' => -1,                    // TTL不能为负数
),
```

## 最佳实践

### 1. 合理的分组设计

```php
'groups' => array(
    'user' => array(/* 用户相关 */),
    'product' => array(/* 商品相关 */),
    'order' => array(/* 订单相关 */),
    'system' => array(/* 系统配置 */),
),
```

### 2. 版本管理

```php
'user' => array(
    'version' => 'v2',              // 数据结构变更时升级版本
),
```

### 3. 环境隔离

```php
'app_prefix' => $_ENV['APP_ENV'] . '_myapp',  // dev_myapp, prod_myapp
```

### 4. 合理的TTL设置

```php
'cache' => array(
    'ttl' => 3600,                  // 默认1小时
),
'groups' => array(
    'user' => array(
        'cache' => array(
            'ttl' => 7200,          // 用户数据2小时
        ),
        'keys' => array(
            'kv' => array(
                'session' => array(
                    'cache' => array(
                        'ttl' => 1800,      // 会话30分钟
                    )
                ),
                'profile' => array(
                    'cache' => array(
                        'ttl' => 14400,     // 用户资料4小时
                    )
                ),
            ),
        ),
    ),
),
```
