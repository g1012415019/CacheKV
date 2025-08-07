# 统计功能

CacheKV 提供完整的统计功能，帮助监控缓存性能和优化策略。

## 基础统计

### 获取统计信息

```php
$stats = cache_kv_get_stats();
print_r($stats);
```

**输出：**
```php
Array
(
    [hits] => 850                   // 缓存命中次数
    [misses] => 150                 // 缓存未命中次数
    [total_requests] => 1000        // 总请求次数
    [hit_rate] => 85%               // 命中率
    [sets] => 200                   // 缓存设置次数
    [deletes] => 10                 // 缓存删除次数
    [enabled] => 1                  // 统计功能是否启用
)
```

## 热点键检测

### 获取热点键

```php
$hotKeys = cache_kv_get_hot_keys(10);
print_r($hotKeys);
```

**输出：**
```php
Array
(
    [myapp:user:v1:profile:123] => 45    // 键名 => 访问次数
    [myapp:user:v1:profile:456] => 32
    [myapp:user:v1:settings:123] => 28
    // ... 更多热点键
)
```

### 热点键判定

热点键基于访问频率判定：

```php
'cache' => array(
    'hot_key_threshold' => 100,     // 访问100次以上算热点键
),
```

## 热点键自动续期

### 工作原理

当键访问次数达到阈值时，系统自动延长其缓存时间。

```php
'cache' => array(
    'hot_key_auto_renewal' => true,         // 启用自动续期
    'hot_key_threshold' => 100,             // 热点键阈值
    'hot_key_extend_ttl' => 7200,           // 延长到2小时
    'hot_key_max_ttl' => 86400,             // 最大24小时
),
```

### 续期条件

1. 启用自动续期功能
2. 访问次数 ≥ 热点阈值
3. 缓存命中时触发
4. 新TTL > 当前TTL

## 实际应用

### 性能监控

```php
function monitorCachePerformance() {
    $stats = cache_kv_get_stats();
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    
    if ($hitRate < 80) {
        logAlert("缓存命中率过低: {$stats['hit_rate']}");
        
        // 分析热点键
        $hotKeys = cache_kv_get_hot_keys(5);
        foreach ($hotKeys as $key => $count) {
            if ($count > 100) {
                logWarning("高频访问键: {$key} ({$count}次)");
            }
        }
    }
    
    return $stats;
}
```

### 优化建议

```php
function getCacheOptimizationSuggestions() {
    $stats = cache_kv_get_stats();
    $hotKeys = cache_kv_get_hot_keys(20);
    $suggestions = [];
    
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    
    if ($hitRate < 60) {
        $suggestions[] = "命中率过低({$stats['hit_rate']})，检查缓存键设计";
    } elseif ($hitRate < 80) {
        $suggestions[] = "命中率偏低({$stats['hit_rate']})，考虑启用热点键自动续期";
    }
    
    $highTrafficKeys = array_filter($hotKeys, function($count) {
        return $count > 1000;
    });
    
    if (count($highTrafficKeys) > 0) {
        $suggestions[] = "检测到" . count($highTrafficKeys) . "个高频访问键，确认自动续期已启用";
    }
    
    return $suggestions;
}
```

### 定期报告

```php
function generateCacheReport() {
    $stats = cache_kv_get_stats();
    $hotKeys = cache_kv_get_hot_keys(10);
    
    $report = [
        'date' => date('Y-m-d'),
        'hit_rate' => $stats['hit_rate'],
        'total_requests' => $stats['total_requests'],
        'top_hot_keys' => array_slice($hotKeys, 0, 5),
        'performance_level' => getPerformanceLevel($stats['hit_rate'])
    ];
    
    file_put_contents(
        '/var/log/cache_report_' . date('Y-m-d') . '.json', 
        json_encode($report, JSON_PRETTY_PRINT)
    );
    
    return $report;
}

function getPerformanceLevel($hitRate) {
    $rate = floatval(str_replace('%', '', $hitRate));
    
    if ($rate >= 90) return 'excellent';
    if ($rate >= 80) return 'good';
    if ($rate >= 70) return 'fair';
    return 'poor';
}
```

## 统计配置

### 基础配置

```php
'cache' => array(
    'enable_stats' => true,         // 启用统计（默认：true）
),
```

### 热点键配置

```php
'cache' => array(
    'hot_key_threshold' => 100,             // 热点键阈值
    'hot_key_auto_renewal' => true,         // 启用自动续期
    'hot_key_extend_ttl' => 7200,           // 延长TTL
    'hot_key_max_ttl' => 86400,             // 最大TTL
),
```

### 分组级配置

```php
'key_manager' => array(
    'groups' => array(
        'user' => array(
            'cache' => array(
                'hot_key_threshold' => 50,      // 用户数据阈值更低
            ),
        ),
        'system' => array(
            'cache' => array(
                'hot_key_auto_renewal' => false, // 系统配置不自动续期
            ),
        ),
    ),
),
```

## 性能影响

### 开销说明

- **内存开销**：每个键约100-200字节
- **CPU开销**：每次操作增加约0.1ms
- **存储**：统计数据存储在内存中

### 优化建议

1. **生产环境**：建议启用统计，便于监控
2. **高并发场景**：可考虑采样统计
3. **测试环境**：可禁用统计减少干扰

## 最佳实践

### 监控指标

- **命中率**：≥80% 为良好，≥90% 为优秀
- **热点键数量**：过多可能需要优化缓存策略
- **未命中率**：突然增加可能表示数据被清除

### 告警设置

```php
// 设置合理的告警阈值
if ($hitRate < 70) {
    sendAlert("缓存命中率告警");
}

if ($totalRequests > 1000000) {
    sendAlert("高并发告警");
}
```

### 定期分析

- 每日生成统计报告
- 每周分析热点键变化趋势
- 每月评估缓存策略效果
