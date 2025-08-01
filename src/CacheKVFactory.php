<?php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

/**
 * CacheKV 工厂类
 * 
 * 提供简洁的创建方式，同时支持灵活配置
 */
class CacheKVFactory
{
    /**
     * @var CacheKV 默认缓存实例
     */
    private static $defaultInstance;
    
    /**
     * @var array 默认配置
     */
    private static $defaultConfig = [
        'driver' => null,
        'ttl' => 3600,
        'app_prefix' => 'app',
        'env_prefix' => 'dev',
        'version' => 'v1',
        'templates' => []
    ];
    
    /**
     * 设置默认配置（可选，不设置则使用内置默认值）
     * 
     * @param array $config 配置数组
     */
    public static function setDefaultConfig(array $config)
    {
        self::$defaultConfig = array_merge(self::$defaultConfig, $config);
        self::$defaultInstance = null; // 重置实例
    }
    
    /**
     * 获取默认缓存实例（单例模式）
     * 
     * @return CacheKV
     */
    public static function getInstance()
    {
        if (self::$defaultInstance === null) {
            self::$defaultInstance = self::createFromDefaultConfig();
        }
        
        return self::$defaultInstance;
    }
    
    /**
     * 直接创建 CacheKV 实例
     * 
     * @param \Asfop\CacheKV\Cache\CacheDriver $driver 缓存驱动
     * @param int $ttl 默认TTL
     * @param KeyManager|null $keyManager 键管理器
     * @return CacheKV
     */
    public static function create($driver, $ttl = 3600, KeyManager $keyManager = null)
    {
        return new CacheKV($driver, $ttl, $keyManager);
    }
    
    /**
     * 使用配置数组创建 CacheKV 实例
     * 
     * @param array $config 配置数组
     * @return CacheKV
     */
    public static function createFromConfig(array $config)
    {
        $driver = $config['driver'] ?? new ArrayDriver();
        $ttl = $config['ttl'] ?? 3600;
        
        $keyManager = null;
        if (isset($config['key_manager'])) {
            $keyManager = new KeyManager($config['key_manager']);
        } elseif (isset($config['templates'])) {
            // 简化配置：直接传 templates
            $keyManager = new KeyManager([
                'app_prefix' => $config['app_prefix'] ?? 'app',
                'env_prefix' => $config['env_prefix'] ?? 'dev',
                'version' => $config['version'] ?? 'v1',
                'templates' => $config['templates']
            ]);
        }
        
        return new CacheKV($driver, $ttl, $keyManager);
    }
    
    /**
     * 快速创建（最简单的方式）
     * 
     * @param array $templates 模板配置
     * @param array $options 可选配置
     * @return CacheKV
     */
    public static function quick(array $templates, array $options = [])
    {
        $config = array_merge([
            'driver' => new ArrayDriver(),
            'ttl' => 3600,
            'app_prefix' => 'app',
            'env_prefix' => 'dev',
            'version' => 'v1',
            'templates' => $templates
        ], $options);
        
        return self::createFromConfig($config);
    }
    
    /**
     * 从默认配置创建实例
     * 
     * @return CacheKV
     */
    private static function createFromDefaultConfig()
    {
        $config = self::$defaultConfig;
        
        if ($config['driver'] === null) {
            $config['driver'] = new ArrayDriver();
        }
        
        return self::createFromConfig($config);
    }
}
