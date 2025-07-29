<?php
// src/CacheKVServiceProvider.php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFacade;

class CacheKVServiceProvider
{
    /**
     * 初始化 CacheKV 并注册到门面
     * @param array|null $config 可选的配置覆盖
     */
    public static function register(?array $config = null): void
    {
        // 加载默认配置
        $defaultConfig = require __DIR__ . '/config/cachekv.php';
        
        // 如果传入自定义配置，则合并
        $finalConfig = $config ? array_merge($defaultConfig, $config) : $defaultConfig;
        
        // 初始化驱动
        $storeName = $finalConfig['default'];
        $driverName = $finalConfig['stores'][$storeName]['driver'];
        $driver = new $driverName();
        $ttl = $finalConfig['stores'][$storeName]['ttl'] ?? 3600;

        if (isset($finalConfig['stores'][$storeName]['ttl_jitter'])) {
        $jitter = $finalConfig['stores'][$storeName]['ttl_jitter'];
        $ttl += rand(-$jitter, $jitter); // 随机浮动
         }
        
        // 注册到门面
        $cacheKV = new CacheKV($driver, $ttl);
        CacheKVFacade::setInstance($cacheKV);
    }
}

