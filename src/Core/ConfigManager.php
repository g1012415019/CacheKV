<?php

namespace Asfop\CacheKV\Core;

use Asfop\CacheKV\Exception\CacheException;
use Asfop\CacheKV\Configuration\CacheKVConfig;
use Asfop\CacheKV\Configuration\CacheConfig;

/**
 * 配置管理器
 * 
 * 底层库，只暴露必要的公共接口
 * 现在使用配置实体类替代原来的数组配置
 */
class ConfigManager
{
    /**
     * 配置实体对象
     * 
     * @var CacheKVConfig|null
     */
    private static $config = null;

    /**
     * 加载配置文件
     * 
     * @param string|null $configFile 配置文件路径
     * @throws CacheException 当配置文件不存在或格式错误时抛出异常
     */
    public static function loadConfig($configFile = null)
    {
        // 确定配置文件路径
        if ($configFile === null) {
            $configFile = dirname(dirname(__DIR__)) . '/src/config/cache_kv.php';
        }
        
        // 检查配置文件是否存在
        if (!file_exists($configFile)) {
            throw new CacheException("Config file not found: {$configFile}");
        }
        
        // 检查文件是否可读
        if (!is_readable($configFile)) {
            throw new CacheException("Config file is not readable: {$configFile}");
        }
        
        try {
            $configArray = include $configFile;
        } catch (\ParseError $e) {
            throw new CacheException("Config file has syntax error: {$configFile}. Error: " . $e->getMessage());
        } catch (\Exception $e) {
            throw new CacheException("Failed to load config file: {$configFile}. Error: " . $e->getMessage());
        }
        
        // 验证配置文件返回的是数组
        if (!is_array($configArray)) {
            throw new CacheException("Config file must return an array, got: " . gettype($configArray) . " in file: {$configFile}");
        }
        
        try {
            // 使用配置实体类创建配置对象
            self::$config = CacheKVConfig::fromArray($configArray);
        } catch (\InvalidArgumentException $e) {
            throw new CacheException("Invalid configuration structure in {$configFile}: " . $e->getMessage());
        }
    }



    /**
     * 获取全局缓存配置对象
     * 
     * @return CacheConfig
     * @throws CacheException 当配置未加载时
     */
    public static function getGlobalCacheConfigObject()
    {
        self::ensureConfigLoaded();
        return self::$config->getCache();
    }

    /**
     * 获取 KeyManager 配置
     * 
     * @return array
     * @throws CacheException 当配置未加载时
     */
    public static function getKeyManagerConfig()
    {
        self::ensureConfigLoaded();
        return self::$config->getKeyManager()->toArray();
    }

    /**
     * 重置配置（主要用于测试）
     */
    public static function reset()
    {
        self::$config = null;
    }

    /**
     * 确保配置已加载
     * 
     * @throws CacheException 当配置未加载时
     */
    private static function ensureConfigLoaded()
    {
        if (self::$config === null) {
            throw new CacheException("Configuration not loaded. Call loadConfig() first.");
        }
    }
}
