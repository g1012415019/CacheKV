# 性能优化

本指南将帮助您最大化 CacheKV 的性能，构建高效的缓存系统。

## 缓存策略优化

### 1. 合理设置 TTL

不同类型的数据应该使用不同的缓存时间：

```php
// 推荐的 TTL 策略
$ttlStrategies = [
    // 用户相关
    'user_basic' => 3600,        // 基本信息：1小时
    'user_profile' => 7200,      // 详细资料：2小时
    'user_permissions' => 1800,  // 权限信息：30分钟
    'user_session' => 7200,      // 会话信息：2小时
    
    // 商品相关
    'product_info' => 3600,      // 商品信息：1小时
    'product_price' => 600,      // 价格信息：10分钟
    'product_stock' => 300,      // 库存信息：5分钟
    
    // API 相关
    'api_weather' => 1800,       // 天气信息：30分钟
    'api_exchange' => 600,       // 汇率信息：10分钟
    'api_news' => 3600,          // 新闻信息：1小时
    
    // 统计相关
    'daily_stats' => 86400,      // 日统计：24小时
    'hourly_stats' => 3600,      // 时统计：1小时
];
```

### 2. 使用滑动过期

对于热点数据，启用滑动过期可以提高缓存命中率：

```php
// 热点数据使用滑动过期
$hotUser = $cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
    return getUserFromDatabase($userId);
}, 3600, true); // 启用滑动过期
```

### 3. 智能预热策略

```php
class CacheWarmer
{
    public function warmupHotData()
    {
        // 预热热门用户
        $hotUserIds = $this->getHotUserIds();
        foreach ($hotUserIds as $userId) {
            $this->cache->getByTemplate('user', ['id' => $userId], function() use ($userId) {
                return getUserFromDatabase($userId);
            });
        }
        
        // 预热热门商品
        $hotProductIds = $this->getHotProductIds();
        $this->preloadProducts($hotProductIds);
    }
    
    private function preloadProducts($productIds)
    {
        $productKeys = array_map(function($id) {
            return $this->keyManager->make('product', ['id' => $id]);
        }, $productIds);
        
        $this->cache->getMultiple($productKeys, function($missingKeys) {
            return $this->getProductsFromDatabase($missingKeys);
        });
    }
}
```

## 批量操作优化

### 1. 避免 N+1 查询

```php
// ❌ 错误做法：N+1 查询
$users = [];
foreach ($userIds as $id) {
    $users[] = $cache->getByTemplate('user', ['id' => $id], function() use ($id) {
        return getUserFromDatabase($id); // 每个ID都查询一次
    });
}

// ✅ 正确做法：批量操作
$userKeys = array_map(function($id) use ($keyManager) {
    return $keyManager->make('user', ['id' => $id]);
}, $userIds);

$users = $cache->getMultiple($userKeys, function($missingKeys) {
    $missingIds = $this->extractIdsFromKeys($missingKeys);
    return getUsersFromDatabase($missingIds); // 一次批量查询
});
```

### 2. 批量大小控制

```php
class BatchProcessor
{
    private $batchSize = 50; // 每批处理50个
    
    public function processLargeDataset($dataIds)
    {
        $batches = array_chunk($dataIds, $this->batchSize);
        
        foreach ($batches as $batch) {
            $this->processBatch($batch);
        }
    }
    
    private function processBatch($ids)
    {
        $keys = array_map(function($id) {
            return $this->keyManager->make('data', ['id' => $id]);
        }, $ids);
        
        return $this->cache->getMultiple($keys, function($missingKeys) {
            return $this->getDataFromDatabase($missingKeys);
        });
    }
}
```

## 内存使用优化

### 1. 控制缓存数据大小

```php
// 只缓存必要的字段
public function getUserProfile($userId)
{
    return $this->cache->getByTemplate('user_profile', ['id' => $userId], function() use ($userId) {
        $profile = $this->userRepository->getFullProfile($userId);
        
        // 只缓存必要字段，减少内存使用
        return [
            'user_id' => $profile['user_id'],
            'avatar' => $profile['avatar'],
            'bio' => substr($profile['bio'], 0, 200), // 限制长度
            'location' => $profile['location'],
            'updated_at' => $profile['updated_at']
        ];
    });
}
```

### 2. 定期清理过期数据

```php
class CacheCleaner
{
    public function cleanupExpiredData()
    {
        // 清理过期会话
        $this->cache->clearTag('expired_sessions');
        
        // 清理临时数据
        $this->cache->clearTag('temp_data');
        
        // 清理过期的搜索结果
        $this->cache->clearTag('old_search_results');
    }
    
    public function scheduleCleanup()
    {
        // 每小时执行一次清理
        $this->scheduler->hourly(function() {
            $this->cleanupExpiredData();
        });
    }
}
```

## 网络优化

### 1. 连接池配置

```php
// Redis 连接池配置
RedisDriver::setRedisFactory(function() {
    return new \Predis\Client([
        'host' => 'redis.example.com',
        'port' => 6379,
        'database' => 0,
        'timeout' => 5.0,
        'read_write_timeout' => 0,
        'persistent' => true, // 使用持久连接
    ]);
});
```

### 2. 数据压缩

```php
class CompressedCacheDriver extends RedisDriver
{
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        // 压缩大数据
        if (strlen(serialize($value)) > 1024) {
            $value = gzcompress(serialize($value));
        }
        
        return parent::set($key, $value, $ttl);
    }
    
    public function get(string $key): mixed
    {
        $value = parent::get($key);
        
        // 解压缩数据
        if (is_string($value) && substr($value, 0, 2) === "\x1f\x8b") {
            $value = unserialize(gzuncompress($value));
        }
        
        return $value;
    }
}
```

## 监控和分析

### 1. 性能监控

```php
class CacheMonitor
{
    public function getPerformanceMetrics()
    {
        $stats = $this->cache->getStats();
        
        return [
            'hit_rate' => $stats['hit_rate'],
            'total_requests' => $stats['hits'] + $stats['misses'],
            'avg_response_time' => $this->calculateAvgResponseTime(),
            'memory_usage' => $this->getMemoryUsage(),
            'top_missed_keys' => $this->getTopMissedKeys(),
        ];
    }
    
    public function alertOnLowPerformance()
    {
        $metrics = $this->getPerformanceMetrics();
        
        if ($metrics['hit_rate'] < 70) {
            $this->sendAlert('Low cache hit rate: ' . $metrics['hit_rate'] . '%');
        }
        
        if ($metrics['avg_response_time'] > 100) {
            $this->sendAlert('High response time: ' . $metrics['avg_response_time'] . 'ms');
        }
    }
}
```

### 2. 热点数据分析

```php
class HotDataAnalyzer
{
    private $accessLog = [];
    
    public function logAccess($key)
    {
        if (!isset($this->accessLog[$key])) {
            $this->accessLog[$key] = 0;
        }
        $this->accessLog[$key]++;
    }
    
    public function getHotKeys($limit = 10)
    {
        arsort($this->accessLog);
        return array_slice($this->accessLog, 0, $limit, true);
    }
    
    public function optimizeHotKeys()
    {
        $hotKeys = $this->getHotKeys();
        
        foreach ($hotKeys as $key => $count) {
            // 为热点数据设置更长的过期时间
            $this->extendTTL($key, 7200); // 2小时
        }
    }
}
```

## 驱动选择和配置

### 1. 生产环境推荐配置

```php
// Redis 生产环境配置
$redisConfig = [
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'port' => env('REDIS_PORT', 6379),
    'database' => env('REDIS_DB', 0),
    'password' => env('REDIS_PASSWORD'),
    'timeout' => 5.0,
    'read_write_timeout' => 0,
    'persistent' => true,
    'parameters' => [
        'tcp_keepalive' => 1,
    ],
];

RedisDriver::setRedisFactory(function() use ($redisConfig) {
    return new \Predis\Client($redisConfig);
});
```

### 2. 开发环境配置

```php
// 开发环境使用 Array 驱动
$cache = new CacheKV(new ArrayDriver(), 600, $keyManager); // 10分钟过期
```

## 性能测试

### 1. 基准测试

```php
class CacheBenchmark
{
    public function benchmarkSingleGet($iterations = 1000)
    {
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->cache->getByTemplate('user', ['id' => rand(1, 100)], function() {
                return ['id' => rand(1, 100), 'name' => 'User'];
            });
        }
        
        $duration = microtime(true) - $startTime;
        $avgTime = ($duration / $iterations) * 1000;
        
        echo "Single get average time: {$avgTime}ms\n";
    }
    
    public function benchmarkBatchGet($batchSize = 50, $iterations = 100)
    {
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $ids = range(1, $batchSize);
            $keys = array_map(function($id) {
                return $this->keyManager->make('user', ['id' => $id]);
            }, $ids);
            
            $this->cache->getMultiple($keys, function($missingKeys) {
                return $this->generateBatchData($missingKeys);
            });
        }
        
        $duration = microtime(true) - $startTime;
        $avgTime = ($duration / $iterations) * 1000;
        
        echo "Batch get average time: {$avgTime}ms\n";
    }
}
```

### 2. 压力测试

```php
class CacheStressTest
{
    public function stressTest($concurrency = 10, $requests = 1000)
    {
        $processes = [];
        
        for ($i = 0; $i < $concurrency; $i++) {
            $processes[] = $this->forkProcess(function() use ($requests) {
                for ($j = 0; $j < $requests; $j++) {
                    $this->cache->getByTemplate('stress_test', ['id' => rand(1, 1000)], function() {
                        return ['data' => str_repeat('x', 1024)]; // 1KB 数据
                    });
                }
            });
        }
        
        // 等待所有进程完成
        foreach ($processes as $process) {
            pcntl_waitpid($process, $status);
        }
    }
}
```

## 性能优化清单

### ✅ 缓存策略
- [ ] 根据数据特性设置合理的 TTL
- [ ] 对热点数据启用滑动过期
- [ ] 实施智能预热策略
- [ ] 使用标签管理相关缓存

### ✅ 批量操作
- [ ] 避免 N+1 查询问题
- [ ] 控制批量操作的大小
- [ ] 使用批量 API 替代循环调用

### ✅ 内存管理
- [ ] 限制缓存数据的大小
- [ ] 定期清理过期数据
- [ ] 监控内存使用情况

### ✅ 网络优化
- [ ] 配置连接池
- [ ] 对大数据启用压缩
- [ ] 使用持久连接

### ✅ 监控分析
- [ ] 监控缓存命中率
- [ ] 分析热点数据
- [ ] 设置性能告警
- [ ] 定期进行性能测试

## 常见性能问题

### Q: 缓存命中率低怎么办？

**A: 分析原因并优化**
```php
$stats = $cache->getStats();
if ($stats['hit_rate'] < 70) {
    // 1. 检查 TTL 设置是否合理
    // 2. 分析数据访问模式
    // 3. 考虑启用滑动过期
    // 4. 实施预热策略
}
```

### Q: 内存使用过高怎么办？

**A: 优化数据结构和清理策略**
```php
// 1. 减少缓存数据大小
// 2. 设置合理的过期时间
// 3. 定期清理过期数据
// 4. 使用数据压缩
```

### Q: 响应时间慢怎么办？

**A: 优化网络和批量操作**
```php
// 1. 使用批量操作
// 2. 优化网络配置
// 3. 启用数据压缩
// 4. 使用连接池
```

---

**通过这些性能优化技巧，您可以构建高性能的缓存系统！** ⚡
