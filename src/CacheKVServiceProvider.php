<?php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\KeyManager;

class CacheKVServiceProvider
{
    /**
     * 初始化 CacheKV 并注册到门面
     * @param array|null $config 可选的配置覆盖
     * @throws \Exception 当配置无效时抛出异常
     */
    public static function register($config = null)
    {
        // 加载默认配置
        $defaultConfig = require __DIR__ . '/Config/cachekv.php';

        // 如果传入自定义配置，则合并
        $finalConfig = $config ? array_merge($defaultConfig, $config) : $defaultConfig;

        // 验证配置
        if (!isset($finalConfig['default']) || !isset($finalConfig['stores'])) {
            throw new \Exception('Invalid cache configuration: missing default or stores');
        }

        $storeName = $finalConfig['default'];
        
        if (!isset($finalConfig['stores'][$storeName])) {
            throw new \Exception("Cache store '{$storeName}' not found in configuration");
        }

        $storeConfig = $finalConfig['stores'][$storeName];
        
        if (!isset($storeConfig['driver'])) {
            throw new \Exception("Driver not specified for cache store '{$storeName}'");
        }

        // 初始化驱动
        $driverConfig = $storeConfig['driver'];
        
        if (is_object($driverConfig)) {
            // 如果直接传入驱动实例
            $driver = $driverConfig;
        } elseif (is_string($driverConfig)) {
            // 如果传入驱动类名
            if (!class_exists($driverConfig)) {
                throw new \Exception("Cache driver class '{$driverConfig}' not found");
            }
            $driver = new $driverConfig();
        } else {
            throw new \Exception("Invalid driver configuration for store '{$storeName}'");
        }
        
        // 获取默认 TTL
        $ttl = isset($finalConfig['default_ttl']) ? $finalConfig['default_ttl'] : 3600;
        
        // 如果配置了 TTL 抖动，应用随机浮动
        if (isset($storeConfig['ttl_jitter'])) {
            $jitter = $storeConfig['ttl_jitter'];
            $ttl += rand(-$jitter, $jitter);
        }

        // 创建 KeyManager 实例（如果配置了）
        $keyManager = null;
        if (isset($finalConfig['key_manager'])) {
            $keyManager = new KeyManager($finalConfig['key_manager']);
        }

        // 创建 CacheKV 实例并注册到门面
        $cacheKV = new CacheKV($driver, $ttl, $keyManager);
        CacheKVFacade::setInstance($cacheKV);
        
        return $cacheKV;
    }

    /**
     * 获取默认配置
     * @return array
     */
    public static function getDefaultConfig()
    {
        return require __DIR__ . '/Config/cachekv.php';
    }

    /**
     * 创建缓存管理器实例
     * @param array|null $config 配置数组
     * @return CacheManager
     */
    public static function createManager($config = null)
    {
        $finalConfig = $config ?: self::getDefaultConfig();
        return new CacheManager($finalConfig);
    }
}
