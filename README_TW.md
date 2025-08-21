# CacheKV

CacheKV 是一個專注於簡化快取操作的 PHP 函式庫，**核心功能是實現「若無則從資料來源獲取並回填快取」這一常見模式**。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/asfop/cache-kv.svg)](https://packagist.org/packages/asfop/cache-kv)
[![Packagist Downloads](https://img.shields.io/packagist/dt/asfop/cache-kv.svg)](https://packagist.org/packages/asfop/cache-kv)
[![GitHub Stars](https://img.shields.io/github/stars/g1012415019/CacheKV.svg)](https://github.com/g1012415019/CacheKV/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/g1012415019/CacheKV.svg)](https://github.com/g1012415019/CacheKV/issues)

## 🎯 核心價值

**CacheKV 讓快取操作變得簡單：**
```php
// 一行程式碼搞定：檢查快取 → 未命中則獲取資料 → 自動回填快取
$data = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123); // 只在快取未命中時執行
});
```

**解決的痛點：**
- ❌ 手動檢查快取是否存在
- ❌ 快取未命中時手動從資料來源獲取
- ❌ 手動將獲取的資料寫入快取
- ❌ 批次操作時的複雜邏輯處理

## ⚡ 快速開始

### 安裝

```bash
composer require asfop/cache-kv
```

### 基礎使用

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

// 單個資料獲取
$user = kv_get('user.profile', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John', 'email' => 'john@example.com'];
});

// 批次資料獲取
$users = kv_get_multi('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
], function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = getUserFromDatabase($params['id']);
    }
    return $data; // 回傳關聯陣列
});

// 批次獲取鍵物件（不執行快取操作）
$keys = kv_get_keys('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
]);

// 檢查鍵設定
foreach ($keys as $keyString => $keyObj) {
    echo "鍵: {$keyString}, 有快取設定: " . ($keyObj->hasCacheConfig() ? '是' : '否') . "\n";
}
```

## 🚀 核心功能

- **自動回填快取**：快取未命中時自動執行回呼並快取結果
- **批次操作最佳化**：高效的批次獲取，避免N+1查詢問題
- **按前綴刪除**：支援按鍵前綴批次刪除快取，相當於按 tag 刪除
- **熱點鍵自動續期**：自動檢測並延長熱點資料的快取時間
- **統計監控**：即時統計命中率、熱點鍵等效能指標
- **統一鍵管理**：標準化鍵產生，支援環境隔離和版本管理

## 📊 統計功能

```php
// 獲取統計資訊
$stats = kv_stats();
// ['hits' => 1500, 'misses' => 300, 'hit_rate' => '83.33%', ...]

// 獲取熱點鍵
$hotKeys = kv_hot_keys(10);
// ['user:profile:123' => 45, 'user:profile:456' => 32, ...]

// 清空統計資料
kv_clear_stats();
```

## ✨ 簡潔API設計

CacheKV 提供了簡潔易用的函式API：

### 🔧 核心操作
```php
kv_get($template, $params, $callback, $ttl)      // 獲取快取
kv_get_multi($template, $paramsList, $callback)  // 批次獲取
```

### 🗝️ 鍵管理
```php
kv_key($template, $params)           // 建立鍵字串
kv_keys($template, $paramsList)      // 批次建立鍵
kv_get_keys($template, $paramsList)  // 獲取鍵物件
```

### 🗑️ 刪除操作
```php
kv_delete($template, $params)         // 刪除指定快取
kv_delete_prefix($template, $params)  // 按前綴刪除
kv_delete_full($prefix)               // 按完整前綴刪除
```

### 📊 統計功能
```php
kv_stats()              // 獲取統計資訊
kv_hot_keys($limit)     // 獲取熱點鍵
kv_clear_stats()        // 清空統計
```

### ⚙️ 設定管理
```php
kv_config()     // 獲取設定物件（可轉換為陣列）
```

## 📚 文件

- **[完整文件](docs/README_TW.md)** - 詳細的設定、架構和使用說明 ⭐
- **[快速開始](docs/QUICK_START_TW.md)** - 5分鐘快速上手指南
- **[設定參考](docs/CONFIG_TW.md)** - 所有設定選項的詳細說明
- **[統計功能](docs/STATS_TW.md)** - 效能監控和熱點鍵管理
- **[API 參考](docs/API_TW.md)** - 完整的API文件
- **[更新日誌](CHANGELOG.md)** - 版本更新記錄

## 🏆 適用場景

- **Web 應用程式** - 使用者資料、頁面內容快取
- **API 服務** - 介面回應、計算結果快取
- **電商平台** - 商品資訊、價格、庫存快取
- **資料分析** - 統計資料、報表快取

## 📋 系統需求

- PHP >= 7.0
- Redis 擴充套件

## 📄 授權條款

MIT License - 詳見 [LICENSE](LICENSE) 檔案

---

**開始您的高效快取之旅！** 🚀

> 💡 **提示：** 查看 [完整文件](docs/README_TW.md) 了解詳細的設定和進階用法
