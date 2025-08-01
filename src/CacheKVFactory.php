<?php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;

class CacheKVFactory
{
    private static $instances = [];
    private static $defaultConfig = null;

    /**
     * 设置默认配置
     */
    public static function setDefaultConfig(array $config)
    {
        self::$defaultConfig = $config;
    }

    /**
     * 获取默认实例
     */
    public static function create($name = 'default')
    {
        if (!isset(self::$instances[$name])) {
            if (self::$defaultConfig === null) {
                throw new \RuntimeException('请先调用 setDefaultConfig() 设置默认配置');
            }

            $config = self::$defaultConfig;
            
            // 创建 KeyManager
            $keyManager = new KeyManager([
                'app_prefix' => $config['key_manager']['app_prefix'] ?? '',
                'env_prefix' => $config['key_manager']['env_prefix'] ?? '',
                'version' => $config['key_manager']['version'] ?? 'v1',
                'templates' => $config['key_manager']['templates'] ?? []
            ]);

            // 创建驱动
            $storeConfig = $config['stores'][$config['default']] ?? $config['stores']['array'];
            $driver = $storeConfig['driver'];
            $ttl = $storeConfig['ttl'] ?? 3600;

            // 创建 CacheKV 实例
            self::$instances[$name] = new CacheKV($driver, $ttl, $keyManager);
        }

        return self::$instances[$name];
    }

    /**
     * 快速创建方法（不影响全局配置）
     */
    public static function quick($appPrefix = 'app', $envPrefix = 'dev', $templates = [])
    {
        // 直接创建实例，不影响全局配置
        $keyManager = new KeyManager([
            'app_prefix' => $appPrefix,
            'env_prefix' => $envPrefix,
            'version' => 'v1',
            'templates' => $templates
        ]);

        $driver = new ArrayDriver();
        $ttl = 3600;

        return new CacheKV($driver, $ttl, $keyManager);
    }
}
