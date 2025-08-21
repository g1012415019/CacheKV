# API Reference

Complete API documentation for CacheKV.

## 🔧 Core Operation Functions

### kv_get()

Get cached data with callback auto backfill support.

```php
function kv_get($template, array $params = [], $callback = null, $ttl = null)
```

**Parameters:**
- `$template` (string): Key template in format 'group.key'
- `$params` (array): Parameter array for template placeholder replacement
- `$callback` (callable|null): Callback function for cache miss
- `$ttl` (int|null): Custom TTL (seconds), overrides default configuration

**Return Value:**
- `mixed`: Cached data or callback function return value

**Examples:**
```php
// Basic usage
$user = kv_get('user.profile', ['id' => 123]);

// With callback
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
});

// Custom TTL
$user = kv_get('user.profile', ['id' => 123], function() {
    return getUserFromDatabase(123);
}, 7200); // 2 hours
```

### kv_get_multi()

Batch get cached data with batch callback support.

```php
function kv_get_multi($template, array $paramsList, $callback = null)
```

**Parameters:**
- `$template` (string): Key template in format 'group.key'
- `$paramsList` (array): Array of parameter arrays
- `$callback` (callable|null): Batch callback function

**Callback Function Signature:**
```php
function($missedKeys) {
    // $missedKeys is array of CacheKey objects
    // Must return associative array: ['key_string' => 'data', ...]
}
```

**Return Value:**
- `array`: Result array with cache key strings as keys and cached data as values

**Example:**
```php
// Batch get user information
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

## 🗝️ Key Management Functions

### kv_key()

Generate single cache key string.

```php
function kv_key($template, array $params = [])
```

**Parameters:**
- `$template` (string): Key template in format 'group.key'
- `$params` (array): Parameter array

**Return Value:**
- `string`: Generated cache key string

**Example:**
```php
$key = kv_key('user.profile', ['id' => 123]);
// Returns: "app:user:v1:123"
```

### kv_keys()

Batch generate cache key strings.

```php
function kv_keys($template, array $paramsList)
```

**Parameters:**
- `$template` (string): Key template in format 'group.key'
- `$paramsList` (array): Array of parameter arrays

**Return Value:**
- `array`: Array of key strings

**Example:**
```php
$keys = kv_keys('user.profile', [
    ['id' => 1],
    ['id' => 2],
    ['id' => 3]
]);
// Returns: ["app:user:v1:1", "app:user:v1:2", "app:user:v1:3"]
```

## 🗑️ Delete Operation Functions

### kv_delete_prefix()

Delete cache by prefix, equivalent to tag-based deletion.

```php
function kv_delete_prefix($template, array $params = [])
```

**Parameters:**
- `$template` (string): Key template in format 'group.key'
- `$params` (array): Parameter array (optional)

**Return Value:**
- `int`: Number of deleted keys

**Examples:**
```php
// Delete all cache for specific user
$deleted = kv_delete_prefix('user.profile', ['id' => 123]);

// Delete all user profile cache
$deleted = kv_delete_prefix('user.profile');

// Delete entire user group cache
$deleted = kv_delete_prefix('user');
```

## 📊 Statistics Functions

### kv_stats()

Get global statistics information.

```php
function kv_stats()
```

**Return Value:**
- `array`: Statistics information array

**Example:**
```php
$stats = kv_stats();
print_r($stats);

// Output example:
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

Get hot keys list.

```php
function kv_hot_keys($limit = 10)
```

**Parameters:**
- `$limit` (int): Number limit for returned hot keys, default 10

**Return Value:**
- `array`: Hot keys array with cache keys as keys and access counts as values

**Example:**
```php
$hotKeys = kv_hot_keys(5);
print_r($hotKeys);

// Output example:
// [
//     'app:user:v1:123' => 45,
//     'app:user:v1:456' => 32,
//     'app:product:v1:789' => 28,
//     'app:user:v1:101' => 25,
//     'app:config:v1:settings' => 20
// ]
```

### kv_clear_stats()

Clear statistics data.

```php
function kv_clear_stats()
```

**Return Value:**
- `bool`: Whether successfully cleared

**Example:**
```php
$success = kv_clear_stats();
if ($success) {
    echo "Statistics data cleared\n";
}
```

## ⚙️ Configuration Management Functions

### kv_config()

Get complete configuration object.

```php
function kv_config()
```

**Return Value:**
- `CacheKVConfig`: Configuration object, convertible to array

**Example:**
```php
$config = kv_config();

// Convert to array for viewing
$configArray = $config->toArray();
print_r($configArray);

// Get specific configuration
$cacheConfig = $config->getCacheConfig();
$keyManagerConfig = $config->getKeyManagerConfig();
```

## 🚨 Error Handling

All functions handle error conditions gracefully:

- **Configuration errors**: Throw `CacheException`
- **Network errors**: Return default values when Redis connection fails
- **Serialization errors**: Auto fallback handling
- **Callback errors**: Log errors but don't affect main flow

**Best Practice:**
```php
try {
    $data = kv_get('user.profile', ['id' => 123], function() {
        return fetchUserFromDatabase(123);
    });
} catch (CacheException $e) {
    // Handle configuration errors
    logError("Cache configuration error: " . $e->getMessage());
    $data = fetchUserFromDatabase(123); // Fallback to direct query
}
```

## 📝 Notes

1. **Template format**: Must use 'group.key' format
2. **Parameter naming**: Parameter names must match placeholders in template
3. **Callback return value**: Batch callbacks must return associative array
4. **Key strings**: Generated keys include app prefix, group prefix and version
5. **TTL priority**: Function parameters > Key-level config > Group-level config > Global config
