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
     * @param string $template 模板名称，如 'user.profile'
     * @param array $paramsList 参数数组列表，每个元素必须是数组
     * @param callable|null $callback 回调函数，必须返回关联数组格式：['key_string' => 'data', ...]
     * @return array 结果数组
     */
    function cache_kv_get_multiple($template, array $paramsList, $callback = null)
    {
        if (empty($paramsList)) {
            return array();
        }

        $cache = CacheKVFactory::getInstance();
        
        // 使用批量键生成函数
        $cacheKeys = cache_kv_make_keys($template, $paramsList);

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
        return \Asfop\CacheKV\Stats\KeyStats::getGlobalStats();
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
        return \Asfop\CacheKV\Stats\KeyStats::getHotKeys($limit);
    }
}

if (!function_exists('cache_kv_clear_stats')) {
    /**
     * 清空统计数据
     */
    function cache_kv_clear_stats()
    {
        \Asfop\CacheKV\Stats\KeyStats::clear();
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

if (!function_exists('cache_kv_make_keys')) {
    /**
     * 批量创建缓存键对象
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $paramsList 参数数组列表，每个元素必须是数组
     * @return CacheKey[] 缓存键对象数组
     */
    function cache_kv_make_keys($template, array $paramsList)
    {
        if (empty($paramsList)) {
            return array();
        }

        $cacheKeys = array();
        foreach ($paramsList as $params) {
            if (is_array($params)) {
                $cacheKeys[] = cache_kv_make_key($template, $params);
            }
        }

        return $cacheKeys;
    }
}

if (!function_exists('cache_kv_keys_to_strings')) {
    /**
     * 将缓存键对象数组转换为字符串数组
     * 
     * @param CacheKey[] $cacheKeys 缓存键对象数组
     * @return string[] 缓存键字符串数组
     */
    function cache_kv_keys_to_strings(array $cacheKeys)
    {
        $keyStrings = array();
        foreach ($cacheKeys as $cacheKey) {
            $keyStrings[] = (string)$cacheKey;
        }
        return $keyStrings;
    }
}
