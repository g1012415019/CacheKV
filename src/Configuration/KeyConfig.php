<?php

namespace Asfop\CacheKV\Configuration;

/**
 * 键配置类 - 简化版
 * 
 * 移除类型概念，通过是否有缓存配置来判断行为
 * 兼容 PHP 7.0
 */
class KeyConfig
{
    /**
     * 键名称
     * 
     * @var string
     */
    private $name;
    
    /**
     * 键模板
     * 
     * @var string
     */
    private $template;
    
    /**
     * 键描述
     * 
     * @var string|null
     */
    private $description;
    
    /**
     * 缓存配置对象
     * 
     * @var CacheConfig|null
     */
    private $cacheConfig;

    /**
     * 构造函数
     * 
     * @param string $name 键名称
     * @param string $template 键模板
     * @param string|null $description 键描述
     * @param CacheConfig|null $cacheConfig 缓存配置对象
     */
    public function __construct($name, $template, $description = null, $cacheConfig = null)
    {
        $this->name = $name;
        $this->template = $template;
        $this->description = $description;
        $this->cacheConfig = $cacheConfig;
    }

    /**
     * 获取键名称
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 获取键模板
     * 
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * 获取键描述
     * 
     * @return string|null
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * 获取缓存配置对象
     * 
     * @return CacheConfig|null
     */
    public function getCacheConfig()
    {
        return $this->cacheConfig;
    }

    /**
     * 检查是否有缓存配置
     * 
     * @return bool
     */
    public function hasCacheConfig()
    {
        return $this->cacheConfig !== null;
    }

    /**
     * 从数组创建配置实例
     * 
     * @param string $keyName 键名称
     * @param array $config 配置数组
     * @param array|null $globalCacheConfig 全局缓存配置
     * @param array|null $groupCacheConfig 组级缓存配置
     * @return KeyConfig
     * @throws \InvalidArgumentException 当配置格式不正确时
     */
    public static function fromArray($keyName, array $config, $globalCacheConfig = null, $groupCacheConfig = null)
    {
        // 验证必要的配置项
        if (!isset($config['template'])) {
            throw new \InvalidArgumentException("Missing required 'template' in key '{$keyName}' configuration");
        }
        
        $template = $config['template'];
        $description = isset($config['description']) ? $config['description'] : null;
        
        // 创建缓存配置对象
        $cacheConfig = null;
        if (isset($config['cache']) && is_array($config['cache'])) {
            // 只有键明确有缓存配置时才创建 CacheConfig 对象
            $cacheConfig = CacheConfig::fromArray($config['cache'], $globalCacheConfig, $groupCacheConfig);
        }
        // 注意：如果键没有明确的cache配置，我们不创建CacheConfig对象
        // 这样 hasCacheConfig() 会返回 false，表示该键不应用缓存逻辑
        
        return new self($keyName, $template, $description, $cacheConfig);
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
            'template' => $this->template,
            'description' => $this->description
        );
        
        if ($this->cacheConfig !== null) {
            $result['cache'] = $this->cacheConfig->toArray();
        }
        
        return $result;
    }
}
