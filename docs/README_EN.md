# CacheKV Complete Documentation

CacheKV is a PHP library focused on simplifying cache operations. Its core functionality is implementing the common pattern of "fetch from data source and backfill cache if not exists".

## 🎯 Core Features

- **Auto cache backfill**: Automatically execute callback and cache result on cache miss
- **Batch operation optimization**: Efficient batch retrieval, avoiding N+1 query problems
- **Prefix-based deletion**: Support batch cache deletion by key prefix, equivalent to tag-based deletion
- **Hot key auto renewal**: Automatically detect and extend cache time for hot data
- **Statistics monitoring**: Real-time statistics for hit rate, hot keys and other performance metrics
- **Unified key management**: Standardized key generation with environment isolation and version management

## 📦 Installation

```bash
composer require g1012415019/cache-kv
```

## ⚡ Quick Start

### Basic Configuration

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

### Basic Usage

```php
// Single data retrieval
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// Batch data retrieval
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

## 🔧 Helper Function API

CacheKV provides clean and easy-to-use helper functions:

### Core Operations
- `kv_get($template, $params, $callback, $ttl)` - Get cache
- `kv_get_multi($template, $paramsList, $callback)` - Batch get

### Key Management
- `kv_key($template, $params)` - Create key string
- `kv_keys($template, $paramsList)` - Batch create keys
- `kv_get_keys($template, $paramsList)` - Get key objects

### Delete Operations
- `kv_delete_prefix($template, $params)` - Delete by prefix
- `kv_delete_full($prefix)` - Delete by full prefix

### Statistics Functions
- `kv_stats()` - Get statistics
- `kv_hot_keys($limit)` - Get hot keys
- `kv_clear_stats()` - Clear statistics

### Configuration Management
- `kv_config()` - Get configuration object

## 🏗️ Architecture Design

### Core Components

1. **CacheKVFactory** - Factory class for initialization and configuration
2. **CacheKV** - Core cache operation class
3. **KeyManager** - Key manager for key generation and management
4. **DriverInterface** - Driver interface supporting multiple cache backends
5. **KeyStats** - Statistics functionality for cache performance monitoring

### Data Flow

```
User calls kv_get()
    ↓
KeyManager generates CacheKey
    ↓
CacheKV checks cache
    ↓
Cache hit → Return data
    ↓
Cache miss → Execute callback → Cache result → Return data
```

## ⚙️ Configuration System

### Configuration File Structure

```php
<?php
return [
    // Global cache configuration
    'cache' => [
        'ttl' => 3600,                    // Default TTL
        'enable_stats' => true,           // Enable statistics
        'hot_key_auto_renewal' => true,   // Hot key auto renewal
    ],
    
    // Key manager configuration
    'key_manager' => [
        'app_prefix' => 'app',            // Application prefix
        'separator' => ':',               // Separator
        'groups' => [
            'user' => [
                'prefix' => 'user',
                'version' => 'v1',
                'cache' => [
                    'ttl' => 7200,        // Group-level TTL override
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

### Configuration Priority

1. Function parameters (highest priority)
2. Key-level configuration
3. Group-level configuration
4. Global configuration (lowest priority)

## 🔑 Key Management System

### Key Template Format

Key templates use `group.key` format:

```php
// Template format: 'group.key'
kv_get('user.profile', ['id' => 123]);
// Generated key: app:user:v1:123

kv_get('product.info', ['id' => 456, 'lang' => 'en']);
// Generated key: app:product:v1:456:en
```

### Key Generation Rules

Complete key format: `{app_prefix}:{group_prefix}:{version}:{template_result}`

- `app_prefix`: Application prefix for environment isolation
- `group_prefix`: Group prefix for categorized management
- `version`: Version number for cache version control
- `template_result`: Template rendering result

## 📊 Statistics and Monitoring

### Statistical Metrics

- **Hit Rate**: Cache hits / Total requests
- **Hot Keys**: Cache keys with highest access frequency
- **Operation Statistics**: get, set, delete operation counts

### Monitoring Example

```php
// Get performance statistics
$stats = kv_stats();
echo "Hit rate: {$stats['hit_rate']}\n";

// Get hot keys
$hotKeys = kv_hot_keys(10);
foreach ($hotKeys as $key => $count) {
    echo "Hot key: {$key} ({$count} accesses)\n";
}
```

## 🔥 Hot Key Auto Renewal

### How It Works

1. **Track access frequency**: Record access count for each key
2. **Identify hot keys**: Keys with access count above threshold are marked as hot
3. **Auto renewal**: Hot keys automatically extend TTL on access
4. **Maximum limit**: Renewal won't exceed configured maximum TTL

### Configuration Example

```php
'cache' => [
    'hot_key_auto_renewal' => true,     // Enable auto renewal
    'hot_key_threshold' => 100,         // Hot key threshold
    'hot_key_extend_ttl' => 7200,       // Extend by 2 hours
    'hot_key_max_ttl' => 86400,         // Maximum 24 hours
]
```

## 🗑️ Cache Invalidation Strategy

### Prefix-based Deletion

```php
// Delete all cache for specific user
kv_delete_prefix('user.profile', ['id' => 123]);

// Delete all user profile cache
kv_delete_prefix('user.profile');

// Delete entire user group cache
kv_delete_prefix('user');
```

### Version Control Invalidation

Invalidate entire group cache by changing version number:

```php
// Change version number in configuration file
'user' => [
    'version' => 'v2',  // Upgrade from v1 to v2
]
```

## 🚀 Performance Optimization

### Batch Operation Optimization

```php
// ❌ Avoid loop calling single operations
foreach ($userIds as $id) {
    $users[] = kv_get('user.profile', ['id' => $id]);
}

// ✅ Use batch operations
$paramsList = array_map(function($id) {
    return ['id' => $id];
}, $userIds);
$users = kv_get_multi('user.profile', $paramsList, $callback);
```

### Configuration Optimization

```php
// Production environment optimization
'cache' => [
    'enable_stats' => false,            // Disable statistics to reduce overhead
    'hot_key_auto_renewal' => false,    // Disable hot key detection
    'ttl_random_range' => 300,          // Add TTL randomness to avoid cache avalanche
]
```

## 🛠️ Driver Support

### Redis Driver (Recommended)

```php
CacheKVFactory::configure(function() {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->select(1); // Select database
    return $redis;
});
```

### Array Driver (For Testing)

```php
use Asfop\CacheKV\Drivers\ArrayDriver;

CacheKVFactory::configure(function() {
    return new ArrayDriver();
});
```

## 🔧 Advanced Usage

### Custom Callback Logic

```php
// Complex callback logic
$user = kv_get('user.profile', ['id' => 123], function() use ($userId) {
    // 1. Query from primary database
    $user = $this->primaryDb->getUser($userId);
    
    // 2. Try secondary database if not found in primary
    if (!$user) {
        $user = $this->secondaryDb->getUser($userId);
    }
    
    // 3. Data processing
    if ($user) {
        $user['avatar_url'] = $this->generateAvatarUrl($user['avatar']);
        $user['permissions'] = $this->getUserPermissions($userId);
    }
    
    return $user;
}, 7200); // Custom TTL
```

### Conditional Caching

```php
// Cache based on conditions
$data = kv_get('api.response', ['endpoint' => $endpoint], function() use ($endpoint) {
    $response = $this->callExternalApi($endpoint);
    
    // Only cache successful responses
    if ($response['status'] === 'success') {
        return $response;
    }
    
    // Return null won't be cached
    return null;
});
```

## 🚨 Error Handling

### Exception Handling

```php
try {
    $data = kv_get('user.profile', ['id' => 123], function() {
        return getUserFromDatabase(123);
    });
} catch (CacheException $e) {
    // Handle cache configuration errors
    logger()->error('Cache error: ' . $e->getMessage());
    $data = getUserFromDatabase(123); // Fallback handling
}
```

### Fallback Strategy

```php
// Fallback when cache service is unavailable
function getUserWithFallback($userId) {
    try {
        return kv_get('user.profile', ['id' => $userId], function() use ($userId) {
            return getUserFromDatabase($userId);
        });
    } catch (Exception $e) {
        // Cache service exception, query database directly
        logger()->warning('Cache service unavailable, fallback to database');
        return getUserFromDatabase($userId);
    }
}
```

## 📚 Related Documentation

- **[Quick Start](QUICK_START_EN.md)** - 5-minute quick start guide
- **[Configuration Reference](CONFIG_EN.md)** - Detailed description of all configuration options
- **[API Reference](API_EN.md)** - Complete API documentation
- **[Statistics Features](STATS_EN.md)** - Performance monitoring and hot key management

## 🤝 Contributing

Welcome to submit Issues and Pull Requests to improve CacheKV.

## 📄 License

MIT License - See [LICENSE](../LICENSE) file for details
