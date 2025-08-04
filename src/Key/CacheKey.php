<?php

namespace Asfop\CacheKV\Key;

use Asfop\CacheKV\Configuration\GroupConfig;
use Asfop\CacheKV\Configuration\KeyConfig;
use Asfop\CacheKV\Configuration\CacheConfig;

/**
 * 缓存键对象 - 简化版
 * 
 * 建立对象关系：CacheKey -> GroupConfig -> KeyConfig -> CacheConfig
 * 只保留核心方法，避免重复的配置读取
 * 兼容 PHP 7.0
 */
class CacheKey
{
    /**
     * 组名
     * 
     * @var string
     */
    private $groupName;
    
    /**
     * 键名
     * 
     * @var string
     */
    private $keyName;
    
    /**
     * 参数
     * 
     * @var array
     */
    private $params;
    
    /**
     * 当前组的配置对象
     * 
     * @var GroupConfig
     */
    private $groupConfig;
    
    /**
     * 当前键的配置对象
     * 
     * @var KeyConfig|null
     */
    private $keyConfig;
    
    /**
     * 完整键字符串（缓存）
     * 
     * @var string|null
     */
    private $fullKey;

    /**
     * 构造函数
     * 
     * @param string $groupName 组名
     * @param string $keyName 键名
     * @param array $params 参数
     * @param GroupConfig $groupConfig 当前组的配置对象
     * @param KeyConfig|null $keyConfig 当前键的配置对象
     * @param string|null $fullKey 完整键字符串
     */
    public function __construct($groupName, $keyName, array $params, $groupConfig, $keyConfig = null, $fullKey = null)
    {
        $this->groupName = $groupName;
        $this->keyName = $keyName;
        $this->params = $params;
        $this->groupConfig = $groupConfig;
        $this->keyConfig = $keyConfig;
        $this->fullKey = $fullKey;
    }

    /**
     * 获取组名
     * 
     * @return string
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * 获取键名
     * 
     * @return string
     */
    public function getKeyName()
    {
        return $this->keyName;
    }

    /**
     * 获取参数
     * 
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * 获取当前组的配置对象
     * 
     * @return GroupConfig
     */
    public function getGroupConfig()
    {
        return $this->groupConfig;
    }

    /**
     * 获取当前键的配置对象
     * 
     * @return KeyConfig|null
     */
    public function getKeyConfig()
    {
        return $this->keyConfig;
    }

    /**
     * 获取缓存配置对象（通过 KeyConfig）
     * 
     * @return CacheConfig|null
     */
    public function getCacheConfig()
    {
        return $this->keyConfig !== null ? $this->keyConfig->getCacheConfig() : null;
    }

    /**
     * 检查是否为KV类型的键
     * 
     * @return bool
     */
    public function isKvKey()
    {
        return $this->keyConfig !== null && $this->keyConfig->isKvType();
    }

    /**
     * 检查是否启用统计（最常用的配置）
     * 
     * @return bool
     */
    public function isStatsEnabled()
    {
        $cacheConfig = $this->getCacheConfig();
        return $cacheConfig !== null ? $cacheConfig->isEnableStats(false) : false;
    }

    /**
     * 转换为字符串
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->fullKey !== null ? $this->fullKey : "{$this->groupName}.{$this->keyName}";
    }

    /**
     * 转换为数组格式（用于调试）
     * 
     * @return array
     */
    public function toArray()
    {
        $result = array(
            'group_name' => $this->groupName,
            'key_name' => $this->keyName,
            'params' => $this->params,
            'full_key' => $this->__toString()
        );
        
        // 添加分组信息
        $result['group_info'] = array(
            'name' => $this->groupConfig->getName(),
            'prefix' => $this->groupConfig->getPrefix(),
            'version' => $this->groupConfig->getVersion(),
            'description' => $this->groupConfig->getDescription()
        );
        
        // 添加键信息
        if ($this->keyConfig !== null) {
            $result['key_info'] = array(
                'name' => $this->keyConfig->getName(),
                'template' => $this->keyConfig->getTemplate(),
                'type' => $this->keyConfig->getType(),
                'description' => $this->keyConfig->getDescription(),
                'has_cache_config' => $this->keyConfig->hasCacheConfig()
            );
            
            // 添加缓存配置信息（完整的配置数组）
            $cacheConfig = $this->keyConfig->getCacheConfig();
            if ($cacheConfig !== null) {
                $result['cache_info'] = $cacheConfig->toArray();
            }
        } else {
            $result['key_info'] = null;
        }
        
        return $result;
    }
}
