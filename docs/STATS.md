# 统计功能详解

CacheKV 提供了完整的统计功能，帮助你监控缓存性能、识别热点数据、优化缓存策略。

## 功能概览

- **基础统计**：命中率、请求量、操作次数
- **热点键检测**：识别高频访问的缓存键
- **热点键自动续期**：自动延长热点数据的缓存时间
- **性能监控**：实时监控缓存性能指标

## 基础统计

### 获取统计信息

```php
$stats = cache_kv_get_stats();
print_r($stats);
```

**输出示例：**
```php
Array
(
    [hits] => 850                   // 缓存命中次数
    [misses] => 150                 // 缓存未命中次数
    [sets] => 200                   // 缓存设置次数
    [deletes] => 10                 // 缓存删除次数
    [total_requests] => 1000        // 总请求次数（hits + misses）
    [hit_rate] => 85%               // 命中率
    [enabled] => 1                  // 统计功能是否启用
)
```

### 统计指标说明

| 指标 | 说明 | 计算方式 |
|------|------|----------|
| `hits` | 缓存命中次数 | 每次从缓存成功获取数据时+1 |
| `misses` | 缓存未命中次数 | 每次缓存中没有数据时+1 |
| `sets` | 缓存设置次数 | 每次向缓存写入数据时+1 |
| `deletes` | 缓存删除次数 | 每次删除缓存数据时+1 |
| `total_requests` | 总请求次数 | hits + misses |
| `hit_rate` | 命中率 | (hits / total_requests) × 100% |

## 热点键检测

### 获取热点键

```php
// 获取访问频率最高的10个键
$hotKeys = cache_kv_get_hot_keys(10);
print_r($hotKeys);
```

**输出示例：**
```php
Array
(
    [myapp:user:v1:profile:123] => 45    // 键名 => 访问次数
    [myapp:user:v1:profile:456] => 32
    [myapp:user:v1:settings:123] => 28
    [myapp:user:v1:profile:789] => 25
    [myapp:user:v1:settings:456] => 22
    // ... 更多热点键
)
```

### 热点键判定标准

热点键的判定基于访问频率：

```php
// 配置热点键阈值
'cache' => array(
    'hot_key_threshold' => 100,     // 访问100次以上算热点键
),
```

**判定逻辑：**
- 统计每个键的总访问次数（hits + misses）
- 当访问次数 ≥ 阈值时，该键被标记为热点键
- 热点键按访问次数降序排列

## 热点键自动续期

### 工作原理

当检测到热点键时，系统会自动延长其缓存时间，避免热点数据过期导致的性能问题。

```php
'cache' => array(
    'hot_key_auto_renewal' => true,         // 启用自动续期
    'hot_key_threshold' => 100,             // 热点键阈值
    'hot_key_extend_ttl' => 7200,           // 延长到2小时
    'hot_key_max_ttl' => 86400,             // 最大24小时
),
```

### 续期触发条件

1. **启用自动续期**：`hot_key_auto_renewal = true`
2. **达到热点阈值**：访问次数 ≥ `hot_key_threshold`
3. **缓存命中时**：只在成功从缓存获取数据时检查
4. **TTL可延长**：新TTL > 当前TTL

### 续期逻辑

```php
// 伪代码
if (访问次数 >= 热点阈值) {
    当前TTL = redis.ttl(key);
    新TTL = min(延长TTL, 最大TTL);
    
    if (新TTL > 当前TTL) {
        redis.expire(key, 新TTL);  // 延长缓存时间
    }
}
```

### 续期示例

```php
// 配置
'hot_key_threshold' => 50,
'hot_key_extend_ttl' => 3600,
'hot_key_max_ttl' => 7200,

// 场景1：普通键
访问次数: 30 < 50  → 不续期

// 场景2：热点键首次续期
访问次数: 60 >= 50, 当前TTL: 600秒
新TTL = min(3600, 7200) = 3600秒
3600 > 600  → 续期到3600秒

// 场景3：热点键再次续期
访问次数: 100 >= 50, 当前TTL: 3600秒
新TTL = min(3600, 7200) = 3600秒
3600 = 3600  → 不续期（TTL未增加）

// 场景4：达到最大TTL
访问次数: 200 >= 50, 当前TTL: 7200秒
新TTL = min(3600, 7200) = 3600秒
3600 < 7200  → 不续期（不会缩短TTL）
```

## 实际应用场景

### 1. 性能监控

```php
function monitorCachePerformance() {
    $stats = cache_kv_get_stats();
    
    // 监控命中率
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    
    if ($hitRate < 80) {
        // 命中率过低告警
        logAlert("缓存命中率过低: {$stats['hit_rate']}");
        
        // 分析热点键
        $hotKeys = cache_kv_get_hot_keys(10);
        foreach ($hotKeys as $key => $count) {
            if ($count > 100) {
                logWarning("高频访问键: {$key} ({$count}次访问)");
            }
        }
    }
    
    // 监控请求量
    if ($stats['total_requests'] > 100000) {
        logInfo("高并发场景: {$stats['total_requests']} 次请求");
    }
    
    return $stats;
}
```

### 2. 缓存优化建议

```php
function getCacheOptimizationSuggestions() {
    $stats = cache_kv_get_stats();
    $hotKeys = cache_kv_get_hot_keys(20);
    $suggestions = [];
    
    // 基于命中率的建议
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    
    if ($hitRate < 60) {
        $suggestions[] = [
            'type' => 'critical',
            'message' => "命中率过低({$stats['hit_rate']})，建议检查缓存键设计",
            'actions' => [
                '检查键模板是否合理',
                '确认数据是否频繁变更',
                '考虑增加TTL时间'
            ]
        ];
    } elseif ($hitRate < 80) {
        $suggestions[] = [
            'type' => 'warning',
            'message' => "命中率偏低({$stats['hit_rate']})，有优化空间",
            'actions' => [
                '分析热点键的访问模式',
                '考虑启用热点键自动续期'
            ]
        ];
    }
    
    // 基于热点键的建议
    $highTrafficKeys = array_filter($hotKeys, function($count) {
        return $count > 1000;
    });
    
    if (count($highTrafficKeys) > 0) {
        $suggestions[] = [
            'type' => 'info',
            'message' => "检测到" . count($highTrafficKeys) . "个高频访问键",
            'actions' => [
                '确认热点键自动续期已启用',
                '考虑对热点数据进行预热',
                '评估是否需要增加缓存层级'
            ]
        ];
    }
    
    // 基于未命中率的建议
    if ($stats['misses'] > $stats['hits']) {
        $suggestions[] = [
            'type' => 'critical',
            'message' => "未命中次数超过命中次数，缓存效果很差",
            'actions' => [
                '检查缓存键是否正确生成',
                '确认数据是否被意外删除',
                '考虑重新设计缓存策略'
            ]
        ];
    }
    
    return $suggestions;
}
```

### 3. 定期报告生成

```php
function generateDailyCacheReport() {
    $stats = cache_kv_get_stats();
    $hotKeys = cache_kv_get_hot_keys(10);
    $suggestions = getCacheOptimizationSuggestions();
    
    $report = [
        'date' => date('Y-m-d'),
        'summary' => [
            'total_requests' => $stats['total_requests'],
            'hit_rate' => $stats['hit_rate'],
            'performance_level' => getPerformanceLevel($stats['hit_rate'])
        ],
        'top_hot_keys' => array_slice($hotKeys, 0, 5),
        'optimization_suggestions' => $suggestions,
        'trends' => [
            // 可以结合历史数据分析趋势
            'hit_rate_trend' => 'stable', // increasing/decreasing/stable
            'traffic_trend' => 'increasing'
        ]
    ];
    
    // 保存报告
    $filename = '/var/log/cache_reports/cache_report_' . date('Y-m-d') . '.json';
    file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));
    
    // 发送邮件报告（如果有严重问题）
    $criticalIssues = array_filter($suggestions, function($s) {
        return $s['type'] === 'critical';
    });
    
    if (!empty($criticalIssues)) {
        sendCacheAlertEmail($report);
    }
    
    return $report;
}

function getPerformanceLevel($hitRate) {
    $rate = floatval(str_replace('%', '', $hitRate));
    
    if ($rate >= 90) return 'excellent';
    if ($rate >= 80) return 'good';
    if ($rate >= 70) return 'fair';
    if ($rate >= 60) return 'poor';
    return 'critical';
}
```

### 4. 实时监控面板

```php
function getCacheMonitoringData() {
    $stats = cache_kv_get_stats();
    $hotKeys = cache_kv_get_hot_keys(5);
    
    return [
        'timestamp' => time(),
        'metrics' => [
            'hit_rate' => floatval(str_replace('%', '', $stats['hit_rate'])),
            'total_requests' => $stats['total_requests'],
            'requests_per_second' => calculateRPS($stats), // 需要实现
            'avg_response_time' => calculateAvgResponseTime(), // 需要实现
        ],
        'hot_keys' => $hotKeys,
        'alerts' => getActiveAlerts($stats), // 需要实现
        'status' => getSystemStatus($stats) // healthy/warning/critical
    ];
}

// 用于前端实时监控
header('Content-Type: application/json');
echo json_encode(getCacheMonitoringData());
```

## 统计配置选项

### 启用/禁用统计

```php
'cache' => array(
    'enable_stats' => true,         // 启用统计（默认：true）
),
```

**注意：**
- 禁用统计会提升性能，但失去监控能力
- 热点键自动续期依赖统计功能

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
                'hot_key_extend_ttl' => 14400,  // 用户数据延长更久
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

### 统计功能的开销

- **内存开销**：每个键约占用100-200字节内存
- **CPU开销**：每次操作增加约0.1ms
- **存储开销**：统计数据存储在内存中，重启后重置

### 优化建议

1. **生产环境**：建议启用统计，便于监控
2. **高并发场景**：可以考虑采样统计（需要自定义实现）
3. **测试环境**：可以禁用统计减少干扰

### 内存管理

```php
// 统计数据会随着键的增加而增长
// 建议定期清理不活跃的键统计（需要自定义实现）

function cleanupInactiveKeyStats($maxAge = 3600) {
    // 这是一个示例，实际需要在KeyStats类中实现
    KeyStats::cleanup($maxAge);
}
```

## 最佳实践

### 1. 合理设置阈值

```php
// 根据业务场景设置合适的阈值
'hot_key_threshold' => 100,     // 中等流量应用
'hot_key_threshold' => 1000,    // 高流量应用
'hot_key_threshold' => 10,      // 低流量应用或测试环境
```

### 2. 监控关键指标

- **命中率**：≥80% 为良好，≥90% 为优秀
- **热点键数量**：过多可能表示缓存策略需要优化
- **未命中率**：突然增加可能表示数据被意外清除

### 3. 定期分析

- 每日生成统计报告
- 每周分析热点键变化趋势
- 每月评估缓存策略效果

### 4. 告警设置

```php
// 设置合理的告警阈值
if ($hitRate < 70) {
    // 发送告警
}

if ($totalRequests > 1000000) {
    // 高并发告警
}
```
