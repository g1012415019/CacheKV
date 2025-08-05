<?php

namespace Asfop\CacheKV\Configuration;

/**
 * KeyManager 配置类 - 简化版
 * 
 * 只保留核心功能，删除重复的便捷方法
 * 兼容 PHP 7.0
 */
class KeyManagerConfig
{
    /**
     * 应用前缀
     * 
     * @var string
     */
    private $appPrefix;
    
    /**
     * 分隔符
     * 
     * @var string
     */
    private $separator;
    
    /**
     * 分组配置
     * 
     * @var array
     */
    private $groups;

    /**
     * 构造函数
     * 
     * @param string $appPrefix 应用前缀
     * @param string $separator 分隔符
     * @param array $groups 分组配置
     */
    public function __construct($appPrefix = 'app', $separator = ':', array $groups = array())
    {
        $this->appPrefix = $appPrefix;
        $this->separator = $separator;
        $this->groups = $groups;
    }

    /**
     * 获取应用前缀
     * 
     * @return string
     */
    public function getAppPrefix()
    {
        return $this->appPrefix;
    }

    /**
     * 获取分隔符
     * 
     * @return string
     */
    public function getSeparator()
    {
        return $this->separator;
    }

    /**
     * 获取所有分组配置
     * 
     * @return array
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * 获取指定分组配置
     * 
     * @param string $groupName 分组名称
     * @return GroupConfig|null
     */
    public function getGroup($groupName)
    {
        return isset($this->groups[$groupName]) ? $this->groups[$groupName] : null;
    }

    /**
     * 检查分组是否存在
     * 
     * @param string $groupName 分组名称
     * @return bool
     */
    public function hasGroup($groupName)
    {
        return isset($this->groups[$groupName]);
    }

    /**
     * 从数组创建配置实例
     * 
     * @param array $config 配置数组
     * @param array|null $globalCacheConfig 全局缓存配置
     * @return KeyManagerConfig
     * @throws \InvalidArgumentException 当配置格式不正确时
     */
    public static function fromArray(array $config, $globalCacheConfig = null)
    {
        $appPrefix = isset($config['app_prefix']) ? $config['app_prefix'] : 'app';
        $separator = isset($config['separator']) ? $config['separator'] : ':';
        
        $groups = array();
        if (isset($config['groups']) && is_array($config['groups'])) {
            foreach ($config['groups'] as $groupName => $groupConfig) {
                // 传递全局缓存配置给 GroupConfig
                $groups[$groupName] = GroupConfig::fromArray($groupName, $groupConfig, $globalCacheConfig);
            }
        }
        
        return new self($appPrefix, $separator, $groups);
    }

    /**
     * 转换为数组格式（向后兼容）
     * 
     * @return array
     */
    public function toArray()
    {
        $result = array(
            'app_prefix' => $this->appPrefix,
            'separator' => $this->separator,
            'groups' => array()
        );
        
        foreach ($this->groups as $groupName => $groupConfig) {
            $result['groups'][$groupName] = $groupConfig->toArray();
        }
        
        return $result;
    }
}
