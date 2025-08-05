<?php

namespace Asfop\CacheKV\Key;

use Asfop\CacheKV\Exception\CacheException;
use Asfop\CacheKV\Configuration\KeyManagerConfig;

/**
 * 缓存键管理器 
 * 
 * 只保留两个核心功能：
 * 1. 生成键字符串
 * 2. 创建 CacheKey 对象
 * 兼容 PHP 7.0
 */
class KeyManager
{
    /**
     * KeyManager 配置对象
     * 
     * @var KeyManagerConfig
     */
    private $config;
    
    /**
     * 单例实例
     * 
     * @var KeyManager|null
     */
    private static $instance = null;
    
    /**
     * 全局配置数组（向后兼容）
     * 
     * @var array|null
     */
    private static $globalConfig = null;

    /**
     * 构造函数
     * 
     * @param KeyManagerConfig|null $config 配置对象
     */
    private function __construct($config = null)
    {
        if ($config instanceof KeyManagerConfig) {
            $this->config = $config;
        } else {
            // 从全局配置创建
            $configArray = self::$globalConfig !== null ? self::$globalConfig : array(
                'app_prefix' => 'app',
                'separator' => ':',
                'groups' => array()
            );
            
            $this->config = KeyManagerConfig::fromArray($configArray);
        }
    }

    /**
     * 获取单例实例
     * 
     * @return KeyManager
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * 注入全局配置
     * 
     * @param array $config 配置数组
     */
    public static function injectGlobalConfig(array $config)
    {
        self::$globalConfig = $config;
        self::$instance = null;
    }

    /**
     * 生成完整的缓存键字符串
     * 
     * @param string $groupName 分组名称
     * @param string $keyName 键名称
     * @param array $params 参数数组
     * @return string 完整的缓存键
     * @throws CacheException
     */
    public function makeKey($groupName, $keyName, array $params = array())
    {
        // 获取分组配置
        $groupConfig = $this->config->getGroup($groupName);
        if ($groupConfig === null) {
            throw new CacheException("Group '{$groupName}' not found");
        }
        
        // 获取键配置
        $keyConfig = $groupConfig->getKey($keyName);
        if ($keyConfig === null) {
            throw new CacheException("Key '{$keyName}' not found in group '{$groupName}'");
        }
        
        // 替换模板参数
        $template = $keyConfig->getTemplate();
        $keyPart = $this->replaceParams($template, $params);
        
        // 组合完整键
        return $this->config->getAppPrefix() . $this->config->getSeparator() . 
               $groupConfig->getPrefix() . $this->config->getSeparator() . 
               $groupConfig->getVersion() . $this->config->getSeparator() . 
               $keyPart;
    }

    /**
     * 创建缓存键对象
     * 
     * @param string $groupName 分组名称
     * @param string $keyName 键名称
     * @param array $params 参数数组
     * @return CacheKey 缓存键对象
     * @throws CacheException
     */
    public function createKey($groupName, $keyName, array $params = array())
    {
        // 获取分组配置
        $groupConfig = $this->config->getGroup($groupName);
        if ($groupConfig === null) {
            throw new CacheException("Group '{$groupName}' not found");
        }
        
        // 获取键配置
        $keyConfig = $groupConfig->getKey($keyName);
        
        // 生成完整键
        $fullKey = $this->makeKey($groupName, $keyName, $params);
        
        // 创建 CacheKey 对象
        return new CacheKey($groupName, $keyName, $params, $groupConfig, $keyConfig, $fullKey);
    }

    /**
     * 获取分组构建器（链式调用）
     * 
     * @param string $groupName 分组名称
     * @return GroupKeyBuilder
     */
    public function group($groupName)
    {
        return new GroupKeyBuilder($this, $groupName);
    }

    /**
     * 替换模板中的参数
     * 
     * @param string $template 模板字符串
     * @param array $params 参数数组
     * @return string 替换后的字符串
     * @throws CacheException
     */
    private function replaceParams($template, array $params)
    {
        $key = $template;
        
        foreach ($params as $param => $value) {
            $placeholder = '{' . $param . '}';
            $key = str_replace($placeholder, (string)$value, $key);
        }
        
        if (preg_match('/\{[^}]+\}/', $key, $matches)) {
            throw new CacheException("Missing parameter for placeholder: {$matches[0]} in template: {$template}");
        }
        
        return $key;
    }

    /**
     * 重置实例（测试用）
     */
    public static function reset()
    {
        self::$instance = null;
        self::$globalConfig = null;
    }
}
