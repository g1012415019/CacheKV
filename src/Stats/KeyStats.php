<?php

namespace Asfop\CacheKV\Stats;

/**
 * 键统计管理 - 支持持久化
 * 
 * 统计数据持久化到Redis，支持分布式环境
 * 兼容 PHP 7.0
 */
class KeyStats
{
    /**
     * 内存中的统计数据缓存
     * 
     * @var array
     */
    private static $memoryStats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'keys' => array() // 键级统计
    );
    
    /**
     * 是否启用统计
     * 
     * @var bool
     */
    private static $enabled = false;
    
    /**
     * Redis驱动实例
     * 
     * @var mixed
     */
    private static $driver = null;
    
    /**
     * 统计数据在Redis中的键前缀
     * 
     * @var string
     */
    private static $statsPrefix = 'cachekv:stats:';
    
    /**
     * 内存统计同步到Redis的间隔（秒）
     * 
     * @var int
     */
    private static $syncInterval = 60;
    
    /**
     * 上次同步时间
     * 
     * @var int
     */
    private static $lastSyncTime = 0;

    /**
     * 设置Redis驱动
     * 
     * @param mixed $driver Redis驱动实例
     */
    public static function setDriver($driver)
    {
        self::$driver = $driver;
    }

    /**
     * 启用统计
     */
    public static function enable()
    {
        self::$enabled = true;
        self::loadFromRedis(); // 启用时从Redis加载历史数据
    }

    /**
     * 禁用统计
     */
    public static function disable()
    {
        self::syncToRedis(); // 禁用前同步到Redis
        self::$enabled = false;
    }

    /**
     * 检查是否启用
     * 
     * @return bool
     */
    public static function isEnabled()
    {
        return self::$enabled;
    }

    /**
     * 批量记录缓存命中（性能优化）
     * 
     * @param array $keys 缓存键数组
     */
    public static function recordBatchHits(array $keys)
    {
        if (empty($keys) || !self::$enabled) {
            return;
        }
        
        $count = count($keys);
        self::$memoryStats['hits'] += $count;
        
        // 批量更新键级统计
        foreach ($keys as $key) {
            self::incrementKeyCounter($key, 'hits');
        }
        
        self::checkAndSync();
    }

    /**
     * 批量记录缓存未命中（性能优化）
     * 
     * @param array $keys 缓存键数组
     */
    public static function recordBatchMisses(array $keys)
    {
        if (empty($keys) || !self::$enabled) {
            return;
        }
        
        $count = count($keys);
        self::$memoryStats['misses'] += $count;
        
        // 批量更新键级统计
        foreach ($keys as $key) {
            self::incrementKeyCounter($key, 'misses');
        }
        
        self::checkAndSync();
    }

    /**
     * 批量记录缓存设置（性能优化）
     * 
     * @param array $keys 缓存键数组
     */
    public static function recordBatchSets(array $keys)
    {
        if (empty($keys) || !self::$enabled) {
            return;
        }
        
        $count = count($keys);
        self::$memoryStats['sets'] += $count;
        
        // 批量更新键级统计
        foreach ($keys as $key) {
            self::incrementKeyCounter($key, 'sets');
        }
        
        self::checkAndSync();
    }

    /**
     * 记录缓存命中
     * 
     * @param string $key 缓存键
     */
    public static function recordHit($key)
    {
        if (!self::$enabled) {
            return;
        }
        
        self::$memoryStats['hits']++;
        self::incrementKeyCounter($key, 'hits');
        self::checkAndSync();
    }

    /**
     * 记录缓存未命中
     * 
     * @param string $key 缓存键
     */
    public static function recordMiss($key)
    {
        if (!self::$enabled) {
            return;
        }
        
        self::$memoryStats['misses']++;
        self::incrementKeyCounter($key, 'misses');
        self::checkAndSync();
    }

    /**
     * 记录缓存设置
     * 
     * @param string $key 缓存键
     */
    public static function recordSet($key)
    {
        if (!self::$enabled) {
            return;
        }
        
        self::$memoryStats['sets']++;
        self::incrementKeyCounter($key, 'sets');
        self::checkAndSync();
    }

    /**
     * 记录缓存删除
     * 
     * @param string $key 缓存键
     */
    public static function recordDelete($key)
    {
        if (!self::$enabled) {
            return;
        }
        
        self::$memoryStats['deletes']++;
        self::incrementKeyCounter($key, 'deletes');
        self::checkAndSync();
    }

    /**
     * 获取全局统计信息
     * 
     * @return array 统计信息数组
     */
    public static function getGlobalStats()
    {
        if (!self::$enabled) {
            return array();
        }
        
        // 从Redis获取最新的持久化数据
        $persistentStats = self::loadGlobalStatsFromRedis();
        
        // 合并内存中的数据
        $totalStats = array(
            'hits' => $persistentStats['hits'] + self::$memoryStats['hits'],
            'misses' => $persistentStats['misses'] + self::$memoryStats['misses'],
            'sets' => $persistentStats['sets'] + self::$memoryStats['sets'],
            'deletes' => $persistentStats['deletes'] + self::$memoryStats['deletes'],
        );
        
        // 计算命中率
        $totalRequests = $totalStats['hits'] + $totalStats['misses'];
        $hitRate = $totalRequests > 0 ? round(($totalStats['hits'] / $totalRequests) * 100, 2) : 0;
        
        $totalStats['total_requests'] = $totalRequests;
        $totalStats['hit_rate'] = $hitRate . '%';
        
        return $totalStats;
    }

    /**
     * 获取热点键列表
     * 
     * @param int $limit 返回数量限制
     * @return array 热点键数组
     */
    public static function getHotKeys($limit = 10)
    {
        if (!self::$enabled || !self::$driver) {
            return array();
        }
        
        try {
            // 从Redis获取热点键数据
            $hotKeysData = self::$driver->get(self::$statsPrefix . 'hot_keys');
            if ($hotKeysData) {
                $hotKeys = json_decode($hotKeysData, true);
                if (is_array($hotKeys)) {
                    // 按访问次数排序
                    arsort($hotKeys);
                    return array_slice($hotKeys, 0, $limit, true);
                }
            }
        } catch (Exception $e) {
            // Redis操作失败时返回空数组
        }
        
        return array();
    }

    /**
     * 清空统计数据
     */
    public static function clear()
    {
        self::$memoryStats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'keys' => array()
        );
        
        // 清空Redis中的统计数据
        if (self::$driver) {
            try {
                self::$driver->delete(self::$statsPrefix . 'global');
                self::$driver->delete(self::$statsPrefix . 'hot_keys');
            } catch (Exception $e) {
                // 忽略Redis操作失败
            }
        }
    }

    /**
     * 强制同步到Redis
     */
    public static function forceSync()
    {
        self::syncToRedis();
    }

    /**
     * 增加键级计数器
     * 
     * @param string $key 缓存键
     * @param string $type 统计类型
     */
    private static function incrementKeyCounter($key, $type)
    {
        if (!isset(self::$memoryStats['keys'][$key])) {
            self::$memoryStats['keys'][$key] = array();
        }
        
        if (!isset(self::$memoryStats['keys'][$key][$type])) {
            self::$memoryStats['keys'][$key][$type] = 0;
        }
        
        self::$memoryStats['keys'][$key][$type]++;
    }

    /**
     * 检查是否需要同步到Redis
     */
    private static function checkAndSync()
    {
        $currentTime = time();
        if ($currentTime - self::$lastSyncTime >= self::$syncInterval) {
            self::syncToRedis();
        }
    }

    /**
     * 同步内存统计数据到Redis
     */
    private static function syncToRedis()
    {
        if (!self::$driver) {
            return;
        }
        
        try {
            // 同步全局统计
            $persistentStats = self::loadGlobalStatsFromRedis();
            $mergedStats = array(
                'hits' => $persistentStats['hits'] + self::$memoryStats['hits'],
                'misses' => $persistentStats['misses'] + self::$memoryStats['misses'],
                'sets' => $persistentStats['sets'] + self::$memoryStats['sets'],
                'deletes' => $persistentStats['deletes'] + self::$memoryStats['deletes'],
            );
            
            self::$driver->set(
                self::$statsPrefix . 'global',
                json_encode($mergedStats),
                86400 * 7 // 保存7天
            );
            
            // 同步热点键数据
            if (!empty(self::$memoryStats['keys'])) {
                $hotKeysData = self::$driver->get(self::$statsPrefix . 'hot_keys');
                $hotKeys = $hotKeysData ? json_decode($hotKeysData, true) : array();
                if (!is_array($hotKeys)) {
                    $hotKeys = array();
                }
                
                // 合并热点键数据
                foreach (self::$memoryStats['keys'] as $key => $stats) {
                    $totalAccess = ($stats['hits'] ?? 0) + ($stats['misses'] ?? 0);
                    if (!isset($hotKeys[$key])) {
                        $hotKeys[$key] = 0;
                    }
                    $hotKeys[$key] += $totalAccess;
                }
                
                // 只保留前100个热点键
                arsort($hotKeys);
                $hotKeys = array_slice($hotKeys, 0, 100, true);
                
                self::$driver->set(
                    self::$statsPrefix . 'hot_keys',
                    json_encode($hotKeys),
                    86400 * 7 // 保存7天
                );
            }
            
            // 清空内存统计
            self::$memoryStats = array(
                'hits' => 0,
                'misses' => 0,
                'sets' => 0,
                'deletes' => 0,
                'keys' => array()
            );
            
            self::$lastSyncTime = time();
            
        } catch (Exception $e) {
            // Redis操作失败时忽略，不影响主要功能
        }
    }

    /**
     * 从Redis加载统计数据到内存
     */
    private static function loadFromRedis()
    {
        if (!self::$driver) {
            return;
        }
        
        try {
            $globalStats = self::loadGlobalStatsFromRedis();
            // 注意：这里不直接覆盖内存统计，而是在获取时合并
        } catch (Exception $e) {
            // Redis操作失败时忽略
        }
    }

    /**
     * 从Redis加载全局统计数据
     * 
     * @return array
     */
    private static function loadGlobalStatsFromRedis()
    {
        $defaultStats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
        );
        
        if (!self::$driver) {
            return $defaultStats;
        }
        
        try {
            $data = self::$driver->get(self::$statsPrefix . 'global');
            if ($data) {
                $stats = json_decode($data, true);
                if (is_array($stats)) {
                    return array_merge($defaultStats, $stats);
                }
            }
        } catch (Exception $e) {
            // Redis操作失败时返回默认值
        }
        
        return $defaultStats;
    }
}
