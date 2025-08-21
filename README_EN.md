# CacheKV

CacheKV is a PHP library focused on simplifying cache operations. **Its core functionality is implementing the common pattern of "fetch from data source and backfill cache if not exists"**.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/asfop/cache-kv.svg)](https://packagist.org/packages/asfop/cache-kv)
[![Packagist Downloads](https://img.shields.io/packagist/dt/asfop/cache-kv.svg)](https://packagist.org/packages/asfop/cache-kv)
[![GitHub Stars](https://img.shields.io/github/stars/g1012415019/CacheKV.svg)](https://github.com/g1012415019/CacheKV/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/g1012415019/CacheKV.svg)](https://github.com/g1012415019/CacheKV/issues)

## 🎯 Core Value

**CacheKV makes cache operations simple:**
```php
// One line of code: Check cache → Fetch data if miss → Auto backfill cache
$data = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123); // Only executed on cache miss
});
```

**Pain points solved:**
- ❌ Manual cache existence checking
- ❌ Manual data fetching from data source on cache miss
- ❌ Manual cache writing of fetched data
- ❌ Complex logic handling for batch operations

## ⚡ Quick Start

### Installation

```bash
composer require asfop/cache-kv
```

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use Asfop\CacheKV\Core\CacheKVFactory;

// Configure Redis connection
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
});

// Single data retrieval
$user = kv_get('user.profile', ['id' => 123], function() {
    return ['id' => 123, 'name' => 'John', 'email' => 'john@example.com'];
});

// Batch data retrieval
$users = kv_get_multi('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
], function($missedKeys) {
    $data = [];
    foreach ($missedKeys as $cacheKey) {
        $keyString = (string)$cacheKey;
        $params = $cacheKey->getParams();
        $data[$keyString] = getUserFromDatabase($params['id']);
    }
    return $data; // Return associative array
});

// Batch get key objects (no cache operations)
$keys = kv_get_keys('user.profile', [
    ['id' => 1], ['id' => 2], ['id' => 3]
]);

// Check key configuration
foreach ($keys as $keyString => $keyObj) {
    echo "Key: {$keyString}, Has cache config: " . ($keyObj->hasCacheConfig() ? 'Yes' : 'No') . "\n";
}
```

## 🚀 Core Features

- **Auto cache backfill**: Automatically execute callback and cache result on cache miss
- **Batch operation optimization**: Efficient batch retrieval, avoiding N+1 query problems
- **Prefix-based deletion**: Support batch cache deletion by key prefix, equivalent to tag-based deletion
- **Hot key auto renewal**: Automatically detect and extend cache time for hot data
- **Statistics monitoring**: Real-time statistics for hit rate, hot keys and other performance metrics
- **Unified key management**: Standardized key generation with environment isolation and version management

## 📊 Statistics Features

```php
// Get statistics
$stats = kv_stats();
// ['hits' => 1500, 'misses' => 300, 'hit_rate' => '83.33%', ...]

// Get hot keys
$hotKeys = kv_hot_keys(10);
// ['user:profile:123' => 45, 'user:profile:456' => 32, ...]

// Clear statistics
kv_clear_stats();
```

## ✨ Clean API Design

CacheKV provides clean and easy-to-use function APIs:

### 🔧 Core Operations
```php
kv_get($template, $params, $callback, $ttl)      // Get cache
kv_get_multi($template, $paramsList, $callback)  // Batch get
```

### 🗝️ Key Management
```php
kv_key($template, $params)           // Create key string
kv_keys($template, $paramsList)      // Batch create keys
kv_get_keys($template, $paramsList)  // Get key objects
```

### 🗑️ Delete Operations
```php
kv_delete($template, $params)         // Delete specific cache
kv_delete_prefix($template, $params)  // Delete by prefix
kv_delete_full($prefix)               // Delete by full prefix
```

### 📊 Statistics Functions
```php
kv_stats()              // Get statistics
kv_hot_keys($limit)     // Get hot keys
kv_clear_stats()        // Clear statistics
```

### ⚙️ Configuration Management
```php
kv_config()     // Get configuration object (convertible to array)
```

## 📚 Documentation

- **[Complete Documentation](docs/README_EN.md)** - Detailed configuration, architecture and usage guide ⭐
- **[Quick Start](docs/QUICK_START_EN.md)** - 5-minute quick start guide
- **[Configuration Reference](docs/CONFIG_EN.md)** - Detailed description of all configuration options
- **[Statistics Features](docs/STATS_EN.md)** - Performance monitoring and hot key management
- **[API Reference](docs/API_EN.md)** - Complete API documentation
- **[Changelog](CHANGELOG.md)** - Version update records

## 🏆 Use Cases

- **Web Applications** - User data, page content caching
- **API Services** - Interface responses, computation result caching
- **E-commerce Platforms** - Product information, pricing, inventory caching
- **Data Analytics** - Statistical data, report caching

## 📋 System Requirements

- PHP >= 7.0
- Redis extension

## 📄 License

MIT License - See [LICENSE](LICENSE) file for details

---

**Start your efficient caching journey!** 🚀

> 💡 **Tip:** Check [Complete Documentation](docs/README_EN.md) for detailed configuration and advanced usage
