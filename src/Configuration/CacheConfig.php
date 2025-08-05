<?php

namespace Asfop\CacheKV\Configuration;

/**
 * 缓存配置对象 - 简化版
 * 
 * 使用通用的 getter 模式减少重复代码
 * 兼容 PHP 7.0
 */
class CacheConfig
{
    /**
     * 配置数据
     * 
     * @var array
     */
    private $config;

    /**
     * 默认配置值
     * 
     * @var array
     */
    private static $defaults = array(
        'ttl' => 3600,
        'null_cache_ttl' => 300,
        'enable_null_cache' => true,
        'ttl_random_range' => 300,
        'enable_stats' => true,
        'stats_prefix' => 'cachekv:stats:',
        'stats_ttl' => 604800,
        'hot_key_auto_renewal' => true,
        'hot_key_threshold' => 100,
        'hot_key_extend_ttl' => 7200,
        'hot_key_max_ttl' => 86400,
        'tag_prefix' => 'tag:'
    );

    /**
     * 构造函数
     * 
     * @param array $config 配置数组
     */
    public function __construct(array $config = array())
    {
        $this->config = $config;
    }

    /**
     * 通用配置获取方法
     * 
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @param string $type 类型转换 (int|bool|string)
     * @return mixed
     */
    private function getValue($key, $default = null, $type = 'string')
    {
        $value = isset($this->config[$key]) ? $this->config[$key] : 
                 (isset(self::$defaults[$key]) ? self::$defaults[$key] : $default);
        
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'bool':
                return (bool)$value;
            default:
                return (string)$value;
        }
    }

    // ==================== 基础缓存配置 ====================
    
    public function getTtl($default = null) { return $this->getValue('ttl', $default, 'int'); }
    public function getNullCacheTtl($default = null) { return $this->getValue('null_cache_ttl', $default, 'int'); }
    public function isEnableNullCache($default = null) { return $this->getValue('enable_null_cache', $default, 'bool'); }
    public function getTtlRandomRange($default = null) { return $this->getValue('ttl_random_range', $default, 'int'); }

    // ==================== 统计配置 ====================
    
    public function isEnableStats($default = null) { return $this->getValue('enable_stats', $default, 'bool'); }
    public function getStatsPrefix($default = null) { return $this->getValue('stats_prefix', $default, 'string'); }
    public function getStatsTtl($default = null) { return $this->getValue('stats_ttl', $default, 'int'); }

    // ==================== 热点键自动续期配置 ====================
    
    public function isHotKeyAutoRenewal($default = null) { return $this->getValue('hot_key_auto_renewal', $default, 'bool'); }
    public function getHotKeyThreshold($default = null) { return $this->getValue('hot_key_threshold', $default, 'int'); }
    public function getHotKeyExtendTtl($default = null) { return $this->getValue('hot_key_extend_ttl', $default, 'int'); }
    public function getHotKeyMaxTtl($default = null) { return $this->getValue('hot_key_max_ttl', $default, 'int'); }

    // ==================== 标签配置 ====================
    
    public function getTagPrefix($default = null) { return $this->getValue('tag_prefix', $default, 'string'); }

    // ==================== 通用方法 ====================

    /**
     * 获取指定配置项
     * 
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * 检查配置项是否存在
     * 
     * @param string $key 配置键
     * @return bool
     */
    public function has($key)
    {
        return isset($this->config[$key]);
    }

    /**
     * 获取所有配置
     * 
     * @return array
     */
    public function toArray()
    {
        return $this->config;
    }

    /**
     * 从数组创建配置对象
     * 
     * @param array $config 配置数组
     * @return CacheConfig
     */
    public static function fromArray(array $config)
    {
        return new self($config);
    }

    /**
     * 合并配置（用于配置继承）
     * 
     * @param array $globalConfig 全局配置
     * @param array $groupConfig 组级配置
     * @param array $keyConfig 键级配置
     * @return CacheConfig
     */
    public static function merge(array $globalConfig = array(), array $groupConfig = array(), array $keyConfig = array())
    {
        return new self(array_merge($globalConfig, $groupConfig, $keyConfig));
    }
}
