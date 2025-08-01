<?php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

/**
 * CacheKV 工厂类
 * 
 * 提供多种方式创建 CacheKV 实例：
 * 1. 直接创建（推荐）
 * 2. 使用配置数组创建
 * 3. 快速创建（开发测试用）
 */
class CacheKVFactory
{
    /**
     * 直接创建 CacheKV 实例（推荐方式）
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
     * @throws \InvalidArgumentException 当配置无效时
     */
    public static function createFromConfig(array $config)
    {
        // 验证配置
        if (!isset($config['driver'])) {
            throw new \InvalidArgumentException('Driver is required in config');
        }
        
        $driver = $config['driver'];
        $ttl = $config['ttl'] ?? 3600;
        
        // 创建键管理器
        $keyManager = null;
        if (isset($config['key_manager'])) {
            $keyManager = self::createKeyManager($config['key_manager']);
        }
        
        return new CacheKV($driver, $ttl, $keyManager);
    }
    
    /**
     * 快速创建 CacheKV 实例（主要用于开发测试）
     * 
     * @param string $appPrefix 应用前缀
     * @param string $envPrefix 环境前缀
     * @param array $templates 模板配置
     * @param int $ttl 默认TTL
     * @return CacheKV
     */
    public static function quick($appPrefix = 'app', $envPrefix = 'dev', $templates = [], $ttl = 3600)
    {
        $driver = new ArrayDriver();
        
        $keyManager = new KeyManager([
            'app_prefix' => $appPrefix,
            'env_prefix' => $envPrefix,
            'version' => 'v1',
            'templates' => $templates
        ]);
        
        return new CacheKV($driver, $ttl, $keyManager);
    }
    
    /**
     * 创建键管理器
     * 
     * @param array $config 键管理器配置
     * @return KeyManager
     */
    public static function createKeyManager(array $config)
    {
        return new KeyManager($config);
    }
    
    /**
     * 创建 ArrayDriver 实例
     * 
     * @return \Asfop\CacheKV\Cache\Drivers\ArrayDriver
     */
    public static function createArrayDriver()
    {
        return new ArrayDriver();
    }
    
    /**
     * 创建 RedisDriver 实例
     * 
     * @param mixed $redis Redis 客户端实例
     * @return \Asfop\CacheKV\Cache\Drivers\RedisDriver
     * @throws \InvalidArgumentException 当 Redis 客户端无效时
     */
    public static function createRedisDriver($redis)
    {
        if (!class_exists('\Asfop\CacheKV\Cache\Drivers\RedisDriver')) {
            throw new \InvalidArgumentException('RedisDriver class not found');
        }
        
        return new \Asfop\CacheKV\Cache\Drivers\RedisDriver($redis);
    }
}
