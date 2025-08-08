<?php

use Asfop\CacheKV\Core\CacheKVFactory;
use Asfop\CacheKV\Key\KeyManager;

// ============================================================================
// 核心缓存操作
// ============================================================================

if (!function_exists('kv_get')) {
    /**
     * 获取缓存，若无则执行回调并回填
     * 
     * @param string $template 键模板，如 'user.profile'
     * @param array $params 参数，如 ['id' => 123]
     * @param callable|null $callback 回调函数
     * @param int|null $ttl 缓存时间
     * @return mixed 缓存数据或回调结果
     */
    function kv_get($template, array $params = array(), $callback = null, $ttl = null)
    {
        return CacheKVFactory::getInstance()->getByTemplate($template, $params, $callback, $ttl);
    }
}

if (!function_exists('kv_get_multi')) {
    /**
     * 批量获取缓存
     * 
     * @param string $template 模板，如 'user.profile'
     * @param array $paramsList 参数列表，如 [['id' => 1], ['id' => 2]]
     * @param callable|null $callback 回调函数
     * @return array 结果数组
     */
    function kv_get_multi($template, array $paramsList, $callback = null)
    {
        return CacheKVFactory::getInstance()->getMultipleByTemplate($template, $paramsList, $callback);
    }
}

// ============================================================================
// 键管理
// ============================================================================

if (!function_exists('kv_key')) {
    /**
     * 创建键字符串
     * 
     * @param string $template 模板，如 'user.profile'
     * @param array $params 参数，如 ['id' => 123]
     * @return string 键字符串
     */
    function kv_key($template, array $params = array())
    {
        return KeyManager::getInstance()->createKeyFromTemplate($template, $params)->__toString();
    }
}

if (!function_exists('kv_keys')) {
    /**
     * 批量创建键字符串
     * 
     * @param string $template 模板，如 'user.profile'
     * @param array $paramsList 参数列表，如 [['id' => 1], ['id' => 2]]
     * @return array 键字符串数组
     */
    function kv_keys($template, array $paramsList)
    {
        $keys = array();
        $keyManager = KeyManager::getInstance();
        foreach ($paramsList as $params) {
            if (is_array($params)) {
                $keys[] = $keyManager->createKeyFromTemplate($template, $params)->__toString();
            }
        }
        return $keys;
    }
}

if (!function_exists('kv_get_keys')) {
    /**
     * 批量获取键对象
     * 
     * @param string $template 模板，如 'user.profile'
     * @param array $paramsList 参数列表，如 [['id' => 1], ['id' => 2]]
     * @return array 键对象数组
     */
    function kv_get_keys($template, array $paramsList)
    {
        return KeyManager::getInstance()->getKeys($template, $paramsList);
    }
}

// ============================================================================
// 删除操作
// ============================================================================

if (!function_exists('kv_delete_prefix')) {
    /**
     * 按前缀删除缓存
     * 
     * @param string $template 模板，如 'user.profile'
     * @param array $params 参数（可选）
     * @return int 删除的键数量
     */
    function kv_delete_prefix($template, array $params = array())
    {
        return CacheKVFactory::getInstance()->deleteByPrefix($template, $params);
    }
}

if (!function_exists('kv_delete_full')) {
    /**
     * 按完整前缀删除缓存
     * 
     * @param string $prefix 完整前缀
     * @return int 删除的键数量
     */
    function kv_delete_full($prefix)
    {
        return CacheKVFactory::getInstance()->deleteByFullPrefix($prefix);
    }
}

// ============================================================================
// 统计功能
// ============================================================================

if (!function_exists('kv_stats')) {
    /**
     * 获取缓存统计信息
     * 
     * @return array 统计信息
     */
    function kv_stats()
    {
        return \Asfop\CacheKV\Stats\KeyStats::getGlobalStats();
    }
}

if (!function_exists('kv_hot_keys')) {
    /**
     * 获取热点键
     * 
     * @param int $limit 返回数量限制
     * @return array 热点键列表
     */
    function kv_hot_keys($limit = 10)
    {
        return \Asfop\CacheKV\Stats\KeyStats::getHotKeys($limit);
    }
}

if (!function_exists('kv_clear_stats')) {
    /**
     * 清空统计信息
     * 
     * @return bool 是否成功
     */
    function kv_clear_stats()
    {
        return \Asfop\CacheKV\Stats\KeyStats::clear();
    }
}

// ============================================================================
// 配置管理
// ============================================================================

if (!function_exists('kv_config')) {
    /**
     * 获取完整配置信息
     * 
     * @return array 完整的配置对象，包含cache和key_manager配置
     */
    function kv_config()
    {
        return KeyManager::getInstance()->getAllKeysConfig(true);
    }
}

// ============================================================================
// 向后兼容（保留原有函数名）
// ============================================================================

if (!function_exists('cache_kv_get')) {
    /**
     * @deprecated 使用 kv_get() 代替
     */
    function cache_kv_get($template, array $params = array(), $callback = null, $ttl = null)
    {
        return kv_get($template, $params, $callback, $ttl);
    }
}

if (!function_exists('cache_kv_get_multiple')) {
    /**
     * @deprecated 使用 kv_get_multi() 代替
     */
    function cache_kv_get_multiple($template, array $paramsList, $callback = null)
    {
        return kv_get_multi($template, $paramsList, $callback);
    }
}

if (!function_exists('cache_kv_get_stats')) {
    /**
     * @deprecated 使用 kv_stats() 代替
     */
    function cache_kv_get_stats()
    {
        return kv_stats();
    }
}

if (!function_exists('cache_kv_get_hot_keys')) {
    /**
     * @deprecated 使用 kv_hot_keys() 代替
     */
    function cache_kv_get_hot_keys($limit = 10)
    {
        return kv_hot_keys($limit);
    }
}

if (!function_exists('cache_kv_clear_stats')) {
    /**
     * @deprecated 使用 kv_clear_stats() 代替
     */
    function cache_kv_clear_stats()
    {
        return kv_clear_stats();
    }
}

if (!function_exists('cache_kv_make_key')) {
    /**
     * @deprecated 使用 kv_key() 代替
     */
    function cache_kv_make_key($template, array $params = array())
    {
        return kv_key($template, $params);
    }
}

if (!function_exists('cache_kv_make_keys')) {
    /**
     * @deprecated 使用 kv_keys() 代替
     */
    function cache_kv_make_keys($template, array $paramsList)
    {
        return kv_keys($template, $paramsList);
    }
}

if (!function_exists('cache_kv_get_keys')) {
    /**
     * @deprecated 使用 kv_get_keys() 代替
     */
    function cache_kv_get_keys($template, array $paramsList)
    {
        return kv_get_keys($template, $paramsList);
    }
}

if (!function_exists('cache_kv_delete_by_prefix')) {
    /**
     * @deprecated 使用 kv_delete_prefix() 代替
     */
    function cache_kv_delete_by_prefix($template, array $params = array())
    {
        return kv_delete_prefix($template, $params);
    }
}

if (!function_exists('cache_kv_delete_by_full_prefix')) {
    /**
     * @deprecated 使用 kv_delete_full() 代替
     */
    function cache_kv_delete_by_full_prefix($prefix)
    {
        return kv_delete_full($prefix);
    }
}

if (!function_exists('cache_kv_get_all_keys_config')) {
    /**
     * @deprecated 使用 kv_config() 代替
     */
    function cache_kv_get_all_keys_config($includeDetails = true)
    {
        return KeyManager::getInstance()->getAllKeysConfig($includeDetails);
    }
}
