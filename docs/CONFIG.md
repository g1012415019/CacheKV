# 配置参考

CacheKV 的完整配置选项说明。

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

## 缓存配置 (`cache`)

### 基础配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `ttl` | `int` | `3600` | 默认缓存时间（秒） |
| `enable_stats` | `bool` | `true` | 是否启用统计功能 |

### 热点键配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `hot_key_auto_renewal` | `bool` | `true` | 是否启用热点键自动续期 |
| `hot_key_threshold` | `int` | `100` | 热点键阈值（访问次数） |
| `hot_key_extend_ttl` | `int` | `7200` | 热点键延长TTL（秒） |
| `hot_key_max_ttl` | `int` | `86400` | 热点键最大TTL（秒） |

### 高级配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `null_cache_ttl` | `int` | `300` | 空值缓存时间（秒） |
| `enable_null_cache` | `bool` | `true` | 是否启用空值缓存 |
| `ttl_random_range` | `int` | `300` | TTL随机范围（秒） |

## 键管理配置 (`key_manager`)

### 基础配置

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `app_prefix` | `string` | `'app'` | 应用前缀 |
| `separator` | `string` | `':'` | 键分隔符 |

### 分组配置

```php
'key_manager' => array(
    'groups' => array(
        'user' => array(                // 分组名
            'prefix' => 'user',         // 分组前缀
            'version' => 'v1',          // 分组版本
            'description' => '用户数据', // 描述（可选）
            
            // 组级缓存配置（可选）
            'cache' => array(
                'ttl' => 7200,          // 覆盖全局TTL
            ),
            
            // 键定义
            'keys' => array(
                'kv' => array(          // KV类型的键
                    'profile' => array(
                        'template' => 'profile:{id}',
                        'description' => '用户资料',
                        // 键级缓存配置（可选）
                        'cache' => array(
                            'ttl' => 10800,
                        )
                    ),
                ),
            ),
        ),
    ),
),
```

## 配置继承

配置优先级：**键级配置 > 组级配置 > 全局配置**

```php
// 示例：最终 user.profile 的 TTL = 10800秒
'cache' => array('ttl' => 3600),                    // 全局：1小时
'groups' => array(
    'user' => array(
        'cache' => array('ttl' => 7200),            // 组级：2小时
        'keys' => array(
            'kv' => array(
                'profile' => array(
                    'cache' => array('ttl' => 10800) // 键级：3小时（最终值）
                )
            )
        )
    )
)
```

## 环境配置示例

### 开发环境

```php
return array(
    'cache' => array(
        'ttl' => 300,                       // 短缓存便于调试
        'enable_stats' => true,
        'hot_key_auto_renewal' => false,    // 关闭自动续期
    ),
    'key_manager' => array(
        'app_prefix' => 'dev_myapp',        // 环境隔离
    ),
);
```

### 生产环境

```php
return array(
    'cache' => array(
        'ttl' => 3600,                      // 长缓存
        'enable_stats' => true,
        'hot_key_auto_renewal' => true,     // 启用自动续期
        'hot_key_threshold' => 1000,        // 更高阈值
    ),
    'key_manager' => array(
        'app_prefix' => 'prod_myapp',
    ),
);
```

## 完整配置示例

```php
<?php
return array(
    'cache' => array(
        // 基础配置
        'ttl' => 3600,
        'enable_stats' => true,
        
        // 热点键配置
        'hot_key_auto_renewal' => true,
        'hot_key_threshold' => 100,
        'hot_key_extend_ttl' => 7200,
        'hot_key_max_ttl' => 86400,
        
        // 高级配置
        'null_cache_ttl' => 300,
        'enable_null_cache' => true,
        'ttl_random_range' => 300,
    ),
    
    'key_manager' => array(
        'app_prefix' => 'myapp',
        'separator' => ':',
        
        'groups' => array(
            'user' => array(
                'prefix' => 'user',
                'version' => 'v1',
                'description' => '用户相关数据',
                'cache' => array(
                    'ttl' => 7200,
                    'hot_key_threshold' => 50,
                ),
                'keys' => array(
                    'kv' => array(
                        'profile' => array(
                            'template' => 'profile:{id}',
                            'description' => '用户资料',
                            'cache' => array('ttl' => 10800)
                        ),
                        'settings' => array(
                            'template' => 'settings:{id}',
                            'description' => '用户设置'
                        ),
                    ),
                ),
            ),
            
            'product' => array(
                'prefix' => 'product',
                'version' => 'v1',
                'keys' => array(
                    'kv' => array(
                        'info' => array(
                            'template' => 'info:{id}',
                            'cache' => array('ttl' => 14400) // 4小时
                        ),
                    ),
                ),
            ),
        ),
    ),
);
```

## 配置验证

### 必需配置项

- `key_manager.groups` 必须存在
- 每个分组必须有 `prefix` 和 `version`
- 每个分组必须有 `keys` 定义

### 常见错误

```php
// ❌ 错误：缺少分组配置
'key_manager' => array(
    'app_prefix' => 'myapp',
    // 缺少 groups
),

// ❌ 错误：分组配置不完整
'groups' => array(
    'user' => array(
        'prefix' => 'user',
        // 缺少 version 和 keys
    ),
),
```

## 最佳实践

1. **环境隔离**：使用不同的 `app_prefix`
2. **版本管理**：数据结构变更时升级 `version`
3. **合理TTL**：根据数据更新频率设置TTL
4. **分组设计**：按业务模块划分分组
