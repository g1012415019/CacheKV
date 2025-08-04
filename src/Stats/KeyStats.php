<?php

namespace Asfop\CacheKV\Stats;

/**
 * 键统计管理 - 简化版
 * 
 * 假设Redis支持所有需要的功能，专注性能优化
 * 统计操作失败时不影响主功能
 */
class KeyStats
{
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
     * 设置Redis驱动
     * 
     * @param mixed $driver Redis驱动实例
     */
    public static function setDriver($driver)
    {
        self::$driver = $driver;
    }

    /**
     * 获取统计数据Redis键前缀
     * 
     * @return string
     */
    private static function getStatsPrefix()
    {
        try {
            $cacheConfig = \Asfop\CacheKV\Core\ConfigManager::getCacheConfig();
            return $cacheConfig->getStatsPrefix();
        } catch (Exception $e) {
            return 'cachekv:stats:'; // 默认值
        }
    }

    /**
     * 获取统计数据TTL
     * 
     * @return int
     */
    private static function getStatsTtl()
    {
        try {
            $cacheConfig = \Asfop\CacheKV\Core\ConfigManager::getCacheConfig();
            return $cacheConfig->getStatsTtl();
        } catch (Exception $e) {
            return 604800; // 默认7天
        }
    }

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
     * 批量记录缓存命中
     * 
     * @param array $keys 缓存键数组
     */
    public static function recordBatchHits(array $keys)
    {
        if (empty($keys) || !self::$enabled || !self::$driver) {
            return;
        }
        
        try {
            $count = count($keys);
            $statsPrefix = self::getStatsPrefix();
            
            // 使用Pipeline批量操作
            $pipe = self::$driver->pipeline();
            $pipe->incrBy($statsPrefix . 'hits', $count);
            
            // 批量更新热点键（使用Redis Sorted Set）
            foreach ($keys as $key) {
                $pipe->zincrby($statsPrefix . 'hot_keys', 1, $key);
            }
            
            $pipe->exec();
        } catch (Exception $e) {
            // Redis操作失败时忽略，不影响主要功能
        }
    }

    /**
     * 批量记录缓存未命中
     * 
     * @param array $keys 缓存键数组
     */
    public static function recordBatchMisses(array $keys)
    {
        if (empty($keys) || !self::$enabled || !self::$driver) {
            return;
        }
        
        try {
            $count = count($keys);
            
            $pipe = self::$driver->pipeline();
            $pipe->incrBy(self::getStatsPrefix() . 'misses', $count);
            
            // 未命中也算访问，记录到热点键
            foreach ($keys as $key) {
                $pipe->zincrby(self::getStatsPrefix() . 'hot_keys', 1, $key);
            }
            
            $pipe->exec();
        } catch (Exception $e) {
            // Redis操作失败时忽略
        }
    }

    /**
     * 批量记录缓存设置
     * 
     * @param array $keys 缓存键数组
     */
    public static function recordBatchSets(array $keys)
    {
        if (empty($keys) || !self::$enabled || !self::$driver) {
            return;
        }
        
        try {
            $count = count($keys);
            self::$driver->incrBy(self::getStatsPrefix() . 'sets', $count);
        } catch (Exception $e) {
            // Redis操作失败时忽略
        }
    }

    /**
     * 记录缓存命中
     * 
     * @param string $key 缓存键
     */
    public static function recordHit($key)
    {
        if (!self::$enabled || !self::$driver) {
            return;
        }
        
        try {
            // 使用Pipeline优化
            $pipe = self::$driver->pipeline();
            $pipe->incrBy(self::getStatsPrefix() . 'hits', 1);
            $pipe->zincrby(self::getStatsPrefix() . 'hot_keys', 1, $key);
            $pipe->exec();
        } catch (Exception $e) {
            // Redis操作失败时忽略
        }
    }

    /**
     * 记录缓存未命中
     * 
     * @param string $key 缓存键
     */
    public static function recordMiss($key)
    {
        if (!self::$enabled || !self::$driver) {
            return;
        }
        
        try {
            $pipe = self::$driver->pipeline();
            $pipe->incrBy(self::getStatsPrefix() . 'misses', 1);
            $pipe->zincrby(self::getStatsPrefix() . 'hot_keys', 1, $key);
            $pipe->exec();
        } catch (Exception $e) {
            // Redis操作失败时忽略
        }
    }

    /**
     * 记录缓存设置
     * 
     * @param string $key 缓存键
     */
    public static function recordSet($key)
    {
        if (!self::$enabled || !self::$driver) {
            return;
        }
        
        try {
            self::$driver->incrBy(self::getStatsPrefix() . 'sets', 1);
        } catch (Exception $e) {
            // Redis操作失败时忽略
        }
    }

    /**
     * 记录缓存删除
     * 
     * @param string $key 缓存键
     */
    public static function recordDelete($key)
    {
        if (!self::$enabled || !self::$driver) {
            return;
        }
        
        try {
            self::$driver->incrBy(self::getStatsPrefix() . 'deletes', 1);
        } catch (Exception $e) {
            // Redis操作失败时忽略
        }
    }

    /**
     * 获取全局统计信息
     * 
     * @return array 统计信息数组
     */
    public static function getGlobalStats()
    {
        if (!self::$enabled || !self::$driver) {
            return array();
        }
        
        try {
            $stats = array(
                'hits' => (int)self::$driver->get(self::getStatsPrefix() . 'hits') ?: 0,
                'misses' => (int)self::$driver->get(self::getStatsPrefix() . 'misses') ?: 0,
                'sets' => (int)self::$driver->get(self::getStatsPrefix() . 'sets') ?: 0,
                'deletes' => (int)self::$driver->get(self::getStatsPrefix() . 'deletes') ?: 0,
            );
            
            // 计算命中率
            $totalRequests = $stats['hits'] + $stats['misses'];
            $hitRate = $totalRequests > 0 ? round(($stats['hits'] / $totalRequests) * 100, 2) : 0;
            
            $stats['total_requests'] = $totalRequests;
            $stats['hit_rate'] = $hitRate . '%';
            
            return $stats;
        } catch (Exception $e) {
            return array();
        }
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
            // 使用Redis Sorted Set的ZREVRANGE命令
            $result = self::$driver->zRevRange(
                self::getStatsPrefix() . 'hot_keys', 
                0, 
                $limit - 1, 
                true // 返回分数
            );
            
            return is_array($result) ? $result : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * 清空统计数据
     */
    public static function clear()
    {
        if (!self::$driver) {
            return;
        }
        
        try {
            $keys = array('hits', 'misses', 'sets', 'deletes', 'hot_keys');
            foreach ($keys as $key) {
                self::$driver->delete(self::getStatsPrefix() . $key);
            }
        } catch (Exception $e) {
            // 忽略Redis操作失败
        }
    }
}
