# 统计功能

CacheKV 提供了强大的统计功能，帮助你监控缓存性能和识别热点数据。

## 📊 统计功能概览

- **命中率统计**：实时监控缓存命中率
- **热点键识别**：自动识别访问频繁的缓存键
- **操作计数**：统计各种缓存操作的次数
- **性能监控**：提供详细的性能指标

## 🔧 启用统计功能

### 全局启用

在配置文件中启用统计功能：

```php
<?php
return [
    'cache' => [
        'enable_stats' => true,           // 启用全局统计
        'stats_prefix' => 'cachekv:stats:', // 统计数据前缀
        'stats_ttl' => 604800,           // 统计数据TTL（7天）
    ]
];
```

### 按组启用

可以为特定组启用或禁用统计：

```php
'groups' => [
    'user' => [
        'cache' => [
            'enable_stats' => true,      // 用户组启用统计
        ]
    ],
    'temp' => [
        'cache' => [
            'enable_stats' => false,     // 临时数据不统计
        ]
    ]
]
```

## 📈 获取统计信息

### kv_stats() - 获取全局统计

```php
$stats = kv_stats();
print_r($stats);
```

**返回数据格式：**
```php
[
    'hits' => 1500,                    // 命中次数
    'misses' => 300,                   // 未命中次数
    'hit_rate' => '83.33%',            // 命中率
    'total_requests' => 1800,          // 总请求数
    'sets' => 350,                     // 设置操作次数
    'deletes' => 50                    // 删除操作次数
]
```

### kv_hot_keys() - 获取热点键

```php
// 获取前10个热点键
$hotKeys = kv_hot_keys(10);

foreach ($hotKeys as $key => $count) {
    echo "热点键: {$key} (访问 {$count} 次)\n";
}
```

**返回数据格式：**
```php
[
    'app:user:v1:123' => 45,           // 键名 => 访问次数
    'app:user:v1:456' => 32,
    'app:product:v1:789' => 28,
    'app:config:v1:settings' => 20
]
```

### kv_clear_stats() - 清空统计数据

```php
$success = kv_clear_stats();
if ($success) {
    echo "统计数据已清空\n";
}
```

## 🔥 热点键自动续期

CacheKV 可以自动识别热点键并延长其缓存时间。

### 配置热点键自动续期

```php
'cache' => [
    'hot_key_auto_renewal' => true,     // 启用热点键自动续期
    'hot_key_threshold' => 100,         // 热点键阈值（访问次数）
    'hot_key_extend_ttl' => 7200,       // 延长的TTL（2小时）
    'hot_key_max_ttl' => 86400,         // 最大TTL（24小时）
]
```

### 工作原理

1. **检测热点**：当键的访问次数超过阈值时，标记为热点键
2. **自动续期**：热点键在被访问时自动延长TTL
3. **限制最大值**：续期不会超过配置的最大TTL

## 📊 实际应用示例

### 性能监控脚本

```php
<?php
// 定期检查缓存性能
function checkCachePerformance() {
    $stats = kv_stats();
    
    echo "=== 缓存性能报告 ===\n";
    echo "命中率: {$stats['hit_rate']}\n";
    echo "总请求: {$stats['total_requests']}\n";
    echo "命中次数: {$stats['hits']}\n";
    echo "未命中次数: {$stats['misses']}\n";
    
    // 检查命中率是否过低
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    if ($hitRate < 80) {
        echo "⚠️ 警告: 命中率过低 ({$stats['hit_rate']})\n";
    }
    
    // 显示热点键
    echo "\n=== 热点键 TOP 10 ===\n";
    $hotKeys = kv_hot_keys(10);
    foreach ($hotKeys as $key => $count) {
        echo "{$key}: {$count} 次\n";
        
        // 检查是否有超热点键
        if ($count > 1000) {
            echo "🔥 超热点键: {$key}\n";
        }
    }
}

// 每小时执行一次
checkCachePerformance();
```

### 缓存优化建议

```php
function getCacheOptimizationSuggestions() {
    $stats = kv_stats();
    $hotKeys = kv_hot_keys(20);
    $suggestions = [];
    
    // 分析命中率
    $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
    if ($hitRate < 70) {
        $suggestions[] = "命中率过低({$stats['hit_rate']})，建议检查缓存策略";
    } elseif ($hitRate > 95) {
        $suggestions[] = "命中率很高({$stats['hit_rate']})，缓存策略良好";
    }
    
    // 分析热点键
    $superHotKeys = array_filter($hotKeys, function($count) {
        return $count > 500;
    });
    
    if (!empty($superHotKeys)) {
        $suggestions[] = "发现 " . count($superHotKeys) . " 个超热点键，建议增加TTL";
    }
    
    // 分析请求量
    if ($stats['total_requests'] > 10000) {
        $suggestions[] = "请求量较大({$stats['total_requests']})，建议启用热点键自动续期";
    }
    
    return $suggestions;
}

// 获取优化建议
$suggestions = getCacheOptimizationSuggestions();
foreach ($suggestions as $suggestion) {
    echo "💡 " . $suggestion . "\n";
}
```

### 实时监控仪表板

```php
class CacheMonitor 
{
    public function getDashboardData() 
    {
        $stats = kv_stats();
        $hotKeys = kv_hot_keys(10);
        
        return [
            'overview' => [
                'hit_rate' => $stats['hit_rate'],
                'total_requests' => $stats['total_requests'],
                'status' => $this->getHealthStatus($stats)
            ],
            'hot_keys' => $hotKeys,
            'recommendations' => $this->getRecommendations($stats, $hotKeys)
        ];
    }
    
    private function getHealthStatus($stats) 
    {
        $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
        
        if ($hitRate >= 90) return 'excellent';
        if ($hitRate >= 80) return 'good';
        if ($hitRate >= 70) return 'fair';
        return 'poor';
    }
    
    private function getRecommendations($stats, $hotKeys) 
    {
        $recommendations = [];
        
        $hitRate = floatval(str_replace('%', '', $stats['hit_rate']));
        if ($hitRate < 80) {
            $recommendations[] = '考虑增加缓存TTL或优化缓存策略';
        }
        
        $superHotCount = count(array_filter($hotKeys, function($count) {
            return $count > 100;
        }));
        
        if ($superHotCount > 5) {
            $recommendations[] = '启用热点键自动续期功能';
        }
        
        return $recommendations;
    }
}

// 使用监控器
$monitor = new CacheMonitor();
$dashboard = $monitor->getDashboardData();

echo "缓存健康状态: " . $dashboard['overview']['status'] . "\n";
echo "命中率: " . $dashboard['overview']['hit_rate'] . "\n";

foreach ($dashboard['recommendations'] as $rec) {
    echo "建议: " . $rec . "\n";
}
```

## ⚙️ 统计配置选项

### 完整配置示例

```php
'cache' => [
    // 统计功能配置
    'enable_stats' => true,              // 是否启用统计
    'stats_prefix' => 'cachekv:stats:',  // 统计数据键前缀
    'stats_ttl' => 604800,               // 统计数据TTL（7天）
    
    // 热点键配置
    'hot_key_auto_renewal' => true,      // 启用热点键自动续期
    'hot_key_threshold' => 100,          // 热点键阈值
    'hot_key_extend_ttl' => 7200,        // 热点键延长TTL
    'hot_key_max_ttl' => 86400,          // 热点键最大TTL
]
```

## 🚨 注意事项

1. **性能影响**：统计功能会有轻微的性能开销，生产环境中可以选择性启用
2. **存储空间**：统计数据会占用额外的Redis存储空间
3. **数据持久性**：统计数据有TTL，会定期清理
4. **热点键检测**：需要一定的访问量才能准确识别热点键

## 🎯 最佳实践

1. **生产环境**：建议启用统计功能进行性能监控
2. **开发环境**：可以禁用统计功能提高性能
3. **定期清理**：定期清空统计数据避免数据过期
4. **监控告警**：设置命中率告警阈值
5. **热点优化**：根据热点键数据优化缓存策略
