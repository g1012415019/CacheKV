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
        
        // 自动加载分组配置文件
        $configArray = self::loadGroupConfigs($configFile, $configArray);
        
        try {
            // 使用配置实体类创建配置对象
            self::$config = CacheKVConfig::fromArray($configArray);
        } catch (\InvalidArgumentException $e) {
            throw new CacheException("Invalid configuration structure in {$configFile}: " . $e->getMessage());
        }
    }

    /**
     * 自动加载分组配置文件
     * 
     * @param string $mainConfigFile 主配置文件路径
     * @param array $configArray 主配置数组
     * @return array 合并后的配置数组
     */
    private static function loadGroupConfigs($mainConfigFile, array $configArray)
    {
        // 确定分组配置目录路径
        $configDir = dirname($mainConfigFile);
        $groupConfigDir = $configDir . '/kvconf';
        
        // 如果分组配置目录不存在，直接返回原配置
        if (!is_dir($groupConfigDir)) {
            return $configArray;
        }
        
        // 扫描分组配置文件
        $groupConfigFiles = glob($groupConfigDir . '/*.php');
        if (empty($groupConfigFiles)) {
            return $configArray;
        }
        
        // 确保 key_manager.groups 存在
        if (!isset($configArray['key_manager'])) {
            $configArray['key_manager'] = array();
        }
        if (!isset($configArray['key_manager']['groups'])) {
            $configArray['key_manager']['groups'] = array();
        }
        
        // 加载每个分组配置文件
        foreach ($groupConfigFiles as $groupConfigFile) {
            $groupName = basename($groupConfigFile, '.php');
            
            try {
                $groupConfig = include $groupConfigFile;
                
                // 验证分组配置格式
                if (!is_array($groupConfig)) {
                    throw new CacheException("Group config file must return an array, got: " . gettype($groupConfig) . " in file: {$groupConfigFile}");
                }
                
                // 合并分组配置
                $configArray['key_manager']['groups'][$groupName] = $groupConfig;
                
            } catch (\ParseError $e) {
                throw new CacheException("Group config file has syntax error: {$groupConfigFile}. Error: " . $e->getMessage());
            } catch (\Exception $e) {
                throw new CacheException("Failed to load group config file: {$groupConfigFile}. Error: " . $e->getMessage());
            }
        }
        
        return $configArray;
    }

    /**
     * 获取全局缓存配置
     * 
     * @return array 返回数组格式以保持向后兼容
     * @throws CacheException 当配置未加载时
     */
    public static function getGlobalCacheConfig()
    {
        self::ensureConfigLoaded();
        return self::$config->getCache()->toArray();
    }

    /**
     * 获取全局缓存配置实体对象
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
     * @return array 返回数组格式以保持向后兼容
     * @throws CacheException 当配置未加载时
     */
    public static function getKeyManagerConfig()
    {
        self::ensureConfigLoaded();
        return self::$config->getKeyManager()->toArray();
    }

    /**
     * 获取指定组的缓存配置（继承全局配置）
     * 
     * @param string $groupName 组名
     * @return array
     * @throws CacheException 当配置未加载或组不存在时
     */
    public static function getGroupCacheConfig($groupName)
    {
        self::ensureConfigLoaded();
        
        $keyManagerConfig = self::$config->getKeyManager();
        $groupConfig = $keyManagerConfig->getGroup($groupName);
        
        if ($groupConfig === null) {
            throw new CacheException("Group '{$groupName}' not found in configuration");
        }
        
        // 获取全局缓存配置
        $globalCacheConfig = self::$config->getCache()->toArray();
        
        // 获取组级缓存配置
        $groupCacheConfig = $groupConfig->getCacheConfig();
        if ($groupCacheConfig === null) {
            $groupCacheConfig = array();
        }
        
        // 组配置覆盖全局配置
        return array_merge($globalCacheConfig, $groupCacheConfig);
    }

    /**
     * 获取指定键的缓存配置（继承组配置）
     * 
     * @param string $groupName 组名
     * @param string $keyName 键名
     * @return array|null 如果是非KV类型的键，返回null
     * @throws CacheException 当配置未加载或组不存在时
     */
    public static function getKeyCacheConfig($groupName, $keyName)
    {
        self::ensureConfigLoaded();
        
        $keyManagerConfig = self::$config->getKeyManager();
        $groupConfig = $keyManagerConfig->getGroup($groupName);
        
        if ($groupConfig === null) {
            throw new CacheException("Group '{$groupName}' not found in configuration");
        }
        
        // 获取组级缓存配置作为基础
        $groupCacheConfig = self::getGroupCacheConfig($groupName);
        
        // 检查是否为KV类型的键
        if ($groupConfig->hasKvKey($keyName)) {
            $keyConfig = $groupConfig->getKvKey($keyName);
            $keyCacheConfig = $keyConfig->getCacheConfig();
            
            if ($keyCacheConfig !== null && !empty($keyCacheConfig)) {
                // 键配置覆盖组配置
                return array_merge($groupCacheConfig, $keyCacheConfig);
            }
            
            return $groupCacheConfig;
        }
        
        // 如果是其他类型的键，返回null（不应用缓存配置）
        if ($groupConfig->hasOtherKey($keyName)) {
            return null;
        }
        
        // 键不存在，返回组配置
        return $groupCacheConfig;
    }

    /**
     * 检查键是否为KV类型
     * 
     * @param string $groupName 组名
     * @param string $keyName 键名
     * @return bool
     * @throws CacheException 当配置未加载时
     */
    public static function isKvKey($groupName, $keyName)
    {
        self::ensureConfigLoaded();
        
        $keyManagerConfig = self::$config->getKeyManager();
        $groupConfig = $keyManagerConfig->getGroup($groupName);
        
        if ($groupConfig === null) {
            return false;
        }
        
        return $groupConfig->isKvKey($keyName);
    }

    /**
     * 获取完整的配置实体对象
     * 
     * @return CacheKVConfig
     * @throws CacheException 当配置未加载时
     */
    public static function getConfigObject()
    {
        self::ensureConfigLoaded();
        return self::$config;
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
