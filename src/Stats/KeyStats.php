<?php

namespace Asfop\CacheKV\Stats;

/**
 * 轻量级键统计 - 简洁版
 * 
 * 只统计最核心的指标，性能优先
 * 兼容 PHP 7.0
 */
class KeyStats
{
    /**
     * 统计数据存储
     * 
     * @var array
     */
    private static $stats = array(
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
     * 启用统计
     */
    public static function enable()
    {
        self::$enabled = true;
    }

    /**
     * 禁用统计
     */
    public static function disable()
    {
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
     * 记录缓存命中
     * 
     * @param string $key 缓存键
     */
    public static function recordHit($key)
    {
        // 自动启用统计（如果还未启用）
        if (!self::$enabled) {
            self::$enabled = true;
        }
        
        self::$stats['hits']++;
        self::incrementKeyCounter($key, 'hits');
    }

    /**
     * 记录缓存未命中
     * 
     * @param string $key 缓存键
     */
    public static function recordMiss($key)
    {
        // 自动启用统计（如果还未启用）
        if (!self::$enabled) {
            self::$enabled = true;
        }
        
        self::$stats['misses']++;
        self::incrementKeyCounter($key, 'misses');
    }

    /**
     * 记录缓存设置
     * 
     * @param string $key 缓存键
     */
    public static function recordSet($key)
    {
        // 自动启用统计（如果还未启用）
        if (!self::$enabled) {
            self::$enabled = true;
        }
        
        self::$stats['sets']++;
        self::incrementKeyCounter($key, 'sets');
    }

    /**
     * 记录缓存删除
     * 
     * @param string $key 缓存键
     */
    public static function recordDelete($key)
    {
        // 自动启用统计（如果还未启用）
        if (!self::$enabled) {
            self::$enabled = true;
        }
        
        self::$stats['deletes']++;
        self::incrementKeyCounter($key, 'deletes');
    }

    /**
     * 获取全局统计
     * 
     * @return array
     */
    public static function getGlobalStats()
    {
        $total = self::$stats['hits'] + self::$stats['misses'];
        $hitRate = $total > 0 ? round((self::$stats['hits'] / $total) * 100, 2) : 0;
        
        return array(
            'hits' => self::$stats['hits'],
            'misses' => self::$stats['misses'],
            'sets' => self::$stats['sets'],
            'deletes' => self::$stats['deletes'],
            'total_requests' => $total,
            'hit_rate' => $hitRate . '%',
            'enabled' => self::$enabled
        );
    }

    /**
     * 获取热点键（访问次数最多的键）
     * 
     * @param int $limit 返回数量限制
     * @return array
     */
    public static function getHotKeys($limit = 10)
    {
        if (!self::$enabled) {
            return array();
        }
        
        $keyStats = array();
        
        foreach (self::$stats['keys'] as $key => $stats) {
            $hits = isset($stats['hits']) ? (int)$stats['hits'] : 0;
            $misses = isset($stats['misses']) ? (int)$stats['misses'] : 0;
            $total = $hits + $misses;
            
            if ($total > 0) {
                $keyStats[$key] = array(
                    'key' => $key,
                    'total_requests' => $total,
                    'hits' => $hits,
                    'misses' => $misses,
                    'hit_rate' => round(($hits / $total) * 100, 2)
                );
            }
        }
        
        // 按总请求数排序
        uasort($keyStats, function($a, $b) {
            return $b['total_requests'] - $a['total_requests'];
        });
        
        return array_slice($keyStats, 0, $limit);
    }

    /**
     * 获取指定键的统计
     * 
     * @param string $key 缓存键
     * @return array|null
     */
    public static function getKeyStats($key)
    {
        if (!self::$enabled || !isset(self::$stats['keys'][$key])) {
            return null;
        }
        
        $stats = self::$stats['keys'][$key];
        $hits = isset($stats['hits']) ? (int)$stats['hits'] : 0;
        $misses = isset($stats['misses']) ? (int)$stats['misses'] : 0;
        $sets = isset($stats['sets']) ? (int)$stats['sets'] : 0;
        $deletes = isset($stats['deletes']) ? (int)$stats['deletes'] : 0;
        $total = $hits + $misses;
        $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;
        
        return array(
            'key' => $key,
            'hits' => $hits,
            'misses' => $misses,
            'sets' => $sets,
            'deletes' => $deletes,
            'total_requests' => $total,
            'hit_rate' => $hitRate . '%'
        );
    }

    /**
     * 获取键的访问频率（总请求次数）
     * 
     * @param string $key 缓存键
     * @return int 访问频率
     */
    public static function getKeyFrequency($key)
    {
        if (!self::$enabled || !isset(self::$stats['keys'][$key])) {
            return 0;
        }
        
        $stats = self::$stats['keys'][$key];
        $hits = isset($stats['hits']) ? (int)$stats['hits'] : 0;
        $misses = isset($stats['misses']) ? (int)$stats['misses'] : 0;
        
        return $hits + $misses;
    }

    /**
     * 重置统计数据
     */
    public static function reset()
    {
        self::$stats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'keys' => array()
        );
    }

    /**
     * 增加键级计数器
     * 
     * @param string $key 缓存键
     * @param string $type 统计类型
     */
    private static function incrementKeyCounter($key, $type)
    {
        if (!isset(self::$stats['keys'][$key])) {
            self::$stats['keys'][$key] = array();
        }
        
        if (!isset(self::$stats['keys'][$key][$type])) {
            self::$stats['keys'][$key][$type] = 0;
        }
        
        self::$stats['keys'][$key][$type]++;
    }
}
