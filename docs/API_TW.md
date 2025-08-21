# API 參考文件

本文件詳細介紹 CacheKV 的所有 API 介面。

## 🔧 核心操作函式

### kv_get()

獲取快取資料，支援回呼自動回填。

```php
function kv_get($template, array $params = [], $callback = null, $ttl = null)
```

**參數：**
- `$template` (string): 鍵範本，格式：'group.key'
- `$params` (array): 參數陣列，用於替換範本中的佔位符
- `$callback` (callable|null): 快取未命中時的回呼函式
- `$ttl` (int|null): 自訂TTL（秒），覆蓋設定中的預設值

**回傳值：**
- `mixed`: 快取資料或回呼函式的回傳值

**範例：**
```php
// 基礎用法
$user = kv_get('user.profile', ['id' => 123]);

// 帶回呼的用法
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// 自訂TTL
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 7200); // 2小時
```

### kv_get_multi()

批次獲取快取資料，支援批次回呼。

```php
function kv_get_multi($template, array $paramsList, $callback = null)
```

**參數：**
- `$template` (string): 鍵範本，格式：'group.key'
- `$paramsList` (array): 參數陣列清單
- `$callback` (callable|null): 批次回呼函式

**回呼函式簽名：**
```php
function($missedKeys) {
    // $missedKeys 是 CacheKey 物件陣列
    // 必須回傳關聯陣列：['key_string' => 'data', ...]
}
```

**回傳值：**
- `array`: 結果陣列，鍵為快取鍵字串，值為快取資料

**範例：**
```php
// 批次獲取使用者資訊
$users = kv_get_multi('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
], function($missedKeys) {
    $results = [];
    foreach ($missedKeys as $cacheKey) {
        $params = $cacheKey->getParams();
        $userId = $params['id'];
        $results[(string)$cacheKey] = getUserFromDatabase($userId);
    }
    return $results;
});
```

## 🗝️ 鍵管理函式

### kv_key()

產生單個快取鍵字串。

```php
function kv_key($template, array $params = [])
```

**參數：**
- `$template` (string): 鍵範本，格式：'group.key'
- `$params` (array): 參數陣列

**回傳值：**
- `string`: 產生的快取鍵字串

**範例：**
```php
$key = kv_key('user.profile', ['id' => 123]);
// 回傳: "app:user:v1:123"
```

### kv_keys()

批次產生快取鍵字串。

```php
function kv_keys($template, array $paramsList)
```

**參數：**
- `$template` (string): 鍵範本，格式：'group.key'
- `$paramsList` (array): 參數陣列清單

**回傳值：**
- `array`: 鍵字串陣列

**範例：**
```php
$keys = kv_keys('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
]);
// 回傳: ["app:user:v1:1", "app:user:v1:2", "app:user:v1:3"]
```

## 🗑️ 刪除操作函式

### kv_delete_prefix()

按前綴刪除快取，相當於按 tag 刪除。

```php
function kv_delete_prefix($template, array $params = [])
```

**參數：**
- `$template` (string): 鍵範本，格式：'group.key'
- `$params` (array): 參數陣列（可選）

**回傳值：**
- `int`: 刪除的鍵數量

**範例：**
```php
// 刪除特定使用者的所有快取
$deleted = kv_delete_prefix('user.profile', ['id' => 123]);

// 刪除所有使用者資料快取
$deleted = kv_delete_prefix('user.profile');

// 刪除整個使用者群組的快取
$deleted = kv_delete_prefix('user');
```

## 📊 統計功能函式

### kv_stats()

獲取全域統計資訊。

```php
function kv_stats()
```

**回傳值：**
- `array`: 統計資訊陣列

**範例：**
```php
$stats = kv_stats();
print_r($stats);

// 輸出範例：
// [
//     'hits' => 1500,
//     'misses' => 300,
//     'hit_rate' => '83.33%',
//     'total_requests' => 1800,
//     'sets' => 350,
//     'deletes' => 50
// ]
```

### kv_hot_keys()

獲取熱點鍵清單。

```php
function kv_hot_keys($limit = 10)
```

**參數：**
- `$limit` (int): 回傳的熱點鍵數量限制，預設10個

**回傳值：**
- `array`: 熱點鍵陣列，鍵為快取鍵，值為存取次數

**範例：**
```php
$hotKeys = kv_hot_keys(5);
print_r($hotKeys);

// 輸出範例：
// [
//     'app:user:v1:123' => 45,
//     'app:user:v1:456' => 32,
//     'app:product:v1:789' => 28,
//     'app:user:v1:101' => 25,
//     'app:config:v1:settings' => 20
// ]
```

### kv_clear_stats()

清空統計資料。

```php
function kv_clear_stats()
```

**回傳值：**
- `bool`: 是否成功清空

**範例：**
```php
$success = kv_clear_stats();
if ($success) {
    echo "統計資料已清空\n";
}
```

## ⚙️ 設定管理函式

### kv_config()

獲取完整的設定物件。

```php
function kv_config()
```

**回傳值：**
- `CacheKVConfig`: 設定物件，可轉換為陣列

**範例：**
```php
$config = kv_config();

// 轉換為陣列查看
$configArray = $config->toArray();
print_r($configArray);

// 獲取特定設定
$cacheConfig = $config->getCacheConfig();
$keyManagerConfig = $config->getKeyManagerConfig();
```

## 🚨 錯誤處理

所有函式都會妥善處理錯誤情況：

- **設定錯誤**：拋出 `CacheException`
- **網路錯誤**：Redis連線失敗時回傳預設值
- **序列化錯誤**：自動降級處理
- **回呼錯誤**：記錄日誌但不影響主流程

**最佳實務：**
```php
try {
    $data = kv_get('user.profile', ['id' => 123], function() {
        return fetchUserFromDatabase(123);
    });
} catch (CacheException $e) {
    // 處理設定錯誤
    logError("Cache configuration error: " . $e->getMessage());
    $data = fetchUserFromDatabase(123); // 降級到直接查詢
}
```

## 📝 注意事項

1. **範本格式**：必須使用 'group.key' 格式
2. **參數命名**：參數名稱必須與範本中的佔位符匹配
3. **回呼回傳值**：批次回呼必須回傳關聯陣列
4. **鍵字串**：產生的鍵會包含應用程式前綴、群組前綴和版本號
5. **TTL優先順序**：函式參數 > 鍵級設定 > 組級設定 > 全域設定
