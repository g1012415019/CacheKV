<?php

namespace Asfop\CacheKV\Configuration;

/**
 * 键配置类 - 重新设计
 * 
 * 持有 CacheConfig 对象
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
     * 键类型 (kv|other)
     * 
     * @var string
     */
    private $type;
    
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
     * @param string $type 键类型
     * @param string|null $description 键描述
     * @param CacheConfig|null $cacheConfig 缓存配置对象
     */
    public function __construct($name, $template, $type, $description = null, $cacheConfig = null)
    {
        $this->name = $name;
        $this->template = $template;
        $this->type = $type;
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
     * 获取键类型
     * 
     * @return string
     */
    public function getType()
    {
        return $this->type;
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
     * 检查是否为KV类型
     * 
     * @return bool
     */
    public function isKvType()
    {
        return $this->type === 'kv';
    }

    /**
     * 检查是否为其他类型
     * 
     * @return bool
     */
    public function isOtherType()
    {
        return $this->type === 'other';
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
     * @param string $type 键类型 (kv|other)
     * @param array|null $globalCacheConfig 全局缓存配置
     * @param array|null $groupCacheConfig 组级缓存配置
     * @return KeyConfig
     * @throws \InvalidArgumentException 当配置格式不正确时
     */
    public static function fromArray($keyName, array $config, $type = 'kv', $globalCacheConfig = null, $groupCacheConfig = null)
    {
        // 验证必要的配置项
        if (!isset($config['template'])) {
            throw new \InvalidArgumentException("Missing required 'template' in key '{$keyName}' configuration");
        }
        
        $template = $config['template'];
        $description = isset($config['description']) ? $config['description'] : null;
        $cacheConfig = null;
        
        // 只有KV类型的键才处理缓存配置
        if ($type === 'kv') {
            // 按优先级合并配置：全局配置 -> 组级配置 -> 键级配置
            $mergedCacheConfig = array();
            
            // 1. 先应用全局配置
            if ($globalCacheConfig !== null && is_array($globalCacheConfig)) {
                $mergedCacheConfig = $globalCacheConfig;
            }
            
            // 2. 再应用组级配置（覆盖全局配置）
            if ($groupCacheConfig !== null && is_array($groupCacheConfig)) {
                $mergedCacheConfig = array_merge($mergedCacheConfig, $groupCacheConfig);
            }
            
            // 3. 最后应用键级配置（覆盖组级配置）
            if (isset($config['cache']) && is_array($config['cache'])) {
                $mergedCacheConfig = array_merge($mergedCacheConfig, $config['cache']);
            }
            
            // 如果有任何配置，则创建 CacheConfig 对象
            if (!empty($mergedCacheConfig)) {
                $cacheConfig = CacheConfig::fromArray($mergedCacheConfig);
            }
        }
        
        return new self($keyName, $template, $type, $description, $cacheConfig);
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
            'type' => $this->type,
            'description' => $this->description
        );
        
        if ($this->cacheConfig !== null) {
            $result['cache'] = $this->cacheConfig->toArray();
        }
        
        return $result;
    }
}
