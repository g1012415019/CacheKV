<?php

namespace Asfop\CacheKV\Core;

use Asfop\CacheKV\Drivers\RedisDriver;
use Asfop\CacheKV\Key\KeyManager;

/**
 * CacheKV 工厂类
 * 
 * 简化的工厂类，只提供核心功能
 */
class CacheKVFactory
{
    /**
     * Redis 实例提供者
     * 
     * @var callable
     */
    private static $redisProvider = null;
    
    /**
     * CacheKV 实例
     * 
     * @var CacheKV
     */
    private static $instance = null;

    /**
     * 配置 CacheKV（唯一的配置入口）
     * 
     * @param callable $redisProvider Redis 实例提供者闭包
     * @param string|null $configFile 配置文件路径
     * @throws CacheException 当配置文件不存在或格式错误时
     */
    public static function configure(callable $redisProvider, $configFile = null)
    {
        // 加载配置文件（如果失败会抛出异常）
        ConfigManager::loadConfig($configFile);
        
        // 保存 Redis 提供者
        self::$redisProvider = $redisProvider;
        
        // 重置实例，强制重新创建
        self::$instance = null;
        
        // 设置 KeyManager 配置
        $keyManagerConfig = ConfigManager::getKeyManagerConfig();
        KeyManager::injectGlobalConfig($keyManagerConfig);
        
        // 初始化统计系统的Redis驱动
        if (self::$redisProvider !== null && is_callable(self::$redisProvider)) {
            $redisProvider = self::$redisProvider;
            $redis = $redisProvider();
            \Asfop\CacheKV\Stats\KeyStats::setDriver($redis);
        }
    }

    /**
     * 获取 CacheKV 实例（唯一的实例获取入口）
     * 
     * @return CacheKV
     * @throws \RuntimeException
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            if (self::$redisProvider === null) {
                throw new \RuntimeException('Redis provider not configured. Call configure() first.');
            }
            
            // 创建 Redis 驱动
            $redis = call_user_func(self::$redisProvider);
            $driver = new RedisDriver($redis);
            
            // 创建 CacheKV 实例（不再传递配置，直接从 ConfigManager 获取）
            self::$instance = new CacheKV($driver);
        }
        
        return self::$instance;
    }

    /**
     * 重置工厂状态（主要用于测试）
     */
    public static function reset()
    {
        self::$redisProvider = null;
        self::$instance = null;
        
        // 同时重置相关组件
        ConfigManager::reset();
        KeyManager::reset();
    }
}
