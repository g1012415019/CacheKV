<?php
/**
 * CacheKV 性能基准测试
 * 
 * 测试 CacheKV 在不同场景下的性能表现
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

// 缓存模板定义
class BenchmarkTemplates {
    const USER = 'user';
    const PRODUCT = 'product';
    const SESSION = 'session';
}

class CacheBenchmark {
    private $iterations = 1000;
    private $batchSize = 100;
    
    public function __construct($iterations = 1000, $batchSize = 100) {
        $this->iterations = $iterations;
        $this->batchSize = $batchSize;
    }
    
    public function runAllBenchmarks() {
        echo "=== CacheKV 性能基准测试 ===\n\n";
        
        // Array 驱动测试
        echo "1. Array 驱动性能测试\n";
        echo str_repeat("-", 50) . "\n";
        $this->setupArrayDriver();
        $this->runBenchmarkSuite('Array');
        
        echo "\n";
        
        // Redis 驱动测试（如果可用）
        if (class_exists('\Predis\Client')) {
            echo "2. Redis 驱动性能测试\n";
            echo str_repeat("-", 50) . "\n";
            try {
                $this->setupRedisDriver();
                $this->runBenchmarkSuite('Redis');
            } catch (Exception $e) {
                echo "Redis 不可用: " . $e->getMessage() . "\n";
            }
        } else {
            echo "2. Redis 驱动未安装 (需要 predis/predis)\n";
        }
        
        echo "\n=== 基准测试完成 ===\n";
    }
    
    private function setupArrayDriver() {
        CacheKVFactory::setDefaultConfig([
            'default' => 'array',
            'stores' => [
                'array' => [
                    'driver' => new ArrayDriver(),
                    'ttl' => 3600
                ]
            ],
            'key_manager' => [
                'app_prefix' => 'benchmark',
                'env_prefix' => 'test',
                'version' => 'v1',
                'templates' => [
                    BenchmarkTemplates::USER => 'user:{id}',
                    BenchmarkTemplates::PRODUCT => 'product:{id}',
                    BenchmarkTemplates::SESSION => 'session:{id}',
                ]
            ]
        ]);
    }
    
    private function setupRedisDriver() {
        $redis = new \Predis\Client(['host' => '127.0.0.1', 'port' => 6379]);
        $redis->ping(); // 测试连接
        
        CacheKVFactory::setDefaultConfig([
            'default' => 'redis',
            'stores' => [
                'redis' => [
                    'driver' => new RedisDriver($redis),
                    'ttl' => 3600
                ]
            ],
            'key_manager' => [
                'app_prefix' => 'benchmark',
                'env_prefix' => 'test',
                'version' => 'v1',
                'templates' => [
                    BenchmarkTemplates::USER => 'user:{id}',
                    BenchmarkTemplates::PRODUCT => 'product:{id}',
                    BenchmarkTemplates::SESSION => 'session:{id}',
                ]
            ]
        ]);
    }
    
    private function runBenchmarkSuite($driverName) {
        $cache = CacheKVFactory::store();
        
        // 清空缓存
        $cache->flush();
        
        // 1. 基础操作性能测试
        $this->benchmarkBasicOperations($driverName);
        
        // 2. 模板操作性能测试
        $this->benchmarkTemplateOperations($driverName);
        
        // 3. 批量操作性能测试
        $this->benchmarkBatchOperations($driverName);
        
        // 4. 自动回填性能测试
        $this->benchmarkAutoFillOperations($driverName);
        
        // 5. 缓存命中率测试
        $this->benchmarkHitRate($driverName);
    }
    
    private function benchmarkBasicOperations($driverName) {
        echo "基础操作性能测试:\n";
        
        $cache = CacheKVFactory::store();
        
        // Set 操作
        $startTime = microtime(true);
        for ($i = 0; $i < $this->iterations; $i++) {
            $cache->set("basic_key_{$i}", "basic_value_{$i}");
        }
        $setTime = microtime(true) - $startTime;
        
        // Get 操作
        $startTime = microtime(true);
        for ($i = 0; $i < $this->iterations; $i++) {
            $cache->get("basic_key_{$i}");
        }
        $getTime = microtime(true) - $startTime;
        
        // Has 操作
        $startTime = microtime(true);
        for ($i = 0; $i < $this->iterations; $i++) {
            $cache->has("basic_key_{$i}");
        }
        $hasTime = microtime(true) - $startTime;
        
        // Delete 操作
        $startTime = microtime(true);
        for ($i = 0; $i < $this->iterations; $i++) {
            $cache->delete("basic_key_{$i}");
        }
        $deleteTime = microtime(true) - $startTime;
        
        printf("  Set:    %d 次操作, %.4f 秒, %.0f ops/sec\n", 
            $this->iterations, $setTime, $this->iterations / $setTime);
        printf("  Get:    %d 次操作, %.4f 秒, %.0f ops/sec\n", 
            $this->iterations, $getTime, $this->iterations / $getTime);
        printf("  Has:    %d 次操作, %.4f 秒, %.0f ops/sec\n", 
            $this->iterations, $hasTime, $this->iterations / $hasTime);
        printf("  Delete: %d 次操作, %.4f 秒, %.0f ops/sec\n", 
            $this->iterations, $deleteTime, $this->iterations / $deleteTime);
    }
    
    private function benchmarkTemplateOperations($driverName) {
        echo "\n模板操作性能测试:\n";
        
        $cache = CacheKVFactory::store();
        
        // 模板 Set 操作
        $startTime = microtime(true);
        for ($i = 0; $i < $this->batchSize; $i++) {
            $cache->setByTemplate(BenchmarkTemplates::USER, ['id' => $i], [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com"
            ]);
        }
        $templateSetTime = microtime(true) - $startTime;
        
        // 模板 Get 操作
        $startTime = microtime(true);
        for ($i = 0; $i < $this->batchSize; $i++) {
            $cache->getByTemplate(BenchmarkTemplates::USER, ['id' => $i]);
        }
        $templateGetTime = microtime(true) - $startTime;
        
        // 模板 Get 带回调操作
        $cache->flush(); // 清空缓存测试回调
        
        $startTime = microtime(true);
        for ($i = 0; $i < $this->batchSize; $i++) {
            $cache->getByTemplate(BenchmarkTemplates::USER, ['id' => $i], function() use ($i) {
                return [
                    'id' => $i,
                    'name' => "User {$i}",
                    'email' => "user{$i}@example.com"
                ];
            });
        }
        $templateGetWithCallbackTime = microtime(true) - $startTime;
        
        printf("  模板 Set:           %d 项, %.4f 秒, %.0f items/sec\n", 
            $this->batchSize, $templateSetTime, $this->batchSize / $templateSetTime);
        printf("  模板 Get:           %d 项, %.4f 秒, %.0f items/sec\n", 
            $this->batchSize, $templateGetTime, $this->batchSize / $templateGetTime);
        printf("  模板 Get (回调):    %d 项, %.4f 秒, %.0f items/sec\n", 
            $this->batchSize, $templateGetWithCallbackTime, $this->batchSize / $templateGetWithCallbackTime);
    }
    
    private function benchmarkBatchOperations($driverName) {
        echo "\n批量操作性能测试:\n";
        
        $cache = CacheKVFactory::store();
        
        // 准备批量数据
        $batchData = [];
        $batchKeys = [];
        for ($i = 0; $i < $this->batchSize; $i++) {
            $key = "batch_key_{$i}";
            $batchData[$key] = "batch_value_{$i}";
            $batchKeys[] = $key;
        }
        
        // 批量 Set
        $startTime = microtime(true);
        $cache->setMultiple($batchData);
        $batchSetTime = microtime(true) - $startTime;
        
        // 批量 Get
        $startTime = microtime(true);
        $cache->getMultiple($batchKeys);
        $batchGetTime = microtime(true) - $startTime;
        
        // 批量 Delete
        $startTime = microtime(true);
        $cache->deleteMultiple($batchKeys);
        $batchDeleteTime = microtime(true) - $startTime;
        
        printf("  批量 Set:    %d 项, %.4f 秒, %.0f items/sec\n", 
            $this->batchSize, $batchSetTime, $this->batchSize / $batchSetTime);
        printf("  批量 Get:    %d 项, %.4f 秒, %.0f items/sec\n", 
            $this->batchSize, $batchGetTime, $this->batchSize / $batchGetTime);
        printf("  批量 Delete: %d 项, %.4f 秒, %.0f items/sec\n", 
            $this->batchSize, $batchDeleteTime, $this->batchSize / $batchDeleteTime);
    }
    
    private function benchmarkAutoFillOperations($driverName) {
        echo "\n自动回填性能测试:\n";
        
        $cache = CacheKVFactory::store();
        $cache->flush();
        
        // 测试自动回填功能
        $startTime = microtime(true);
        for ($i = 0; $i < $this->batchSize; $i++) {
            $cache->get("autofill_key_{$i}", function() use ($i) {
                return "autofill_value_{$i}";
            });
        }
        $autoFillTime = microtime(true) - $startTime;
        
        // 测试缓存命中
        $startTime = microtime(true);
        for ($i = 0; $i < $this->batchSize; $i++) {
            $cache->get("autofill_key_{$i}");
        }
        $hitTime = microtime(true) - $startTime;
        
        printf("  自动回填:    %d 项, %.4f 秒, %.0f items/sec\n", 
            $this->batchSize, $autoFillTime, $this->batchSize / $autoFillTime);
        printf("  缓存命中:    %d 项, %.4f 秒, %.0f items/sec\n", 
            $this->batchSize, $hitTime, $this->batchSize / $hitTime);
    }
    
    private function benchmarkHitRate($driverName) {
        echo "\n缓存命中率测试:\n";
        
        $cache = CacheKVFactory::store();
        
        // 预填充缓存
        for ($i = 0; $i < $this->batchSize; $i++) {
            $cache->set("hit_test_{$i}", "value_{$i}");
        }
        
        $hits = 0;
        $misses = 0;
        
        $startTime = microtime(true);
        
        // 测试命中和未命中
        for ($i = 0; $i < $this->iterations; $i++) {
            $key = "hit_test_" . ($i % ($this->batchSize * 2)); // 50% 命中率
            $result = $cache->get($key);
            
            if ($result !== null) {
                $hits++;
            } else {
                $misses++;
            }
        }
        
        $totalTime = microtime(true) - $startTime;
        $hitRate = $hits / ($hits + $misses) * 100;
        
        printf("  总查询: %d 次, 命中: %d, 未命中: %d\n", $hits + $misses, $hits, $misses);
        printf("  命中率: %.1f%%, 总时间: %.4f 秒, %.0f ops/sec\n", 
            $hitRate, $totalTime, $this->iterations / $totalTime);
    }
}

// 内存使用情况监控
class MemoryMonitor {
    private $startMemory;
    
    public function start() {
        $this->startMemory = memory_get_usage(true);
        echo "开始内存使用: " . $this->formatBytes($this->startMemory) . "\n";
    }
    
    public function end() {
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        echo "结束内存使用: " . $this->formatBytes($endMemory) . "\n";
        echo "峰值内存使用: " . $this->formatBytes($peakMemory) . "\n";
        echo "内存增长: " . $this->formatBytes($endMemory - $this->startMemory) . "\n";
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// 运行基准测试
if (php_sapi_name() === 'cli') {
    $monitor = new MemoryMonitor();
    $monitor->start();
    
    $benchmark = new CacheBenchmark(1000, 100);
    $benchmark->runAllBenchmarks();
    
    echo "\n";
    $monitor->end();
} else {
    echo "请在命令行环境下运行此脚本\n";
}
