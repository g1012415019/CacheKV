<?php

namespace Asfop\CacheKV\Configuration;

/**
 * CacheKV 主配置类 - 简化版
 * 
 * 只保留核心功能，删除重复的便捷方法
 * 兼容 PHP 7.0
 */
class CacheKVConfig
{
    /**
     * 缓存配置
     * 
     * @var CacheConfig
     */
    private $cache;
    
    /**
     * KeyManager 配置
     * 
     * @var KeyManagerConfig
     */
    private $keyManager;

    /**
     * 构造函数
     * 
     * @param CacheConfig $cache 缓存配置
     * @param KeyManagerConfig $keyManager KeyManager配置
     */
    public function __construct(CacheConfig $cache, KeyManagerConfig $keyManager)
    {
        $this->cache = $cache;
        $this->keyManager = $keyManager;
    }

    /**
     * 获取缓存配置
     * 
     * @return CacheConfig
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * 获取 KeyManager 配置
     * 
     * @return KeyManagerConfig
     */
    public function getKeyManager()
    {
        return $this->keyManager;
    }

    /**
     * 从数组创建配置实例
     * 
     * @param array $config 配置数组
     * @return CacheKVConfig
     * @throws \InvalidArgumentException 当配置格式不正确时
     */
    public static function fromArray(array $config)
    {
        // 验证必要的配置项
        if (!isset($config['cache'])) {
            throw new \InvalidArgumentException("Missing required 'cache' configuration");
        }
        
        if (!isset($config['key_manager'])) {
            throw new \InvalidArgumentException("Missing required 'key_manager' configuration");
        }
        
        // 创建缓存配置对象
        $cacheConfig = CacheConfig::fromArray($config['cache']);
        
        // 创建 KeyManager 配置对象，传递全局缓存配置
        $keyManagerConfig = KeyManagerConfig::fromArray($config['key_manager'], $config['cache']);
        
        return new self($cacheConfig, $keyManagerConfig);
    }

    /**
     * 转换为数组格式
     * 
     * @return array
     */
    public function toArray()
    {
        return array(
            'cache' => $this->cache->toArray(),
            'key_manager' => $this->keyManager->toArray()
        );
    }
}
