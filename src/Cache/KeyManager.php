<?php

namespace Asfop\CacheKV\Cache;

/**
 * 缓存 Key 管理器
 * 
 * 提供统一的缓存键命名规范和管理功能，支持模板化键生成、
 * 环境隔离、版本管理等功能。
 */
class KeyManager
{
    /**
     * @var string 应用前缀
     */
    private $appPrefix;
    
    /**
     * @var string 环境前缀
     */
    private $envPrefix;
    
    /**
     * @var string 版本前缀
     */
    private $version;
    
    /**
     * @var array 键模板定义
     */
    private $templates = [];
    
    /**
     * @var string 分隔符
     */
    private $separator = ':';
    
    /**
     * @var array 无效字符列表
     */
    private $invalidChars = [' ', "\t", "\n", "\r", "\0", "\x0B"];

    /**
     * 构造函数
     * 
     * @param array $config 配置数组
     */
    public function __construct($config = [])
    {
        $this->validateConfig($config);
        
        $this->appPrefix = isset($config['app_prefix']) ? $config['app_prefix'] : 'app';
        $this->envPrefix = isset($config['env_prefix']) ? $config['env_prefix'] : 'prod';
        $this->version = isset($config['version']) ? $config['version'] : 'v1';
        $this->separator = isset($config['separator']) ? $config['separator'] : ':';
        
        // 验证配置值
        $this->validatePrefixes();
        
        // 加载默认模板
        $this->loadDefaultTemplates();
        
        // 加载自定义模板
        if (isset($config['templates']) && is_array($config['templates'])) {
            $this->addTemplates($config['templates']);
        }
    }

    /**
     * 验证配置参数
     * 
     * @param array $config 配置数组
     * @throws \InvalidArgumentException 当配置无效时
     */
    private function validateConfig($config)
    {
        if (!is_array($config)) {
            throw new \InvalidArgumentException('Config must be an array');
        }
        
        // 验证必要的配置项类型
        $stringConfigs = ['app_prefix', 'env_prefix', 'version', 'separator'];
        foreach ($stringConfigs as $key) {
            if (isset($config[$key]) && !is_string($config[$key])) {
                throw new \InvalidArgumentException("Config '{$key}' must be a string");
            }
        }
        
        if (isset($config['templates']) && !is_array($config['templates'])) {
            throw new \InvalidArgumentException('Config templates must be an array');
        }
    }

    /**
     * 验证前缀是否有效
     * 
     * @throws \InvalidArgumentException 当前缀包含无效字符时
     */
    private function validatePrefixes()
    {
        $prefixes = [
            'app_prefix' => $this->appPrefix,
            'env_prefix' => $this->envPrefix,
            'version' => $this->version,
            'separator' => $this->separator
        ];
        
        foreach ($prefixes as $name => $value) {
            if (empty($value)) {
                throw new \InvalidArgumentException("'{$name}' cannot be empty");
            }
            
            foreach ($this->invalidChars as $char) {
                if (strpos($value, $char) !== false) {
                    throw new \InvalidArgumentException("'{$name}' contains invalid character");
                }
            }
        }
    }

    /**
     * 加载默认模板
     */
    private function loadDefaultTemplates()
    {
        $this->templates = [
            // 用户相关
            'user' => 'user:{id}',
            'user_profile' => 'user:profile:{id}',
            'user_settings' => 'user:settings:{id}',
            'user_permissions' => 'user:permissions:{id}',
            
            // 会话相关
            'session' => 'session:{id}',
            'user_session' => 'user:session:{user_id}',
            
            // 通用模板
            'cache' => 'cache:{key}',
            'temp' => 'temp:{key}',
            'lock' => 'lock:{resource}',
        ];
    }

    /**
     * 批量添加模板
     * 
     * @param array $templates 模板数组
     * @throws \InvalidArgumentException 当模板格式无效时
     */
    public function addTemplates(array $templates)
    {
        foreach ($templates as $name => $pattern) {
            $this->addTemplate($name, $pattern);
        }
    }

    /**
     * 添加单个模板
     * 
     * @param string $name 模板名称
     * @param string $pattern 模板模式
     * @throws \InvalidArgumentException 当模板格式无效时
     */
    public function addTemplate($name, $pattern)
    {
        if (empty($name) || !is_string($name)) {
            throw new \InvalidArgumentException('Template name must be a non-empty string');
        }
        
        if (empty($pattern) || !is_string($pattern)) {
            throw new \InvalidArgumentException('Template pattern must be a non-empty string');
        }
        
        // 验证模板名称不包含无效字符
        foreach ($this->invalidChars as $char) {
            if (strpos($name, $char) !== false) {
                throw new \InvalidArgumentException("Template name '{$name}' contains invalid character");
            }
        }
        
        $this->templates[$name] = $pattern;
    }

    /**
     * 生成缓存键
     * 
     * @param string $template 模板名称
     * @param array $params 参数数组
     * @param bool $withPrefix 是否包含前缀
     * @return string 生成的缓存键
     * @throws \InvalidArgumentException 当模板不存在或参数无效时
     */
    public function make($template, $params = [], $withPrefix = true)
    {
        if (empty($template) || !is_string($template)) {
            throw new \InvalidArgumentException('Template name must be a non-empty string');
        }
        
        if (!isset($this->templates[$template])) {
            throw new \InvalidArgumentException("Template '{$template}' not found");
        }
        
        if (!is_array($params)) {
            throw new \InvalidArgumentException('Parameters must be an array');
        }
        
        $pattern = $this->templates[$template];
        $businessKey = $this->buildBusinessKey($pattern, $params);
        
        if (!$withPrefix) {
            return $businessKey;
        }
        
        return $this->buildFullKey($businessKey);
    }

    /**
     * 构建业务键
     * 
     * @param string $pattern 模板模式
     * @param array $params 参数数组
     * @return string 业务键
     * @throws \InvalidArgumentException 当参数缺失时
     */
    private function buildBusinessKey($pattern, $params)
    {
        $businessKey = $pattern;
        
        // 查找所有参数占位符
        if (preg_match_all('/\{([^}]+)\}/', $pattern, $matches)) {
            foreach ($matches[1] as $paramName) {
                if (!array_key_exists($paramName, $params)) {
                    throw new \InvalidArgumentException("Parameter '{$paramName}' is required for this template");
                }
                
                $paramValue = $this->sanitizeParamValue($params[$paramName]);
                $businessKey = str_replace('{' . $paramName . '}', $paramValue, $businessKey);
            }
        }
        
        return $businessKey;
    }

    /**
     * 清理参数值
     * 
     * @param mixed $value 参数值
     * @return string 清理后的值
     */
    private function sanitizeParamValue($value)
    {
        // 转换为字符串
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_null($value)) {
            return 'null';
        }
        
        if (is_array($value) || is_object($value)) {
            return md5(serialize($value));
        }
        
        $stringValue = (string) $value;
        
        // 移除无效字符
        foreach ($this->invalidChars as $char) {
            $stringValue = str_replace($char, '_', $stringValue);
        }
        
        return $stringValue;
    }

    /**
     * 构建完整键
     * 
     * @param string $businessKey 业务键
     * @return string 完整键
     */
    private function buildFullKey($businessKey)
    {
        return implode($this->separator, [
            $this->appPrefix,
            $this->envPrefix,
            $this->version,
            $businessKey
        ]);
    }

    /**
     * 生成模式匹配键（用于批量操作）
     * 
     * @param string $template 模板名称
     * @param array $params 参数数组，使用 '*' 作为通配符
     * @param bool $withPrefix 是否包含前缀
     * @return string 模式匹配键
     */
    public function pattern($template, $params = [], $withPrefix = true)
    {
        return $this->make($template, $params, $withPrefix);
    }

    /**
     * 解析缓存键
     * 
     * @param string $key 要解析的缓存键
     * @return array 解析结果
     */
    public function parse($key)
    {
        if (empty($key) || !is_string($key)) {
            return [
                'full_key' => $key,
                'has_prefix' => false,
                'app_prefix' => null,
                'env_prefix' => null,
                'version' => null,
                'business_key' => $key
            ];
        }
        
        $parts = explode($this->separator, $key);
        
        // 检查是否有足够的部分构成完整的前缀键
        if (count($parts) >= 4) {
            $businessKeyParts = array_slice($parts, 3);
            return [
                'full_key' => $key,
                'has_prefix' => true,
                'app_prefix' => $parts[0],
                'env_prefix' => $parts[1],
                'version' => $parts[2],
                'business_key' => implode($this->separator, $businessKeyParts)
            ];
        }
        
        return [
            'full_key' => $key,
            'has_prefix' => false,
            'app_prefix' => null,
            'env_prefix' => null,
            'version' => null,
            'business_key' => $key
        ];
    }

    /**
     * 验证键格式
     * 
     * @param string $key 要验证的键
     * @return bool 是否有效
     */
    public function validate($key)
    {
        if (empty($key) || !is_string($key)) {
            return false;
        }
        
        // 检查是否包含无效字符
        foreach ($this->invalidChars as $char) {
            if (strpos($key, $char) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 清理键名
     * 
     * @param string $key 要清理的键
     * @return string 清理后的键
     */
    public function sanitize($key)
    {
        if (empty($key)) {
            return '';
        }
        
        $cleanKey = (string) $key;
        
        // 替换无效字符
        foreach ($this->invalidChars as $char) {
            $cleanKey = str_replace($char, '_', $cleanKey);
        }
        
        return $cleanKey;
    }

    /**
     * 获取所有模板
     * 
     * @return array 模板数组
     */
    public function getTemplates()
    {
        return $this->templates;
    }

    /**
     * 检查模板是否存在
     * 
     * @param string $template 模板名称
     * @return bool 是否存在
     */
    public function hasTemplate($template)
    {
        return isset($this->templates[$template]);
    }

    /**
     * 移除模板
     * 
     * @param string $template 模板名称
     * @return bool 是否成功移除
     */
    public function removeTemplate($template)
    {
        if (isset($this->templates[$template])) {
            unset($this->templates[$template]);
            return true;
        }
        return false;
    }

    /**
     * 获取配置信息
     * 
     * @return array 配置数组
     */
    public function getConfig()
    {
        return [
            'app_prefix' => $this->appPrefix,
            'env_prefix' => $this->envPrefix,
            'version' => $this->version,
            'separator' => $this->separator,
            'templates' => $this->templates
        ];
    }

    /**
     * 设置应用前缀
     * 
     * @param string $prefix 应用前缀
     * @throws \InvalidArgumentException 当前缀无效时
     */
    public function setAppPrefix($prefix)
    {
        if (empty($prefix) || !is_string($prefix)) {
            throw new \InvalidArgumentException('App prefix must be a non-empty string');
        }
        
        foreach ($this->invalidChars as $char) {
            if (strpos($prefix, $char) !== false) {
                throw new \InvalidArgumentException('App prefix contains invalid character');
            }
        }
        
        $this->appPrefix = $prefix;
    }

    /**
     * 设置环境前缀
     * 
     * @param string $prefix 环境前缀
     * @throws \InvalidArgumentException 当前缀无效时
     */
    public function setEnvPrefix($prefix)
    {
        if (empty($prefix) || !is_string($prefix)) {
            throw new \InvalidArgumentException('Env prefix must be a non-empty string');
        }
        
        foreach ($this->invalidChars as $char) {
            if (strpos($prefix, $char) !== false) {
                throw new \InvalidArgumentException('Env prefix contains invalid character');
            }
        }
        
        $this->envPrefix = $prefix;
    }

    /**
     * 设置版本
     * 
     * @param string $version 版本号
     * @throws \InvalidArgumentException 当版本无效时
     */
    public function setVersion($version)
    {
        if (empty($version) || !is_string($version)) {
            throw new \InvalidArgumentException('Version must be a non-empty string');
        }
        
        foreach ($this->invalidChars as $char) {
            if (strpos($version, $char) !== false) {
                throw new \InvalidArgumentException('Version contains invalid character');
            }
        }
        
        $this->version = $version;
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
     * 获取环境前缀
     * 
     * @return string
     */
    public function getEnvPrefix()
    {
        return $this->envPrefix;
    }

    /**
     * 获取版本
     * 
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
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
}
