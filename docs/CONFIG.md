# 配置参考

CacheKV 的完整配置选项说明。

## 配置文件结构

### 方式一：单文件配置（传统方式）

```php
<?php
return array(
    'cache' => array(
        // 全局缓存配置
    ),
    'key_manager' => array(
        'groups' => array(
            'user' => array(/* 用户分组配置 */),
            'goods' => array(/* 商品分组配置 */),
            // ... 更多分组
        ),
    ),
);
```

### 方式二：分组配置文件（推荐）

**主配置文件** `config/cache_kv.php`：
```php
<?php
return array(
    'cache' => array(
        'ttl' => 3600,
        'enable_stats' => true,
        // ... 全局配置
    ),
    'key_manager' => array(
        'app_prefix' => 'myapp',
        'separator' => ':',
        // groups 会自动从 kvconf/ 目录加载
    ),
);
```

**分组配置目录** `config/kvconf/`：
```
config/
├── cache_kv.php          # 主配置文件
└── kvconf/               # 分组配置目录
    ├── user.php          # 用户模块配置
    ├── goods.php         # 商品模块配置
    └── article.php       # 文章模块配置
```

**分组配置文件示例** `config/kvconf/user.php`：
```php
<?php
return array(
    'prefix' => 'user',
    'version' => 'v1',
    'description' => '用户相关数据缓存',
    
    'cache' => array(
        'ttl' => 7200,                      // 用户数据缓存2小时
        'hot_key_threshold' => 50,
    ),
    
    // 键定义 - 统一结构，不区分类型
    'keys' => array(
        'profile' => array(
            'template' => 'profile:{id}',
            'description' => '用户资料',
            'cache' => array('ttl' => 10800)    // 有cache配置的键会应用缓存逻辑
        ),
        'settings' => array(
            'template' => 'settings:{id}',
            'description' => '用户设置'
            // 继承组级缓存配置
        ),
        'session' => array(
            'template' => 'session:{token}',
            'description' => '用户会话标识'
            // 没有cache配置，仅用于键生成
        ),
    ),
);
```

### 分组配置文件的优势

1. **避免冲突**：不同开发者配置自己的模块，不会冲突
2. **模块化管理**：每个模块的配置独立维护
3. **版本控制友好**：减少合并冲突
4. **自动加载**：无需修改主配置文件

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

每个分组配置文件的结构：

```php
<?php
return array(
    'prefix' => 'group_name',           // 分组前缀
    'version' => 'v1',                  // 分组版本
    'description' => '分组描述',         // 描述（可选）
    
    // 组级缓存配置（可选）
    'cache' => array(
        'ttl' => 7200,                  // 覆盖全局TTL
    ),
    
    // 键定义 - 统一结构
    'keys' => array(
        'key_name' => array(
            'template' => 'template:{param}',
            'description' => '键描述',
            'cache' => array(           // 键级配置（可选）
                'ttl' => 10800,         // 有cache配置的键会应用缓存逻辑
            )
        ),
        'other_key' => array(
            'template' => 'other:{param}',
            'description' => '其他键',
            // 没有cache配置，仅用于键生成
        ),
    ),
);
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
            'profile' => array(
                'cache' => array('ttl' => 10800)   // 键级：3小时（最终值）
            )
        )
    )
)
```

## 使用示例

### 创建分组配置

**1. 用户模块开发者创建** `config/kvconf/user.php`：
```php
<?php
return array(
    'prefix' => 'user',
    'version' => 'v1',
    'keys' => array(
        'profile' => array('template' => 'profile:{id}'),
        'settings' => array('template' => 'settings:{id}'),
    ),
);
```

**2. 商品模块开发者创建** `config/kvconf/goods.php`：
```php
<?php
return array(
    'prefix' => 'goods',
    'version' => 'v1',
    'keys' => array(
        'info' => array('template' => 'info:{id}'),
        'price' => array('template' => 'price:{id}'),
    ),
);
```

**3. 使用时无需修改主配置**：
```php
// API 使用方式完全不变
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    '/path/to/config/cache_kv.php'  // 主配置文件路径
);

// 自动加载所有分组配置
$user = cache_kv_get('user.profile', ['id' => 123], $callback);
$goods = cache_kv_get('goods.info', ['id' => 456], $callback);
```

## 环境配置示例

### 开发环境

**主配置** `config/cache_kv.php`：
```php
return array(
    'cache' => array(
        'ttl' => 300,                       // 短缓存便于调试
        'enable_stats' => true,
        'hot_key_auto_renewal' => false,
    ),
    'key_manager' => array(
        'app_prefix' => 'dev_myapp',        // 环境隔离
    ),
);
```

**分组配置保持不变**，环境差异通过主配置控制。

### 生产环境

**主配置** `config/cache_kv.php`：
```php
return array(
    'cache' => array(
        'ttl' => 3600,                      // 长缓存
        'enable_stats' => true,
        'hot_key_auto_renewal' => true,
        'hot_key_threshold' => 1000,        // 更高阈值
    ),
    'key_manager' => array(
        'app_prefix' => 'prod_myapp',
    ),
);
```

## 最佳实践

### 1. 分组配置文件命名

- 使用小写字母和下划线：`user.php`, `goods.php`, `user_order.php`
- 文件名即为分组名，保持一致性

### 2. 模块化开发

```
project/
├── modules/
│   ├── user/
│   │   ├── UserController.php
│   │   └── config/user.php         # 用户模块配置
│   └── goods/
│       ├── GoodsController.php
│       └── config/goods.php        # 商品模块配置
└── config/
    ├── cache_kv.php                # 主配置
    └── kvconf/                     # 分组配置目录
        ├── user.php -> ../modules/user/config/user.php
        └── goods.php -> ../modules/goods/config/goods.php
```

### 3. 版本管理

- 每个分组独立版本控制
- 数据结构变更时升级分组版本
- 主配置文件版本控制全局配置

### 4. 团队协作

- 每个开发者负责自己模块的配置文件
- 主配置文件由架构师维护
- 减少配置文件的合并冲突
