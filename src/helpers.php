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
     * 支持多种调用方式：
     * 1. 传统方式：[['template' => 'user.profile', 'params' => ['id' => 1]], ...]
     * 2. 简洁方式：['user.profile' => [['id' => 1], ['id' => 2], ['id' => 3]]]
     * 3. 最简方式：['user.profile' => [1, 2, 3]] (自动转换为 ['id' => value])
     * 
     * @param array $templates 模板数组，支持多种格式
     * @param callable|null $callback 回调函数
     * @return array 结果数组
     */
    function cache_kv_get_multiple(array $templates, $callback = null)
    {
        if (empty($templates)) {
            return array();
        }

        $cache = CacheKVFactory::getInstance();
        $cacheKeys = array();
        $paramsMap = array(); // 用于回调函数参数转换

        // 检测数组格式并统一处理
        $normalizedTemplates = array();
        
        // 检查是否是新的简洁格式
        $isSimpleFormat = false;
        foreach ($templates as $key => $value) {
            if (is_string($key) && is_array($value)) {
                $isSimpleFormat = true;
                break;
            }
        }
        
        if ($isSimpleFormat) {
            // 处理简洁格式：['user.profile' => [1, 2, 3]] 或 ['user.profile' => [['id' => 1], ['id' => 2]]]
            foreach ($templates as $template => $paramsList) {
                foreach ($paramsList as $params) {
                    if (!is_array($params)) {
                        // 最简格式：[1, 2, 3] -> [['id' => 1], ['id' => 2], ['id' => 3]]
                        $params = array('id' => $params);
                    }
                    $normalizedTemplates[] = array('template' => $template, 'params' => $params);
                }
            }
        } else {
            // 传统格式：[['template' => 'user.profile', 'params' => ['id' => 1]], ...]
            $normalizedTemplates = $templates;
        }

        // 创建CacheKey对象数组
        foreach ($normalizedTemplates as $template) {
            if (isset($template['template'])) {
                $params = isset($template['params']) ? $template['params'] : array();
                $cacheKey = cache_kv_make_key($template['template'], $params);
                $cacheKeys[] = $cacheKey;
                $paramsMap[(string)$cacheKey] = $params;
            }
        }

        // 包装回调函数，提供更友好的参数
        $wrappedCallback = null;
        if ($callback !== null) {
            $wrappedCallback = function($missedKeys) use ($callback, $paramsMap) {
                $missedParams = array();
                foreach ($missedKeys as $keyString) {
                    if (isset($paramsMap[$keyString])) {
                        $missedParams[] = $paramsMap[$keyString];
                    }
                }
                return $callback($missedParams);
            };
        }

        return $cache->getMultiple($cacheKeys, $wrappedCallback);
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
