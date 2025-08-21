# Statistics Features

CacheKV provides powerful statistics features to help you monitor cache performance and identify hot data.

## 📊 Statistics Overview

- **Hit Rate Statistics**: Real-time monitoring of cache hit rates
- **Hot Key Identification**: Automatically identify frequently accessed cache keys
- **Operation Counting**: Statistics for various cache operations
- **Performance Monitoring**: Detailed performance metrics

## 🔧 Enable Statistics

### Global Enable

Enable statistics in configuration file:

```php
<?php
return [
    'cache' => [
        'enable_stats' => true,           // Enable global statistics
        'stats_prefix' => 'cachekv:stats:', // Statistics data prefix
        'stats_ttl' => 604800,           // Statistics data TTL (7 days)
    ]
];
```

### Enable by Group

Enable or disable statistics for specific groups:

```php
'groups' => [
    'user' => [
        'cache' => [
            'enable_stats' => true,      // Enable statistics for user group
        ]
    ],
    'temp' => [
        'cache' => [
            'enable_stats' => false,     // Disable statistics for temporary data
        ]
    ]
]
```

## 📈 Get Statistics Information

### kv_stats() - Get Global Statistics

```php
$stats = kv_stats();
print_r($stats);
```

**Return Data Format:**
```php
[
    'hits' => 1500,                    // Hit count
    'misses' => 300,                   // Miss count
    'hit_rate' => '83.33%',            // Hit rate
    'total_requests' => 1800,          // Total requests
    'sets' => 350,                     // Set operations count
    'deletes' => 50                    // Delete operations count
]
```

### kv_hot_keys() - Get Hot Keys

```php
// Get top 10 hot keys
$hotKeys = kv_hot_keys(10);

foreach ($hotKeys as $key => $count) {
    echo "Hot key: {$key} (accessed {$count} times)\n";
}
```

**Return Data Format:**
```php
[
    'app:user:v1:123' => 45,           // Key name => Access count
    'app:user:v1:456' => 32,
    'app:product:v1:789' => 28,
    'app:config:v1:settings' => 20
]
```

### kv_clear_stats() - Clear Statistics Data

```php
$success = kv_clear_stats();
if ($success) {
    echo "Statistics data cleared\n";
}
```

## 🔥 Hot Key Auto Renewal

CacheKV can automatically identify hot keys and extend their cache time.

### Configure Hot Key Auto Renewal

```php
'cache' => [
    'hot_key_auto_renewal' => true,     // Enable hot key auto renewal
    'hot_key_threshold' => 100,         // Hot key threshold (access count)
    'hot_key_extend_ttl' => 7200,       // Extended TTL (2 hours)
    'hot_key_max_ttl' => 86400,         // Maximum TTL (24 hours)
]
```

### How It Works

1. **Detect Hot Keys**: When key access count exceeds threshold, mark as hot key
2. **Auto Renewal**: Hot keys automatically extend TTL when accessed
3. **Maximum Limit**: Renewal won't exceed configured maximum TTL

## 📊 Real-world Examples

### Performance Monitoring Script

```php
<?php
// Periodically check cache performance
function checkCachePerformance() {
    $stats = kv_stats();
    
    echo "=== Cache Performance Report ===\n";
    echo "Hit rate: {$stats['hit_rate']}\n";
    echo "Total requests: {$stats['total_requests']}\n";
    echo "Hits: {$stats['hits']}\n";
    echo "Misses: {$stats['misses']}\n";
    
    // Check if hit rate is too low
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    if ($hitRate < 80) {
        echo "⚠️ Warning: Hit rate is low ({$stats['hit_rate']})\n";
    }
    
    // Show hot keys
    echo "\n=== Hot Keys TOP 10 ===\n";
    $hotKeys = kv_hot_keys(10);
    foreach ($hotKeys as $key => $count) {
        echo "{$key}: {$count} times\n";
        
        // Check for super hot keys
        if ($count > 1000) {
            echo "🔥 Super hot key: {$key}\n";
        }
    }
}

// Execute every hour
checkCachePerformance();
```

### Cache Optimization Suggestions

```php
function getCacheOptimizationSuggestions() {
    $stats = kv_stats();
    $hotKeys = kv_hot_keys(20);
    $suggestions = [];
    
    // Analyze hit rate
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    if ($hitRate < 70) {
        $suggestions[] = "Hit rate is low ({$stats['hit_rate']}), consider reviewing cache strategy";
    } elseif ($hitRate > 95) {
        $suggestions[] = "Hit rate is excellent ({$stats['hit_rate']}), cache strategy is good";
    }
    
    // Analyze hot keys
    $superHotKeys = array_filter($hotKeys, function($count) {
        return $count > 500;
    });
    
    if (!empty($superHotKeys)) {
        $suggestions[] = "Found " . count($superHotKeys) . " super hot keys, consider increasing TTL";
    }
    
    // Analyze request volume
    if ($stats['total_requests'] > 10000) {
        $suggestions[] = "High request volume ({$stats['total_requests']}), consider enabling hot key auto renewal";
    }
    
    return $suggestions;
}

// Get optimization suggestions
$suggestions = getCacheOptimizationSuggestions();
foreach ($suggestions as $suggestion) {
    echo "💡 " . $suggestion . "\n";
}
```

## ⚙️ Statistics Configuration Options

### Complete Configuration Example

```php
'cache' => [
    // Statistics configuration
    'enable_stats' => true,              // Enable statistics
    'stats_prefix' => 'cachekv:stats:',  // Statistics data key prefix
    'stats_ttl' => 604800,               // Statistics data TTL (7 days)
    
    // Hot key configuration
    'hot_key_auto_renewal' => true,      // Enable hot key auto renewal
    'hot_key_threshold' => 100,          // Hot key threshold
    'hot_key_extend_ttl' => 7200,        // Hot key extend TTL
    'hot_key_max_ttl' => 86400,          // Hot key maximum TTL
]
```

## 🚨 Notes

1. **Performance Impact**: Statistics features have slight performance overhead, can be selectively enabled in production
2. **Storage Space**: Statistics data will consume additional Redis storage space
3. **Data Persistence**: Statistics data has TTL and will be cleaned periodically
4. **Hot Key Detection**: Requires certain access volume to accurately identify hot keys

## 🎯 Best Practices

1. **Production Environment**: Recommend enabling statistics for performance monitoring
2. **Development Environment**: Can disable statistics to improve performance
3. **Regular Cleanup**: Periodically clear statistics data to avoid data expiration
4. **Monitoring Alerts**: Set hit rate alert thresholds
5. **Hot Key Optimization**: Optimize cache strategy based on hot key data
