<?php

namespace Asfop\CacheKV\Cache;

/**
 * 缓存 Key 管理器
 * 
 * 提供统一的缓存键命名规范和管理功能
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
    
    public function __construct($config = [])
    {
        $this->appPrefix = isset($config['app_prefix']) ? $config['app_prefix'] : 'app';
        $this->envPrefix = isset($config['env_prefix']) ? $config['env_prefix'] : 'prod';
        $this->version = isset($config['version']) ? $config['version'] : 'v1';
        $this->separator = isset($config['separator']) ? $config['separator'] : ':';
        
        // 加载默认模板
        $this->loadDefaultTemplates();
        
        // 加载自定义模板
        if (isset($config['templates'])) {
            $this->templates = array_merge($this->templates, $config['templates']);
        }
    }
    
    /**
     * 加载默认键模板
     */
    private function loadDefaultTemplates()
    {
        $this->templates = [
            // 用户相关
            'user' => 'user:{id}',
            'user_profile' => 'user:profile:{id}',
            'user_settings' => 'user:settings:{id}',
            'user_permissions' => 'user:permissions:{id}',
            'user_session' => 'user:session:{session_id}',
            
            // 商品相关
            'product' => 'product:{id}',
            'product_detail' => 'product:detail:{id}',
            'product_price' => 'product:price:{id}',
            'product_stock' => 'product:stock:{id}',
            
            // 分类相关
            'category' => 'category:{id}',
            'category_products' => 'category:products:{id}:page:{page}',
            'category_tree' => 'category:tree:{parent_id}',
            
            // 列表相关
            'list' => 'list:{type}:{id}',
            'page' => 'page:{type}:{page}:size:{size}',
            'search' => 'search:{query}:page:{page}',
            
            // API 相关
            'api_response' => 'api:{endpoint}:{params_hash}',
            'api_token' => 'api:token:{user_id}',
            
            // 系统相关
            'config' => 'config:{key}',
            'stats' => 'stats:{type}:{date}',
            'lock' => 'lock:{resource}:{id}',
            
            // 临时数据
            'temp' => 'temp:{type}:{id}',
            'verification' => 'verification:{type}:{target}',
        ];
    }
    
    /**
     * 生成缓存键
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param bool $withPrefix 是否包含前缀
     * @return string
     */
    public function make($template, $params = [], $withPrefix = true)
    {
        if (!isset($this->templates[$template])) {
            throw new \InvalidArgumentException("Template '{$template}' not found");
        }
        
        $pattern = $this->templates[$template];
        
        // 替换参数
        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', $value, $pattern);
        }
        
        // 检查是否还有未替换的参数
        if (preg_match('/\{[^}]+\}/', $pattern)) {
            throw new \InvalidArgumentException("Missing parameters for template '{$template}': {$pattern}");
        }
        
        // 添加前缀
        if ($withPrefix) {
            $prefix = $this->buildPrefix();
            return $prefix . $this->separator . $pattern;
        }
        
        return $pattern;
    }
    
    /**
     * 构建前缀
     * 
     * @return string
     */
    private function buildPrefix()
    {
        return implode($this->separator, [
            $this->appPrefix,
            $this->envPrefix,
            $this->version
        ]);
    }
    
    /**
     * 添加自定义模板
     * 
     * @param string $name 模板名称
     * @param string $pattern 模板模式
     */
    public function addTemplate($name, $pattern)
    {
        $this->templates[$name] = $pattern;
    }
    
    /**
     * 批量添加模板
     * 
     * @param array $templates 模板数组
     */
    public function addTemplates(array $templates)
    {
        $this->templates = array_merge($this->templates, $templates);
    }
    
    /**
     * 获取所有模板
     * 
     * @return array
     */
    public function getTemplates()
    {
        return $this->templates;
    }
    
    /**
     * 解析缓存键
     * 
     * @param string $key 缓存键
     * @return array 解析结果
     */
    public function parse($key)
    {
        $parts = explode($this->separator, $key);
        
        $result = [
            'full_key' => $key,
            'parts' => $parts,
            'has_prefix' => false,
            'app_prefix' => null,
            'env_prefix' => null,
            'version' => null,
            'business_key' => $key
        ];
        
        // 检查是否有前缀
        if (count($parts) >= 4 && $parts[0] === $this->appPrefix) {
            $result['has_prefix'] = true;
            $result['app_prefix'] = $parts[0];
            $result['env_prefix'] = $parts[1];
            $result['version'] = $parts[2];
            $result['business_key'] = implode($this->separator, array_slice($parts, 3));
        }
        
        return $result;
    }
    
    /**
     * 生成模式匹配键（用于批量操作）
     * 
     * @param string $template 模板名称
     * @param array $params 参数（可以包含通配符 *）
     * @param bool $withPrefix 是否包含前缀
     * @return string
     */
    public function pattern($template, $params = [], $withPrefix = true)
    {
        if (!isset($this->templates[$template])) {
            throw new \InvalidArgumentException("Template '{$template}' not found");
        }
        
        $pattern = $this->templates[$template];
        
        // 替换参数，支持通配符
        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', $value, $pattern);
        }
        
        // 未指定的参数用通配符替换
        $pattern = preg_replace('/\{[^}]+\}/', '*', $pattern);
        
        // 添加前缀
        if ($withPrefix) {
            $prefix = $this->buildPrefix();
            return $prefix . $this->separator . $pattern;
        }
        
        return $pattern;
    }
    
    /**
     * 验证键格式
     * 
     * @param string $key 缓存键
     * @return bool
     */
    public function validate($key)
    {
        // 基本格式检查
        if (empty($key) || !is_string($key)) {
            return false;
        }
        
        // 长度检查
        if (strlen($key) > 250) {
            return false;
        }
        
        // 字符检查（只允许字母、数字、冒号、下划线、连字符）
        if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $key)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 清理键名（移除非法字符）
     * 
     * @param string $key 原始键名
     * @return string 清理后的键名
     */
    public function sanitize($key)
    {
        // 移除非法字符
        $key = preg_replace('/[^a-zA-Z0-9:_-]/', '_', $key);
        
        // 限制长度
        if (strlen($key) > 250) {
            $key = substr($key, 0, 250);
        }
        
        return $key;
    }
    
    /**
     * 生成哈希键（用于长参数）
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param array $hashParams 需要哈希的参数
     * @param bool $withPrefix 是否包含前缀
     * @return string
     */
    public function makeWithHash($template, $params = [], $hashParams = [], $withPrefix = true)
    {
        // 对指定参数进行哈希
        foreach ($hashParams as $param) {
            if (isset($params[$param])) {
                $params[$param] = md5(serialize($params[$param]));
            }
        }
        
        return $this->make($template, $params, $withPrefix);
    }
}
