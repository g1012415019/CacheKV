# 統計功能

CacheKV 提供了強大的統計功能，幫助你監控快取效能和識別熱點資料。

## 📊 統計功能概覽

- **命中率統計**：即時監控快取命中率
- **熱點鍵識別**：自動識別存取頻繁的快取鍵
- **操作計數**：統計各種快取操作的次數
- **效能監控**：提供詳細的效能指標

## 🔧 啟用統計功能

### 全域啟用

在設定檔中啟用統計功能：

```php
<?php
return [
    'cache' => [
        'enable_stats' => true,           // 啟用全域統計
        'stats_prefix' => 'cachekv:stats:', // 統計資料前綴
        'stats_ttl' => 604800,           // 統計資料TTL（7天）
    ]
];
```

### 按組啟用

可以為特定組啟用或停用統計：

```php
'groups' => [
    'user' => [
        'cache' => [
            'enable_stats' => true,      // 使用者組啟用統計
        ]
    ],
    'temp' => [
        'cache' => [
            'enable_stats' => false,     // 臨時資料不統計
        ]
    ]
]
```

## 📈 獲取統計資訊

### kv_stats() - 獲取全域統計

```php
$stats = kv_stats();
print_r($stats);
```

**回傳資料格式：**
```php
[
    'hits' => 1500,                    // 命中次數
    'misses' => 300,                   // 未命中次數
    'hit_rate' => '83.33%',            // 命中率
    'total_requests' => 1800,          // 總請求數
    'sets' => 350,                     // 設定操作次數
    'deletes' => 50                    // 刪除操作次數
]
```

### kv_hot_keys() - 獲取熱點鍵

```php
// 獲取前10個熱點鍵
$hotKeys = kv_hot_keys(10);

foreach ($hotKeys as $key => $count) {
    echo "熱點鍵: {$key} (存取 {$count} 次)\n";
}
```

**回傳資料格式：**
```php
[
    'app:user:v1:123' => 45,           // 鍵名 => 存取次數
    'app:user:v1:456' => 32,
    'app:product:v1:789' => 28,
    'app:config:v1:settings' => 20
]
```

### kv_clear_stats() - 清空統計資料

```php
$success = kv_clear_stats();
if ($success) {
    echo "統計資料已清空\n";
}
```

## 🔥 熱點鍵自動續期

CacheKV 可以自動識別熱點鍵並延長其快取時間。

### 設定熱點鍵自動續期

```php
'cache' => [
    'hot_key_auto_renewal' => true,     // 啟用熱點鍵自動續期
    'hot_key_threshold' => 100,         // 熱點鍵閾值（存取次數）
    'hot_key_extend_ttl' => 7200,       // 延長的TTL（2小時）
    'hot_key_max_ttl' => 86400,         // 最大TTL（24小時）
]
```

### 工作原理

1. **檢測熱點**：當鍵的存取次數超過閾值時，標記為熱點鍵
2. **自動續期**：熱點鍵在被存取時自動延長TTL
3. **限制最大值**：續期不會超過設定的最大TTL

## 📊 實際應用範例

### 效能監控腳本

```php
<?php
// 定期檢查快取效能
function checkCachePerformance() {
    $stats = kv_stats();
    
    echo "=== 快取效能報告 ===\n";
    echo "命中率: {$stats['hit_rate']}\n";
    echo "總請求: {$stats['total_requests']}\n";
    echo "命中次數: {$stats['hits']}\n";
    echo "未命中次數: {$stats['misses']}\n";
    
    // 檢查命中率是否過低
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    if ($hitRate < 80) {
        echo "⚠️ 警告: 命中率過低 ({$stats['hit_rate']})\n";
    }
    
    // 顯示熱點鍵
    echo "\n=== 熱點鍵 TOP 10 ===\n";
    $hotKeys = kv_hot_keys(10);
    foreach ($hotKeys as $key => $count) {
        echo "{$key}: {$count} 次\n";
        
        // 檢查是否有超熱點鍵
        if ($count > 1000) {
            echo "🔥 超熱點鍵: {$key}\n";
        }
    }
}

// 每小時執行一次
checkCachePerformance();
```

### 快取最佳化建議

```php
function getCacheOptimizationSuggestions() {
    $stats = kv_stats();
    $hotKeys = kv_hot_keys(20);
    $suggestions = [];
    
    // 分析命中率
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    if ($hitRate < 70) {
        $suggestions[] = "命中率過低({$stats['hit_rate']})，建議檢查快取策略";
    } elseif ($hitRate > 95) {
        $suggestions[] = "命中率很高({$stats['hit_rate']})，快取策略良好";
    }
    
    // 分析熱點鍵
    $superHotKeys = array_filter($hotKeys, function($count) {
        return $count > 500;
    });
    
    if (!empty($superHotKeys)) {
        $suggestions[] = "發現 " . count($superHotKeys) . " 個超熱點鍵，建議增加TTL";
    }
    
    // 分析請求量
    if ($stats['total_requests'] > 10000) {
        $suggestions[] = "請求量較大({$stats['total_requests']})，建議啟用熱點鍵自動續期";
    }
    
    return $suggestions;
}

// 獲取最佳化建議
$suggestions = getCacheOptimizationSuggestions();
foreach ($suggestions as $suggestion) {
    echo "💡 " . $suggestion . "\n";
}
```

## ⚙️ 統計設定選項

### 完整設定範例

```php
'cache' => [
    // 統計功能設定
    'enable_stats' => true,              // 是否啟用統計
    'stats_prefix' => 'cachekv:stats:',  // 統計資料鍵前綴
    'stats_ttl' => 604800,               // 統計資料TTL（7天）
    
    // 熱點鍵設定
    'hot_key_auto_renewal' => true,      // 啟用熱點鍵自動續期
    'hot_key_threshold' => 100,          // 熱點鍵閾值
    'hot_key_extend_ttl' => 7200,        // 熱點鍵延長TTL
    'hot_key_max_ttl' => 86400,          // 熱點鍵最大TTL
]
```

## 🚨 注意事項

1. **效能影響**：統計功能會有輕微的效能開銷，生產環境中可以選擇性啟用
2. **儲存空間**：統計資料會佔用額外的Redis儲存空間
3. **資料持久性**：統計資料有TTL，會定期清理
4. **熱點鍵檢測**：需要一定的存取量才能準確識別熱點鍵

## 🎯 最佳實務

1. **生產環境**：建議啟用統計功能進行效能監控
2. **開發環境**：可以停用統計功能提高效能
3. **定期清理**：定期清空統計資料避免資料過期
4. **監控告警**：設定命中率告警閾值
5. **熱點最佳化**：根據熱點鍵資料最佳化快取策略
