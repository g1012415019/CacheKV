# 設定參考

CacheKV 的完整設定選項說明。

## 設定檔結構

### 方式一：單檔案設定（傳統方式）

```php
<?php
return array(
    'cache' => array(
        // 全域快取設定
    ),
    'key_manager' => array(
        'groups' => array(
            'user' => array(/* 使用者分組設定 */),
            'goods' => array(/* 商品分組設定 */),
            // ... 更多分組
        ),
    ),
);
```

### 方式二：分組設定檔（推薦）

**主設定檔** `config/cache_kv.php`：
```php
<?php
return array(
    'cache' => array(
        'ttl' => 3600,
        'enable_stats' => true,
        // ... 全域設定
    ),
    'key_manager' => array(
        'app_prefix' => 'myapp',
        'separator' => ':',
        'groups' => array(
            // 在這裡手動設定各個分組
            // 'user' => require __DIR__ . '/groups/user.php',
            // 'goods' => require __DIR__ . '/groups/goods.php',
        ),
    ),
);
```

## 快取設定 (`cache`)

### 基礎設定

| 設定項 | 類型 | 預設值 | 說明 |
|--------|------|--------|------|
| `ttl` | `int` | `3600` | 預設快取時間（秒） |
| `enable_stats` | `bool` | `true` | 是否啟用統計功能 |

### 熱點鍵設定

| 設定項 | 類型 | 預設值 | 說明 |
|--------|------|--------|------|
| `hot_key_auto_renewal` | `bool` | `true` | 是否啟用熱點鍵自動續期 |
| `hot_key_threshold` | `int` | `100` | 熱點鍵閾值（存取次數） |
| `hot_key_extend_ttl` | `int` | `7200` | 熱點鍵延長TTL（秒） |
| `hot_key_max_ttl` | `int` | `86400` | 熱點鍵最大TTL（秒） |

### 進階設定

| 設定項 | 類型 | 預設值 | 說明 |
|--------|------|--------|------|
| `null_cache_ttl` | `int` | `300` | 空值快取時間（秒） |
| `enable_null_cache` | `bool` | `true` | 是否啟用空值快取 |
| `ttl_random_range` | `int` | `300` | TTL隨機範圍（秒） |

## 鍵管理設定 (`key_manager`)

### 基礎設定

| 設定項 | 類型 | 預設值 | 說明 |
|--------|------|--------|------|
| `app_prefix` | `string` | `'app'` | 應用程式前綴 |
| `separator` | `string` | `':'` | 鍵分隔符號 |

### 分組設定

每個分組設定檔的結構：

```php
<?php
return array(
    'prefix' => 'group_name',           // 分組前綴
    'version' => 'v1',                  // 分組版本
    'description' => '分組描述',         // 描述（可選）
    
    // 組級快取設定（可選）
    'cache' => array(
        'ttl' => 7200,                  // 覆蓋全域TTL
    ),
    
    // 鍵定義 - 統一結構
    'keys' => array(
        'key_name' => array(
            'template' => 'template:{param}',
            'description' => '鍵描述',
            'cache' => array(           // 鍵級設定（可選）
                'ttl' => 10800,         // 有cache設定的鍵會應用快取邏輯
            )
        ),
        'other_key' => array(
            'template' => 'other:{param}',
            'description' => '其他鍵',
            // 沒有cache設定，僅用於鍵產生
        ),
    ),
);
```

## 設定繼承

設定優先順序：**鍵級設定 > 組級設定 > 全域設定**

```php
// 範例：最終 user.profile 的 TTL = 10800秒
'cache' => array('ttl' => 3600),                    // 全域：1小時
'groups' => array(
    'user' => array(
        'cache' => array('ttl' => 7200),            // 組級：2小時
        'keys' => array(
            'profile' => array(
                'cache' => array('ttl' => 10800)   // 鍵級：3小時（最終值）
            )
        )
    )
)
```

## 最佳實務

### 1. 分組設定檔命名

- 使用小寫字母和底線：`user.php`, `goods.php`, `user_order.php`
- 檔案名稱即為分組名稱，保持一致性

### 2. 模組化開發

```
project/
├── modules/
│   ├── user/
│   │   ├── UserController.php
│   │   └── config/user.php         # 使用者模組設定
│   └── goods/
│       ├── GoodsController.php
│       └── config/goods.php        # 商品模組設定
└── config/
    ├── cache_kv.php                # 主設定
    └── groups/                     # 分組設定目錄（可選）
        ├── user.php -> ../modules/user/config/user.php
        └── goods.php -> ../modules/goods/config/goods.php
```

### 3. 版本管理

- 每個分組獨立版本控制
- 資料結構變更時升級分組版本
- 主設定檔版本控制全域設定

### 4. 團隊協作

- 每個開發者負責自己模組的設定檔
- 主設定檔由架構師維護
- 減少設定檔的合併衝突
