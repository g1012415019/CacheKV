<?php

namespace Asfop\CacheKV\Configuration;

/**
 * 缓存配置对象 - 简化版
 * 
 * 只保留核心配置项，去掉不必要的复杂配置
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
     * 构造函数
     * 
     * @param array $config 配置数组
     */
    public function __construct(array $config = array())
    {
        $this->config = $config;
    }

    // ==================== 基础缓存配置 ====================

    /**
     * 获取TTL
     * 
     * @param int $default 默认值
     * @return int
     */
    public function getTtl($default = 3600)
    {
        return isset($this->config['ttl']) ? (int)$this->config['ttl'] : $default;
    }

    /**
     * 获取空值缓存TTL
     * 
     * @param int $default 默认值
     * @return int
     */
    public function getNullCacheTtl($default = 300)
    {
        return isset($this->config['null_cache_ttl']) ? (int)$this->config['null_cache_ttl'] : $default;
    }

    /**
     * 是否启用空值缓存
     * 
     * @param bool $default 默认值
     * @return bool
     */
    public function isEnableNullCache($default = true)
    {
        return isset($this->config['enable_null_cache']) ? (bool)$this->config['enable_null_cache'] : $default;
    }

    /**
     * 获取TTL随机范围
     * 
     * @param int $default 默认值
     * @return int
     */
    public function getTtlRandomRange($default = 300)
    {
        return isset($this->config['ttl_random_range']) ? (int)$this->config['ttl_random_range'] : $default;
    }

    // ==================== 统计配置 ====================

    /**
     * 是否启用统计
     * 
     * @param bool $default 默认值
     * @return bool
     */
    public function isEnableStats($default = true)
    {
        return isset($this->config['enable_stats']) ? (bool)$this->config['enable_stats'] : $default;
    }

    // ==================== 热点键自动续期配置 ====================

    /**
     * 是否启用热点键自动续期
     * 
     * @param bool $default 默认值
     * @return bool
     */
    public function isHotKeyAutoRenewal($default = true)
    {
        return isset($this->config['hot_key_auto_renewal']) ? (bool)$this->config['hot_key_auto_renewal'] : $default;
    }

    /**
     * 获取热点键阈值
     * 
     * @param int $default 默认值
     * @return int
     */
    public function getHotKeyThreshold($default = 100)
    {
        return isset($this->config['hot_key_threshold']) ? (int)$this->config['hot_key_threshold'] : $default;
    }

    /**
     * 获取热点键延长TTL
     * 
     * @param int $default 默认值
     * @return int
     */
    public function getHotKeyExtendTtl($default = 7200)
    {
        return isset($this->config['hot_key_extend_ttl']) ? (int)$this->config['hot_key_extend_ttl'] : $default;
    }

    /**
     * 获取热点键最大TTL
     * 
     * @param int $default 默认值
     * @return int
     */
    public function getHotKeyMaxTtl($default = 86400)
    {
        return isset($this->config['hot_key_max_ttl']) ? (int)$this->config['hot_key_max_ttl'] : $default;
    }

    // ==================== 标签配置 ====================

    /**
     * 获取标签前缀
     * 
     * @param string $default 默认值
     * @return string
     */
    public function getTagPrefix($default = 'tag:')
    {
        return isset($this->config['tag_prefix']) ? (string)$this->config['tag_prefix'] : $default;
    }

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
        // 按优先级合并：全局配置 -> 组级配置 -> 键级配置
        $mergedConfig = array_merge($globalConfig, $groupConfig, $keyConfig);
        return new self($mergedConfig);
    }
}
