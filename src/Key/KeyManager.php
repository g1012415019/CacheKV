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
     * 全局配置数组
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
     * 从模板创建缓存键对象
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $params 参数数组
     * @return CacheKey 缓存键对象
     * @throws CacheException
     */
    public function createKeyFromTemplate($template, array $params = array())
    {
        // 解析模板格式
        $parts = explode('.', $template, 2);
        if (count($parts) !== 2) {
            throw new CacheException("Invalid template format: '{$template}'. Expected 'group.key'");
        }
        
        $groupName = $parts[0];
        $keyName = $parts[1];
        
        // 委托给现有的 createKey 方法
        return $this->createKey($groupName, $keyName, $params);
    }

    /**
     * 批量获取缓存键对象
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $paramsList 参数数组列表
     * @return array 关联数组，键为字符串形式的缓存键，值为CacheKey对象
     * @throws CacheException
     */
    public function getKeys($template, array $paramsList)
    {
        if (empty($paramsList)) {
            return array();
        }
        
        // 解析模板（复用模板解析逻辑）
        $parts = explode('.', $template, 2);
        if (count($parts) !== 2) {
            throw new CacheException("Invalid template format: '{$template}'. Expected 'group.key'");
        }
        
        $groupName = $parts[0];
        $keyName = $parts[1];
        
        $result = array();
        
        foreach ($paramsList as $params) {
            if (!is_array($params)) {
                continue; // 跳过非数组参数
            }
            
            try {
                $cacheKey = $this->createKey($groupName, $keyName, $params);
                $keyString = (string)$cacheKey;
                $result[$keyString] = $cacheKey;
            } catch (CacheException $e) {
                // 如果单个键创建失败，抛出异常以保持一致性
                throw $e;
            }
        }
        
        return $result;
    }

    /**
     * 创建缓存键集合
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $paramsList 参数数组列表
     * @return CacheKeyCollection 缓存键集合对象
     * @throws CacheException
     */
    public function createKeyCollection($template, array $paramsList)
    {
        if (empty($paramsList)) {
            return new CacheKeyCollection(array());
        }

        $cacheKeys = array();
        foreach ($paramsList as $params) {
            if (is_array($params)) {
                $cacheKeys[] = $this->createKeyFromTemplate($template, $params);
            }
        }

        return new CacheKeyCollection($cacheKeys);
    }

    /**
     * 获取所有键配置信息
     * 
     * @param bool $includeDetails 是否包含详细配置信息（默认true）
     * @return array 所有分组和键的配置信息
     */
    public function getAllKeysConfig($includeDetails = true)
    {
        if ($this->config === null) {
            return array();
        }
        
        $config = $this->config->toArray()['groups'];
        
        if (!$includeDetails) {
            // 返回简化版本：只返回可用的模板列表
            $templates = array();
            foreach ($config as $groupName => $groupConfig) {
                if (isset($groupConfig['keys']) && is_array($groupConfig['keys'])) {
                    foreach ($groupConfig['keys'] as $keyName => $keyConfig) {
                        $templates[] = $groupName . '.' . $keyName;
                    }
                }
            }
            return $templates;
        }
        
        // 返回详细版本：包含完整配置信息
        $detailedConfig = array();
        foreach ($config as $groupName => $groupConfig) {
            $detailedConfig[$groupName] = array(
                'prefix' => isset($groupConfig['prefix']) ? $groupConfig['prefix'] : $groupName,
                'version' => isset($groupConfig['version']) ? $groupConfig['version'] : 'v1',
                'cache_config' => isset($groupConfig['cache']) ? $groupConfig['cache'] : null,
                'keys' => array()
            );
            
            if (isset($groupConfig['keys']) && is_array($groupConfig['keys'])) {
                foreach ($groupConfig['keys'] as $keyName => $keyConfig) {
                    $template = isset($keyConfig['template']) ? $keyConfig['template'] : $keyName;
                    
                    // 提取模板中的参数
                    $parameters = array();
                    if (preg_match_all('/\{([^}]+)\}/', $template, $matches)) {
                        $parameters = $matches[1];
                    }
                    
                    $detailedConfig[$groupName]['keys'][$keyName] = array(
                        'template' => $template,
                        'full_template' => $groupName . '.' . $keyName,
                        'cache_config' => isset($keyConfig['cache']) ? $keyConfig['cache'] : null,
                        'parameters' => $parameters
                    );
                }
            }
        }
        
        return $detailedConfig;
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
