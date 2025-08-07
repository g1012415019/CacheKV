# 分组配置文件示例

这个目录演示了如何使用 CacheKV 的分组配置文件功能，避免多人开发时的配置冲突。

## 功能特点

- ✅ **避免冲突**：不同开发者配置自己的模块，不会产生合并冲突
- ✅ **模块化管理**：每个模块的缓存配置独立维护
- ✅ **自动加载**：无需修改主配置文件，自动扫描加载
- ✅ **API 简洁**：`CacheKVFactory::configure()` 的使用方式不变

## 目录结构

```
examples/
├── config/
│   ├── cache_kv.php          # 主配置文件（全局配置）
│   └── kvconf/               # 分组配置目录
│       ├── user.php          # 用户模块配置
│       ├── goods.php         # 商品模块配置
│       └── article.php       # 文章模块配置
└── separate_group_configs_example.php  # 使用示例
```

## 使用方式

### 1. 主配置文件

`config/cache_kv.php` 只包含全局配置：

```php
<?php
return array(
    'cache' => array(
        'ttl' => 3600,
        'enable_stats' => true,
        // ... 全局缓存配置
    ),
    'key_manager' => array(
        'app_prefix' => 'myapp',
        'separator' => ':',
        // groups 会自动从 kvconf/ 目录加载
    ),
);
```

### 2. 分组配置文件

每个模块创建自己的配置文件，文件名即为分组名：

**用户模块** `config/kvconf/user.php`：
```php
<?php
return array(
    'prefix' => 'user',
    'version' => 'v1',
    'keys' => array(
        'kv' => array(
            'profile' => array('template' => 'profile:{id}'),
            'settings' => array('template' => 'settings:{id}'),
        ),
    ),
);
```

**商品模块** `config/kvconf/goods.php`：
```php
<?php
return array(
    'prefix' => 'goods',
    'version' => 'v1',
    'keys' => array(
        'kv' => array(
            'info' => array('template' => 'info:{id}'),
            'price' => array('template' => 'price:{id}'),
        ),
    ),
);
```

### 3. 使用时 API 不变

```php
// 配置方式完全不变
CacheKVFactory::configure(
    function() {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    },
    '/path/to/config/cache_kv.php'  // 主配置文件路径
);

// 使用方式完全不变
$user = cache_kv_get('user.profile', ['id' => 123], $callback);
$goods = cache_kv_get('goods.info', ['id' => 456], $callback);
```

## 运行示例

```bash
cd examples/
php separate_group_configs_example.php
```

## 工作原理

1. **自动扫描**：ConfigManager 会自动扫描 `kvconf/` 目录
2. **文件加载**：加载所有 `.php` 文件作为分组配置
3. **配置合并**：将分组配置合并到主配置的 `key_manager.groups` 中
4. **文件名映射**：文件名（不含扩展名）即为分组名

## 团队协作场景

### 传统方式的问题

```php
// config/cache_kv.php - 所有人都要修改这个文件
return array(
    'key_manager' => array(
        'groups' => array(
            'user' => array(/* 用户模块配置 */),      // 用户模块开发者
            'goods' => array(/* 商品模块配置 */),     // 商品模块开发者
            'article' => array(/* 文章模块配置 */),   // 文章模块开发者
            'order' => array(/* 订单模块配置 */),     // 订单模块开发者
        ),
    ),
);
```

**问题：**
- ❌ 多人同时修改同一个文件
- ❌ 容易产生合并冲突
- ❌ 配置文件越来越大
- ❌ 难以进行模块化管理

### 新方式的优势

```
config/
├── cache_kv.php          # 只有架构师维护
└── kvconf/
    ├── user.php          # 用户模块开发者维护
    ├── goods.php         # 商品模块开发者维护
    ├── article.php       # 文章模块开发者维护
    └── order.php         # 订单模块开发者维护
```

**优势：**
- ✅ 每个开发者只维护自己的配置文件
- ✅ 不会产生合并冲突
- ✅ 配置文件模块化，易于管理
- ✅ 支持模块的独立版本控制

## 最佳实践

### 1. 目录结构建议

```
project/
├── config/
│   ├── cache_kv.php              # 主配置
│   └── kvconf/                   # 分组配置目录
│       ├── user.php
│       ├── goods.php
│       └── article.php
├── modules/                      # 模块目录
│   ├── user/
│   │   ├── UserController.php
│   │   └── cache_config.php      # 模块内的配置文件
│   └── goods/
│       ├── GoodsController.php
│       └── cache_config.php
└── scripts/
    └── sync_cache_configs.php    # 同步脚本
```

### 2. 配置同步脚本

```php
<?php
// scripts/sync_cache_configs.php
// 将模块内的配置文件同步到 kvconf/ 目录

$modules = ['user', 'goods', 'article'];
$kvconfDir = __DIR__ . '/../config/kvconf/';

foreach ($modules as $module) {
    $sourceFile = __DIR__ . "/../modules/{$module}/cache_config.php";
    $targetFile = $kvconfDir . "{$module}.php";
    
    if (file_exists($sourceFile)) {
        copy($sourceFile, $targetFile);
        echo "同步 {$module} 配置完成\n";
    }
}
```

### 3. 版本控制策略

```gitignore
# .gitignore
config/kvconf/          # 不提交分组配置目录
!config/kvconf/.gitkeep # 保留目录结构
```

每个模块在自己的目录中维护配置，通过构建脚本同步到 `kvconf/` 目录。

## 注意事项

1. **文件命名**：分组配置文件名必须与分组名一致
2. **目录位置**：`kvconf/` 目录必须与主配置文件在同一目录
3. **配置格式**：分组配置文件必须返回数组格式
4. **错误处理**：配置文件语法错误会抛出异常
5. **性能影响**：配置加载时会扫描目录，但只在初始化时执行一次
