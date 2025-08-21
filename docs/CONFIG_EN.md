# Configuration Reference

Complete configuration options for CacheKV.

## Configuration File Structure

### Method 1: Single File Configuration (Traditional)

```php
<?php
return array(
    'cache' => array(
        // Global cache configuration
    ),
    'key_manager' => array(
        'groups' => array(
            'user' => array(/* User group configuration */),
            'goods' => array(/* Product group configuration */),
            // ... more groups
        ),
    ),
);
```

### Method 2: Group Configuration Files (Recommended)

**Main Configuration File** `config/cache_kv.php`:
```php
<?php
return array(
    'cache' => array(
        'ttl' => 3600,
        'enable_stats' => true,
        // ... global configuration
    ),
    'key_manager' => array(
        'app_prefix' => 'myapp',
        'separator' => ':',
        'groups' => array(
            // Manually configure each group here
            // 'user' => require __DIR__ . '/groups/user.php',
            // 'goods' => require __DIR__ . '/groups/goods.php',
        ),
    ),
);
```

## Cache Configuration (`cache`)

### Basic Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `ttl` | `int` | `3600` | Default cache time (seconds) |
| `enable_stats` | `bool` | `true` | Enable statistics |

### Hot Key Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `hot_key_auto_renewal` | `bool` | `true` | Enable hot key auto renewal |
| `hot_key_threshold` | `int` | `100` | Hot key threshold (access count) |
| `hot_key_extend_ttl` | `int` | `7200` | Hot key extend TTL (seconds) |
| `hot_key_max_ttl` | `int` | `86400` | Hot key maximum TTL (seconds) |

### Advanced Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `null_cache_ttl` | `int` | `300` | Null value cache time (seconds) |
| `enable_null_cache` | `bool` | `true` | Enable null value caching |
| `ttl_random_range` | `int` | `300` | TTL random range (seconds) |

## Key Manager Configuration (`key_manager`)

### Basic Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `app_prefix` | `string` | `'app'` | Application prefix |
| `separator` | `string` | `':'` | Key separator |

### Group Configuration

Each group configuration file structure:

```php
<?php
return array(
    'prefix' => 'group_name',           // Group prefix
    'version' => 'v1',                  // Group version
    'description' => 'Group description', // Description (optional)
    
    // Group-level cache configuration (optional)
    'cache' => array(
        'ttl' => 7200,                  // Override global TTL
    ),
    
    // Key definitions - unified structure
    'keys' => array(
        'key_name' => array(
            'template' => 'template:{param}',
            'description' => 'Key description',
            'cache' => array(           // Key-level configuration (optional)
                'ttl' => 10800,         // Keys with cache config will apply caching logic
            )
        ),
        'other_key' => array(
            'template' => 'other:{param}',
            'description' => 'Other key',
            // No cache config, only for key generation
        ),
    ),
);
```

## Configuration Inheritance

Configuration priority: **Key-level > Group-level > Global**

```php
// Example: Final user.profile TTL = 10800 seconds
'cache' => array('ttl' => 3600),                    // Global: 1 hour
'groups' => array(
    'user' => array(
        'cache' => array('ttl' => 7200),            // Group: 2 hours
        'keys' => array(
            'profile' => array(
                'cache' => array('ttl' => 10800)   // Key: 3 hours (final value)
            )
        )
    )
)
```

## Best Practices

### 1. Group Configuration File Naming

- Use lowercase letters and underscores: `user.php`, `goods.php`, `user_order.php`
- Keep file name consistent with group name

### 2. Modular Development

```
project/
├── modules/
│   ├── user/
│   │   ├── UserController.php
│   │   └── config/user.php         # User module configuration
│   └── goods/
│       ├── GoodsController.php
│       └── config/goods.php        # Goods module configuration
└── config/
    ├── cache_kv.php                # Main configuration
    └── groups/                     # Group configuration directory (optional)
        ├── user.php -> ../modules/user/config/user.php
        └── goods.php -> ../modules/goods/config/goods.php
```

### 3. Version Management

- Independent version control for each group
- Upgrade group version when data structure changes
- Main configuration file controls global configuration versioning

### 4. Team Collaboration

- Each developer maintains their own module configuration files
- Main configuration file maintained by architect
- Reduce configuration file merge conflicts
