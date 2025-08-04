<?php

namespace Asfop\CacheKV\Stats;

/**
 * 键统计管理 - 直接Redis存储
 * 
 * 每次统计操作直接写入Redis，不依赖内存缓存
 * 兼容 PHP 7.0
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
     * 统计数据在Redis中的键前缀
     * 
     * @var string
     */
    private static $statsPrefix = 'cachekv:stats:';
    
    /**
     * 统计数据TTL（7天）
     * 
     * @var int
     */
    private static $statsTtl = 604800;

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
            // 增加全局命中计数
            self::incrementGlobalCounter('hits', count($keys));
            
            // 增加热点键计数
            foreach ($keys as $key) {
                self::incrementHotKeyCounter($key);
            }
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
            // 增加全局未命中计数
            self::incrementGlobalCounter('misses', count($keys));
            
            // 增加热点键计数（未命中也算访问）
            foreach ($keys as $key) {
                self::incrementHotKeyCounter($key);
            }
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
            // 增加全局设置计数
            self::incrementGlobalCounter('sets', count($keys));
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
            self::incrementGlobalCounter('hits', 1);
            self::incrementHotKeyCounter($key);
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
            self::incrementGlobalCounter('misses', 1);
            self::incrementHotKeyCounter($key);
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
            self::incrementGlobalCounter('sets', 1);
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
            self::incrementGlobalCounter('deletes', 1);
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
                'hits' => self::getGlobalCounter('hits'),
                'misses' => self::getGlobalCounter('misses'),
                'sets' => self::getGlobalCounter('sets'),
                'deletes' => self::getGlobalCounter('deletes'),
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
        if (!self::$driver) {
            return;
        }
        
        try {
            // 清空所有统计相关的键
            $keys = array('hits', 'misses', 'sets', 'deletes', 'hot_keys');
            foreach ($keys as $key) {
                self::$driver->delete(self::$statsPrefix . $key);
            }
        } catch (Exception $e) {
            // 忽略Redis操作失败
        }
    }

    /**
     * 增加全局计数器
     * 
     * @param string $type 计数器类型
     * @param int $increment 增加数量
     */
    private static function incrementGlobalCounter($type, $increment = 1)
    {
        $key = self::$statsPrefix . $type;
        
        // 尝试使用Redis的INCRBY命令
        if (method_exists(self::$driver, 'incrBy')) {
            self::$driver->incrBy($key, $increment);
            self::$driver->expire($key, self::$statsTtl);
        } else {
            // 降级方案：GET -> 计算 -> SET
            $current = self::$driver->get($key);
            $current = $current ? (int)$current : 0;
            $new = $current + $increment;
            self::$driver->set($key, $new, self::$statsTtl);
        }
    }

    /**
     * 获取全局计数器值
     * 
     * @param string $type 计数器类型
     * @return int 计数器值
     */
    private static function getGlobalCounter($type)
    {
        $key = self::$statsPrefix . $type;
        $value = self::$driver->get($key);
        return $value ? (int)$value : 0;
    }

    /**
     * 增加热点键计数器
     * 
     * @param string $key 缓存键
     */
    private static function incrementHotKeyCounter($key)
    {
        $hotKeysKey = self::$statsPrefix . 'hot_keys';
        
        // 获取当前热点键数据
        $hotKeysData = self::$driver->get($hotKeysKey);
        $hotKeys = $hotKeysData ? json_decode($hotKeysData, true) : array();
        if (!is_array($hotKeys)) {
            $hotKeys = array();
        }
        
        // 增加计数
        if (!isset($hotKeys[$key])) {
            $hotKeys[$key] = 0;
        }
        $hotKeys[$key]++;
        
        // 只保留前100个热点键（避免数据过大）
        if (count($hotKeys) > 100) {
            arsort($hotKeys);
            $hotKeys = array_slice($hotKeys, 0, 100, true);
        }
        
        // 保存回Redis
        self::$driver->set($hotKeysKey, json_encode($hotKeys), self::$statsTtl);
    }
}
