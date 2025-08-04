<?php

namespace Asfop\CacheKV\Configuration;

/**
 * 分组配置类 - 简化版
 * 
 * 只保留核心功能，删除重复的便捷方法
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
     * KV类型的键配置
     * 
     * @var array
     */
    private $kvKeys;
    
    /**
     * 其他类型的键配置
     * 
     * @var array
     */
    private $otherKeys;

    /**
     * 构造函数
     * 
     * @param string $name 分组名称
     * @param string $prefix 分组前缀
     * @param string $version 分组版本
     * @param string|null $description 分组描述
     * @param array|null $cacheConfig 分组级缓存配置
     * @param array $kvKeys KV类型的键配置
     * @param array $otherKeys 其他类型的键配置
     */
    public function __construct($name, $prefix, $version, $description = null, $cacheConfig = null, array $kvKeys = array(), array $otherKeys = array())
    {
        $this->name = $name;
        $this->prefix = $prefix;
        $this->version = $version;
        $this->description = $description;
        $this->cacheConfig = $cacheConfig;
        $this->kvKeys = $kvKeys;
        $this->otherKeys = $otherKeys;
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
     * 获取KV类型的键配置
     * 
     * @return array
     */
    public function getKvKeys()
    {
        return $this->kvKeys;
    }

    /**
     * 获取其他类型的键配置
     * 
     * @return array
     */
    public function getOtherKeys()
    {
        return $this->otherKeys;
    }

    /**
     * 获取指定的键配置（KV或其他类型）
     * 
     * @param string $keyName 键名称
     * @return KeyConfig|null
     */
    public function getKey($keyName)
    {
        if (isset($this->kvKeys[$keyName])) {
            return $this->kvKeys[$keyName];
        }
        
        if (isset($this->otherKeys[$keyName])) {
            return $this->otherKeys[$keyName];
        }
        
        return null;
    }

    /**
     * 获取指定的KV键配置
     * 
     * @param string $keyName 键名称
     * @return KeyConfig|null
     */
    public function getKvKey($keyName)
    {
        return isset($this->kvKeys[$keyName]) ? $this->kvKeys[$keyName] : null;
    }

    /**
     * 获取指定的其他类型键配置
     * 
     * @param string $keyName 键名称
     * @return KeyConfig|null
     */
    public function getOtherKey($keyName)
    {
        return isset($this->otherKeys[$keyName]) ? $this->otherKeys[$keyName] : null;
    }

    /**
     * 检查键是否存在
     * 
     * @param string $keyName 键名称
     * @return bool
     */
    public function hasKey($keyName)
    {
        return isset($this->kvKeys[$keyName]) || isset($this->otherKeys[$keyName]);
    }

    /**
     * 检查KV键是否存在
     * 
     * @param string $keyName 键名称
     * @return bool
     */
    public function hasKvKey($keyName)
    {
        return isset($this->kvKeys[$keyName]);
    }

    /**
     * 检查其他类型键是否存在
     * 
     * @param string $keyName 键名称
     * @return bool
     */
    public function hasOtherKey($keyName)
    {
        return isset($this->otherKeys[$keyName]);
    }

    /**
     * 检查键是否为KV类型
     * 
     * @param string $keyName 键名称
     * @return bool
     */
    public function isKvKey($keyName)
    {
        return $this->hasKvKey($keyName);
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
        
        $kvKeys = array();
        $otherKeys = array();
        
        // 解析键配置
        if (isset($config['keys']) && is_array($config['keys'])) {
            // 解析KV类型的键
            if (isset($config['keys']['kv']) && is_array($config['keys']['kv'])) {
                foreach ($config['keys']['kv'] as $keyName => $keyConfig) {
                    // 传递全局配置和组级配置给 KeyConfig
                    $kvKeys[$keyName] = KeyConfig::fromArray($keyName, $keyConfig, 'kv', $globalCacheConfig, $cacheConfig);
                }
            }
            
            // 解析其他类型的键
            if (isset($config['keys']['other']) && is_array($config['keys']['other'])) {
                foreach ($config['keys']['other'] as $keyName => $keyConfig) {
                    // 其他类型的键不需要缓存配置
                    $otherKeys[$keyName] = KeyConfig::fromArray($keyName, $keyConfig, 'other');
                }
            }
        }
        
        return new self($groupName, $prefix, $version, $description, $cacheConfig, $kvKeys, $otherKeys);
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
        if (!empty($this->kvKeys)) {
            $result['keys']['kv'] = array();
            foreach ($this->kvKeys as $keyName => $keyConfig) {
                $result['keys']['kv'][$keyName] = $keyConfig->toArray();
            }
        }
        
        if (!empty($this->otherKeys)) {
            $result['keys']['other'] = array();
            foreach ($this->otherKeys as $keyName => $keyConfig) {
                $result['keys']['other'][$keyName] = $keyConfig->toArray();
            }
        }
        
        return $result;
    }
}
