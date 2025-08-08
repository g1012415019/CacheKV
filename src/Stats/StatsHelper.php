<?php

namespace Asfop\CacheKV\Stats;

use Asfop\CacheKV\Key\CacheKey;

/**
 * 统计助手类
 * 
 * 统一处理统计相关逻辑，避免重复代码
 */
class StatsHelper
{
    /**
     * 记录缓存命中（如果启用统计）
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param string $keyString 键字符串
     */
    public static function recordHitIfEnabled($cacheKey, $keyString)
    {
        if (self::isStatsEnabled($cacheKey)) {
            KeyStats::recordHit($keyString);
        }
    }
    
    /**
     * 记录缓存未命中（如果启用统计）
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param string $keyString 键字符串
     */
    public static function recordMissIfEnabled($cacheKey, $keyString)
    {
        if (self::isStatsEnabled($cacheKey)) {
            KeyStats::recordMiss($keyString);
        }
    }
    
    /**
     * 记录缓存设置（如果启用统计）
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param string $keyString 键字符串
     */
    public static function recordSetIfEnabled($cacheKey, $keyString)
    {
        if (self::isStatsEnabled($cacheKey)) {
            KeyStats::recordSet($keyString);
        }
    }
    
    /**
     * 记录缓存删除（如果启用统计）
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param string $keyString 键字符串
     */
    public static function recordDeleteIfEnabled($cacheKey, $keyString)
    {
        if (self::isStatsEnabled($cacheKey)) {
            KeyStats::recordDelete($keyString);
        }
    }
    
    /**
     * 检查是否启用统计
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @return bool
     */
    private static function isStatsEnabled($cacheKey)
    {
        try {
            return $cacheKey->getCacheConfig()->isEnableStats();
        } catch (\Exception $e) {
            // 如果获取配置失败，默认不启用统计
            return false;
        }
    }
}
