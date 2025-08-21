# CacheKV 完整文件

CacheKV 是一個專注於簡化快取操作的 PHP 函式庫，核心功能是實現「若無則從資料來源獲取並回填快取」這一常見模式。

## 🎯 核心特性

- **自動回填快取**：快取未命中時自動執行回呼並快取結果
- **批次操作最佳化**：高效的批次獲取，避免N+1查詢問題
- **按前綴刪除**：支援按鍵前綴批次刪除快取，相當於按 tag 刪除
- **熱點鍵自動續期**：自動檢測並延長熱點資料的快取時間
- **統計監控**：即時統計命中率、熱點鍵等效能指標
- **統一鍵管理**：標準化鍵產生，支援環境隔離和版本管理

## 📦 安裝

```bash
composer require g1012415019/cache-kv
```

## ⚡ 快速開始

### 基礎設定

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// 設定Redis連線
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});
```

### 基礎使用

```php
// 單個資料獲取
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 批次資料獲取
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

## 🔧 輔助函式 API

CacheKV 提供了簡潔易用的輔助函式：

### 核心操作
- `kv_get($template, $params, $callback, $ttl)` - 獲取快取
- `kv_get_multi($template, $paramsList, $callback)` - 批次獲取

### 鍵管理
- `kv_key($template, $params)` - 建立鍵字串
- `kv_keys($template, $paramsList)` - 批次建立鍵
- `kv_get_keys($template, $paramsList)` - 獲取鍵物件

### 刪除操作
- `kv_delete_prefix($template, $params)` - 按前綴刪除
- `kv_delete_full($prefix)` - 按完整前綴刪除

### 統計功能
- `kv_stats()` - 獲取統計資訊
- `kv_hot_keys($limit)` - 獲取熱點鍵
- `kv_clear_stats()` - 清空統計

### 設定管理
- `kv_config()` - 獲取設定物件

## 🏗️ 架構設計

### 核心元件

1. **CacheKVFactory** - 工廠類別，負責初始化和設定
2. **CacheKV** - 核心快取操作類別
3. **KeyManager** - 鍵管理器，負責鍵的產生和管理
4. **DriverInterface** - 驅動程式介面，支援多種快取後端
5. **KeyStats** - 統計功能，監控快取效能

### 資料流程

```
使用者呼叫 kv_get()
    ↓
KeyManager 產生 CacheKey
    ↓
CacheKV 檢查快取
    ↓
快取命中 → 回傳資料
    ↓
快取未命中 → 執行回呼 → 快取結果 → 回傳資料
```

## ⚙️ 設定系統

### 設定檔結構

```php
<?php
return [
    // 全域快取設定
    'cache' => [
        'ttl' => 3600,                    // 預設TTL
        'enable_stats' => true,           // 啟用統計
        'hot_key_auto_renewal' => true,   // 熱點鍵自動續期
    ],
    
    // 鍵管理設定
    'key_manager' => [
        'app_prefix' => 'app',            // 應用程式前綴
        'separator' => ':',               // 分隔符號
        'groups' => [
            'user' => [
                'prefix' => 'user',
                'version' => 'v1',
                'cache' => [
                    'ttl' => 7200,        // 群組級TTL覆蓋
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

### 設定優先順序

1. 函式參數（最高優先順序）
2. 鍵級設定
3. 群組級設定
4. 全域設定（最低優先順序）

## 🔑 鍵管理系統

### 鍵範本格式

鍵範本使用 `group.key` 格式：

```php
// 範本格式：'group.key'
kv_get('user.profile', ['id' => 123]);
// 產生鍵：app:user:v1:123

kv_get('product.info', ['id' => 456, 'lang' => 'en']);
// 產生鍵：app:product:v1:456:en
```

### 鍵產生規則

完整的鍵格式：`{app_prefix}:{group_prefix}:{version}:{template_result}`

- `app_prefix`: 應用程式前綴，用於環境隔離
- `group_prefix`: 群組前綴，用於分類管理
- `version`: 版本號，用於快取版本控制
- `template_result`: 範本渲染結果

## 📊 統計與監控

### 統計指標

- **命中率**：快取命中次數 / 總請求次數
- **熱點鍵**：存取頻率最高的快取鍵
- **操作統計**：get、set、delete 操作次數

### 監控範例

```php
// 獲取效能統計
$stats = kv_stats();
echo "命中率: {$stats['hit_rate']}\n";

// 獲取熱點鍵
$hotKeys = kv_hot_keys(10);
foreach ($hotKeys as $key => $count) {
    echo "熱點鍵: {$key} ({$count} 次存取)\n";
}
```

## 🔥 熱點鍵自動續期

### 工作原理

1. **統計存取頻率**：記錄每個鍵的存取次數
2. **識別熱點鍵**：存取次數超過閾值的鍵被標記為熱點
3. **自動續期**：熱點鍵在存取時自動延長TTL
4. **限制最大值**：續期不會超過設定的最大TTL

### 設定範例

```php
'cache' => [
    'hot_key_auto_renewal' => true,     // 啟用自動續期
    'hot_key_threshold' => 100,         // 熱點閾值
    'hot_key_extend_ttl' => 7200,       // 延長2小時
    'hot_key_max_ttl' => 86400,         // 最大24小時
]
```

## 🗑️ 快取失效策略

### 按前綴刪除

```php
// 刪除特定使用者的所有快取
kv_delete_prefix('user.profile', ['id' => 123]);

// 刪除所有使用者資料快取
kv_delete_prefix('user.profile');

// 刪除整個使用者群組的快取
kv_delete_prefix('user');
```

### 版本控制失效

透過修改版本號使整個群組的快取失效：

```php
// 設定檔中修改版本號
'user' => [
    'version' => 'v2',  // 從 v1 升級到 v2
]
```

## 🚀 效能最佳化

### 批次操作最佳化

```php
// ❌ 避免迴圈呼叫單個操作
foreach ($userIds as $id) {
    $users[] = kv_get('user.profile', ['id' => $id]);
}

// ✅ 使用批次操作
$paramsList = array_map(function($id) {
    return ['id' => $id];
}, $userIds);
$users = kv_get_multi('user.profile', $paramsList, $callback);
```

### 設定最佳化

```php
// 生產環境最佳化設定
'cache' => [
    'enable_stats' => false,            // 停用統計減少開銷
    'hot_key_auto_renewal' => false,    // 停用熱點鍵檢測
    'ttl_random_range' => 300,          // 新增TTL隨機性避免雪崩
]
```

## 🛠️ 驅動程式支援

### Redis 驅動程式（推薦）

```php
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->select(1); // 選擇資料庫
    return $redis;
});
```

### Array 驅動程式（測試用）

```php
use Asfop\CacheKV\Drivers\ArrayDriver;

CacheKVFactory::configure(function() {
    return new ArrayDriver();
});
```

## 🔧 進階用法

### 自訂回呼邏輯

```php
// 複雜的回呼邏輯
$user = kv_get('user.profile', ['id' => 123], function() use ($userId) {
    // 1. 從主資料庫查詢
    $user = $this->primaryDb->getUser($userId);
    
    // 2. 如果主庫沒有，嘗試從備庫
    if (!$user) {
        $user = $this->secondaryDb->getUser($userId);
    }
    
    // 3. 資料處理
    if ($user) {
        $user['avatar_url'] = $this->generateAvatarUrl($user['avatar']);
        $user['permissions'] = $this->getUserPermissions($userId);
    }
    
    return $user;
}, 7200); // 自訂TTL
```

### 條件快取

```php
// 根據條件決定是否快取
$data = kv_get('api.response', ['endpoint' => $endpoint], function() use ($endpoint) {
    $response = $this->callExternalApi($endpoint);
    
    // 只快取成功的回應
    if ($response['status'] === 'success') {
        return $response;
    }
    
    // 回傳 null 不會被快取
    return null;
});
```

## 🚨 錯誤處理

### 例外處理

```php
try {
    $data = kv_get('user.profile', ['id' => 123], function() {
        return getUserFromDatabase(123);
    });
} catch (CacheException $e) {
    // 處理快取設定錯誤
    logger()->error('Cache error: ' . $e->getMessage());
    $data = getUserFromDatabase(123); // 降級處理
}
```

### 降級策略

```php
// 快取服務不可用時的降級處理
function getUserWithFallback($userId) {
    try {
        return kv_get('user.profile', ['id' => $userId], function() use ($userId) {
            return getUserFromDatabase($userId);
        });
    } catch (Exception $e) {
        // 快取服務異常，直接查詢資料庫
        logger()->warning('Cache service unavailable, fallback to database');
        return getUserFromDatabase($userId);
    }
}
```

## 📚 相關文件

- **[快速開始](QUICK_START_TW.md)** - 5分鐘快速上手指南
- **[設定參考](CONFIG_TW.md)** - 所有設定選項的詳細說明
- **[API 參考](API_TW.md)** - 完整的API文件
- **[統計功能](STATS_TW.md)** - 效能監控和熱點鍵管理

## 🤝 貢獻指南

歡迎提交 Issue 和 Pull Request 來改進 CacheKV。

## 📄 授權條款

MIT License - 詳見 [LICENSE](../LICENSE) 檔案
