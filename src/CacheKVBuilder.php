<?php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\KeyManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;

/**
 * CacheKV 构建器
 * 
 * 提供流畅的 API 来配置和创建 CacheKV 实例
 */
class CacheKVBuilder
{
    private $driver;
    private $ttl = 3600;
    private $keyManagerConfig = [];
    
    /**
     * 设置缓存驱动
     * 
     * @param \Asfop\CacheKV\Cache\CacheDriver $driver
     * @return self
     */
    public function driver($driver)
    {
        $this->driver = $driver;
        return $this;
    }
    
    /**
     * 使用 Array 驱动
     * 
     * @return self
     */
    public function useArrayDriver()
    {
        $this->driver = new ArrayDriver();
        return $this;
    }
    
    /**
     * 使用 Redis 驱动
     * 
     * @param mixed $redis Redis 客户端实例
     * @return self
     */
    public function useRedisDriver($redis)
    {
        $this->driver = CacheKVFactory::createRedisDriver($redis);
        return $this;
    }
    
    /**
     * 设置默认 TTL
     * 
     * @param int $ttl
     * @return self
     */
    public function ttl($ttl)
    {
        $this->ttl = $ttl;
        return $this;
    }
    
    /**
     * 设置应用前缀
     * 
     * @param string $prefix
     * @return self
     */
    public function appPrefix($prefix)
    {
        $this->keyManagerConfig['app_prefix'] = $prefix;
        return $this;
    }
    
    /**
     * 设置环境前缀
     * 
     * @param string $prefix
     * @return self
     */
    public function envPrefix($prefix)
    {
        $this->keyManagerConfig['env_prefix'] = $prefix;
        return $this;
    }
    
    /**
     * 设置版本
     * 
     * @param string $version
     * @return self
     */
    public function version($version)
    {
        $this->keyManagerConfig['version'] = $version;
        return $this;
    }
    
    /**
     * 设置模板
     * 
     * @param array $templates
     * @return self
     */
    public function templates(array $templates)
    {
        $this->keyManagerConfig['templates'] = $templates;
        return $this;
    }
    
    /**
     * 添加单个模板
     * 
     * @param string $name
     * @param string $pattern
     * @return self
     */
    public function template($name, $pattern)
    {
        if (!isset($this->keyManagerConfig['templates'])) {
            $this->keyManagerConfig['templates'] = [];
        }
        $this->keyManagerConfig['templates'][$name] = $pattern;
        return $this;
    }
    
    /**
     * 设置键管理器配置
     * 
     * @param array $config
     * @return self
     */
    public function keyManager(array $config)
    {
        $this->keyManagerConfig = array_merge($this->keyManagerConfig, $config);
        return $this;
    }
    
    /**
     * 构建 CacheKV 实例
     * 
     * @return CacheKV
     * @throws \InvalidArgumentException 当配置无效时
     */
    public function build()
    {
        if (!$this->driver) {
            throw new \InvalidArgumentException('Driver is required. Use driver(), useArrayDriver(), or useRedisDriver()');
        }
        
        $keyManager = null;
        if (!empty($this->keyManagerConfig)) {
            $keyManager = new KeyManager($this->keyManagerConfig);
        }
        
        return new CacheKV($this->driver, $this->ttl, $keyManager);
    }
    
    /**
     * 创建新的构建器实例
     * 
     * @return self
     */
    public static function create()
    {
        return new self();
    }
}
