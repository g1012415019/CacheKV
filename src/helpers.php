<?php

use Asfop\CacheKV\Core\CacheKVFactory;
use Asfop\CacheKV\Key\KeyManager;
use Asfop\CacheKV\Key\CacheKey;

if (!function_exists('cache_kv_get')) {
    /**
     * 获取缓存，若无则执行回调并回填
     * 
     * @param string $template 键模板（如 'user.profile'）
     * @param array $params 模板参数
     * @param callable|null $callback 回调函数
     * @param int|null $ttl 缓存时间
     * @return mixed 返回缓存数据或回调结果
     */
    function cache_kv_get($template, array $params = array(), $callback = null, $ttl = null)
    {
        $cache = CacheKVFactory::getInstance();
        
        // 创建 CacheKey 对象
        $cacheKey = cache_kv_make_key($template, $params);
        
        // 使用 CacheKey 对象调用 get 方法
        return $cache->get($cacheKey, $callback, $ttl);
    }
}

if (!function_exists('cache_kv_get_multiple')) {
    /**
     * 批量获取缓存
     * 
     * 支持两种调用方式：
     * 1. 简洁方式：cache_kv_get_multiple('user.profile', [1, 2, 3], $callback)
     * 2. 复杂方式：cache_kv_get_multiple('user.profile', [['id'=>1,'sex'=>'M'], ['id'=>2,'sex'=>'F']], $callback)
     * 3. 传统方式：cache_kv_get_multiple([['template'=>'user.profile', 'params'=>['id'=>1]], ...], $callback)
     * 
     * @param string|array $template 模板名称或传统格式数组
     * @param array|callable|null $params 参数数组或回调函数（传统格式时）
     * @param callable|null $callback 回调函数
     * @return array 结果数组
     */
    function cache_kv_get_multiple($template, $params = null, $callback = null)
    {
        $cache = CacheKVFactory::getInstance();
        $cacheKeys = array();

        // 传统格式：第一个参数是数组
        if (is_array($template)) {
            $templates = $template;
            $callback = $params; // 第二个参数是回调函数
            
            if (empty($templates)) {
                return array();
            }

            // 创建CacheKey对象数组
            foreach ($templates as $tmpl) {
                if (isset($tmpl['template'])) {
                    $tmplParams = isset($tmpl['params']) ? $tmpl['params'] : array();
                    $cacheKeys[] = cache_kv_make_key($tmpl['template'], $tmplParams);
                }
            }

            return $cache->getMultiple($cacheKeys, $callback);
        }

        // 新格式：template是字符串
        if (!is_string($template) || empty($params) || !is_array($params)) {
            return array();
        }

        // 构建CacheKey数组
        foreach ($params as $param) {
            if (is_array($param)) {
                // 复杂参数：['id' => 1, 'ymd' => '20240804', 'uid' => 123, 'sex' => 'M']
                $cacheKeys[] = cache_kv_make_key($template, $param);
            } else {
                // 简单参数：假设是ID
                $cacheKeys[] = cache_kv_make_key($template, array('id' => $param));
            }
        }

        return $cache->getMultiple($cacheKeys, $callback);
    }
}

if (!function_exists('cache_kv_get_stats')) {
    /**
     * 获取缓存统计信息
     * 
     * @return array 统计信息
     */
    function cache_kv_get_stats()
    {
        $cache = CacheKVFactory::getInstance();
        return $cache->getStats();
    }
}

if (!function_exists('cache_kv_get_hot_keys')) {
    /**
     * 获取热点键列表
     * 
     * @param int $limit 返回数量限制
     * @return array 热点键列表
     */
    function cache_kv_get_hot_keys($limit = 10)
    {
        $cache = CacheKVFactory::getInstance();
        return $cache->getHotKeys($limit);
    }
}

if (!function_exists('cache_kv_make_key')) {
    /**
     * 创建缓存键对象
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $params 模板参数
     * @return CacheKey 缓存键对象
     */
    function cache_kv_make_key($template, array $params = array())
    {
        // 简单分割模板
        $parts = explode('.', $template, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Template must be in format 'group.key', got: '{$template}'");
        }
        
        $groupName = $parts[0];
        $keyName = $parts[1];
        
        // 委托给KeyManager处理，让它负责验证和创建
        return KeyManager::getInstance()->createKey($groupName, $keyName, $params);
    }
}
