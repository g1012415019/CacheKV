# Quick Start

Get started with CacheKV in 5 minutes and experience clean and efficient cache operations.

## 📦 Installation

```bash
composer require g1012415019/cache-kv
```

## ⚡ Basic Configuration

### 1. Configure Redis Connection

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
```

### 2. Start Using

```php
// Single data retrieval - one line handles all cache logic
$user = kv_get('user.profile', ['id' => 123], function() {
    // Only executed on cache miss
    return getUserFromDatabase(123);
});

echo "Username: " . $user['name'];
```

## 🚀 Core Features Demo

### Single Cache Operation

```php
// Get user profile
$user = kv_get('user.profile', ['id' => 123], function() {
    return [
        'id' => 123,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
});

// Get user settings
$settings = kv_get('user.settings', ['id' => 123], function() {
    return [
        'theme' => 'dark',
        'language' => 'en-US',
        'notifications' => true
    ];
});
```

## 📚 Next Steps

- Check [Complete Documentation](README_EN.md) for advanced features
- Read [Configuration Reference](CONFIG_EN.md) for performance optimization
- Learn [API Reference](API_EN.md) to master all interfaces
