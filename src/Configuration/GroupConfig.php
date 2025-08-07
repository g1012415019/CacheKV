<?php

namespace Asfop\CacheKV\Configuration;

/**
 * 分组配置类 - 简化版
 * 
 * 移除 kv/other 区分，统一为 keys 结构
 * 兼容 PHP 7.0
 */
class GroupConfig
{
    /**
     * 分组名称
     * 
     * @var string
     */
    private $name;
    
    /**
     * 分组前缀
     * 
     * @var string
     */
    private $prefix;
    
    /**
     * 分组版本
     * 
     * @var string
     */
    private $version;
    
    /**
     * 分组描述
     * 
     * @var string|null
     */
    private $description;
    
    /**
     * 分组级缓存配置
     * 
     * @var array|null
     */
    private $cacheConfig;
    
    /**
     * 键配置
     * 
     * @var array
     */
    private $keys;

    /**
     * 构造函数
     * 
     * @param string $name 分组名称
     * @param string $prefix 分组前缀
     * @param string $version 分组版本
     * @param string|null $description 分组描述
     * @param array|null $cacheConfig 分组级缓存配置
     * @param array $keys 键配置
     */
    public function __construct($name, $prefix, $version, $description = null, $cacheConfig = null, array $keys = array())
    {
        $this->name = $name;
        $this->prefix = $prefix;
        $this->version = $version;
        $this->description = $description;
        $this->cacheConfig = $cacheConfig;
        $this->keys = $keys;
    }

    /**
     * 获取分组名称
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 获取分组前缀
     * 
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * 获取分组版本
     * 
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * 获取分组描述
     * 
     * @return string|null
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * 获取分组级缓存配置
     * 
     * @return array|null
     */
    public function getCacheConfig()
    {
        return $this->cacheConfig;
    }

    /**
     * 获取所有键配置
     * 
     * @return array
     */
    public function getKeys()
    {
        return $this->keys;
    }

    /**
     * 获取指定的键配置
     * 
     * @param string $keyName 键名称
     * @return KeyConfig|null
     */
    public function getKey($keyName)
    {
        return isset($this->keys[$keyName]) ? $this->keys[$keyName] : null;
    }

    /**
     * 检查键是否存在
     * 
     * @param string $keyName 键名称
     * @return bool
     */
    public function hasKey($keyName)
    {
        return isset($this->keys[$keyName]);
    }

    /**
     * 检查键是否有缓存配置（用于判断是否应用缓存逻辑）
     * 
     * @param string $keyName 键名称
     * @return bool
     */
    public function hasKeyCache($keyName)
    {
        if (!isset($this->keys[$keyName])) {
            return false;
        }
        
        return $this->keys[$keyName]->hasCacheConfig();
    }

    /**
     * 从数组创建配置实例
     * 
     * @param string $groupName 分组名称
     * @param array $config 配置数组
     * @param array|null $globalCacheConfig 全局缓存配置
     * @return GroupConfig
     * @throws \InvalidArgumentException 当配置格式不正确时
     */
    public static function fromArray($groupName, array $config, $globalCacheConfig = null)
    {
        // 验证必要的配置项
        if (!isset($config['prefix'])) {
            throw new \InvalidArgumentException("Missing required 'prefix' in group '{$groupName}' configuration");
        }
        
        if (!isset($config['version'])) {
            throw new \InvalidArgumentException("Missing required 'version' in group '{$groupName}' configuration");
        }
        
        $prefix = $config['prefix'];
        $version = $config['version'];
        $description = isset($config['description']) ? $config['description'] : null;
        $cacheConfig = isset($config['cache']) ? $config['cache'] : null;
        
        $keys = array();
        
        // 解析键配置
        if (isset($config['keys']) && is_array($config['keys'])) {
            foreach ($config['keys'] as $keyName => $keyConfig) {
                // 判断键是否有缓存配置来决定类型
                $hasCache = isset($keyConfig['cache']) && is_array($keyConfig['cache']);
                $keyType = $hasCache ? 'kv' : 'other';
                
                // 传递全局配置和组级配置给 KeyConfig
                $keys[$keyName] = KeyConfig::fromArray($keyName, $keyConfig, $keyType, $globalCacheConfig, $cacheConfig);
            }
        }
        
        return new self($groupName, $prefix, $version, $description, $cacheConfig, $keys);
    }

    /**
     * 转换为数组格式
     * 
     * @return array
     */
    public function toArray()
    {
        $result = array(
            'name' => $this->name,
            'prefix' => $this->prefix,
            'version' => $this->version,
            'description' => $this->description
        );
        
        if ($this->cacheConfig !== null) {
            $result['cache'] = $this->cacheConfig;
        }
        
        // 转换键配置
        if (!empty($this->keys)) {
            $result['keys'] = array();
            foreach ($this->keys as $keyName => $keyConfig) {
                $result['keys'][$keyName] = $keyConfig->toArray();
            }
        }
        
        return $result;
    }

    // ==================== 向后兼容方法 ====================
    
    /**
     * 获取KV类型的键配置（向后兼容）
     * 
     * @return array
     */
    public function getKvKeys()
    {
        $kvKeys = array();
        foreach ($this->keys as $keyName => $keyConfig) {
            if ($keyConfig->isKvType()) {
                $kvKeys[$keyName] = $keyConfig;
            }
        }
        return $kvKeys;
    }

    /**
     * 获取其他类型的键配置（向后兼容）
     * 
     * @return array
     */
    public function getOtherKeys()
    {
        $otherKeys = array();
        foreach ($this->keys as $keyName => $keyConfig) {
            if (!$keyConfig->isKvType()) {
                $otherKeys[$keyName] = $keyConfig;
            }
        }
        return $otherKeys;
    }

    /**
     * 检查KV键是否存在（向后兼容）
     * 
     * @param string $keyName 键名称
     * @return bool
     */
    public function hasKvKey($keyName)
    {
        return isset($this->keys[$keyName]) && $this->keys[$keyName]->isKvType();
    }

    /**
     * 检查键是否为KV类型（向后兼容）
     * 
     * @param string $keyName 键名称
     * @return bool
     */
    public function isKvKey($keyName)
    {
        return $this->hasKvKey($keyName);
    }
}
