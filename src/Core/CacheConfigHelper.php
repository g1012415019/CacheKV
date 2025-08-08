<?php

namespace Asfop\CacheKV\Core;

use Asfop\CacheKV\Key\CacheKey;

/**
 * 缓存配置助手类
 * 
 * 统一处理配置获取逻辑，避免重复的空值检查
 */
class CacheConfigHelper
{
    /**
     * 安全获取TTL配置
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param int|null $customTtl 自定义TTL
     * @param int $defaultTtl 默认TTL
     * @return int
     */
    public static function getTtl($cacheKey, $customTtl = null, $defaultTtl = 3600)
    {
        // 优先使用传入的TTL
        if ($customTtl !== null) {
            return $customTtl;
        }

        $cacheConfig = self::getCacheConfig($cacheKey);
        return $cacheConfig ? $cacheConfig->getTtl() : $defaultTtl;
    }
    
    /**
     * 安全获取是否应该缓存空值
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param bool $default 默认值
     * @return bool
     */
    public static function shouldCacheNull($cacheKey, $default = false)
    {
        $cacheConfig = self::getCacheConfig($cacheKey);
        return $cacheConfig ? $cacheConfig->isEnableNullCache() : $default;
    }
    
    /**
     * 安全获取空值缓存TTL
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param int $default 默认值
     * @return int
     */
    public static function getNullCacheTtl($cacheKey, $default = 300)
    {
        $cacheConfig = self::getCacheConfig($cacheKey);
        return $cacheConfig ? $cacheConfig->getNullCacheTtl() : $default;
    }
    
    /**
     * 安全获取是否启用热点键自动续期
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param bool $default 默认值
     * @return bool
     */
    public static function isHotKeyAutoRenewal($cacheKey, $default = true)
    {
        $cacheConfig = self::getCacheConfig($cacheKey);
        return $cacheConfig ? $cacheConfig->isHotKeyAutoRenewal() : $default;
    }
    
    /**
     * 安全获取热点键阈值
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param int $default 默认值
     * @return int
     */
    public static function getHotKeyThreshold($cacheKey, $default = 100)
    {
        $cacheConfig = self::getCacheConfig($cacheKey);
        return $cacheConfig ? $cacheConfig->getHotKeyThreshold() : $default;
    }
    
    /**
     * 安全获取热点键扩展TTL
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param int $default 默认值
     * @return int
     */
    public static function getHotKeyExtendTtl($cacheKey, $default = 7200)
    {
        $cacheConfig = self::getCacheConfig($cacheKey);
        return $cacheConfig ? $cacheConfig->getHotKeyExtendTtl() : $default;
    }
    
    /**
     * 安全获取热点键最大TTL
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @param int $default 默认值
     * @return int
     */
    public static function getHotKeyMaxTtl($cacheKey, $default = 86400)
    {
        $cacheConfig = self::getCacheConfig($cacheKey);
        return $cacheConfig ? $cacheConfig->getHotKeyMaxTtl() : $default;
    }
    
    /**
     * 安全获取缓存配置对象
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @return \Asfop\CacheKV\Configuration\CacheConfig|null
     */
    private static function getCacheConfig($cacheKey)
    {
        try {
            return $cacheKey->getCacheConfig();
        } catch (\Exception $e) {
            // 如果获取配置失败，返回null
            return null;
        }
    }
}
