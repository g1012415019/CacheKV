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
        // 委托给 CacheKV 处理，不包含业务逻辑
        return CacheKVFactory::getInstance()->getByTemplate($template, $params, $callback, $ttl);
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
        // 委托给 CacheKV 处理，不包含业务逻辑
        return CacheKVFactory::getInstance()->getMultipleByTemplate($template, $paramsList, $callback);
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
        // 委托给 KeyManager 处理，不包含业务逻辑
        return \Asfop\CacheKV\Key\KeyManager::getInstance()->createKeyFromTemplate($template, $params);
    }
}

if (!function_exists('cache_kv_make_keys')) {
    /**
     * 批量创建缓存键集合
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $paramsList 参数数组列表，每个元素必须是数组
     * @return \Asfop\CacheKV\Key\CacheKeyCollection 缓存键集合对象
     */
    function cache_kv_make_keys($template, array $paramsList)
    {
        // 委托给 KeyManager 处理，不包含业务逻辑
        return \Asfop\CacheKV\Key\KeyManager::getInstance()->createKeyCollection($template, $paramsList);
    }
}

if (!function_exists('cache_kv_get_keys')) {
    /**
     * 批量获取缓存键对象（不执行缓存操作）
     * 
     * 这个函数只生成键对象，不进行实际的缓存读取操作
     * 适用于需要获取键信息、检查键配置等场景
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $paramsList 参数数组列表，每个元素必须是数组
     * @return array 缓存键对象数组，键为字符串形式的缓存键，值为CacheKey对象
     * 
     * @example
     * // 批量获取用户资料键对象
     * $keys = cache_kv_get_keys('user.profile', [
     *     ['id' => 1],
     *     ['id' => 2], 
     *     ['id' => 3]
     * ]);
     * 
     * // 结果格式：
     * // [
     * //     'myapp:user:v1:profile:1' => CacheKey对象,
     * //     'myapp:user:v1:profile:2' => CacheKey对象,
     * //     'myapp:user:v1:profile:3' => CacheKey对象
     * // ]
     * 
     * // 检查键配置
     * foreach ($keys as $keyString => $keyObj) {
     *     echo "键: {$keyString}, 有缓存配置: " . ($keyObj->hasCacheConfig() ? '是' : '否') . "\n";
     * }
     */
    function cache_kv_get_keys($template, array $paramsList)
    {
        // 委托给 KeyManager 处理，不包含业务逻辑
        return \Asfop\CacheKV\Key\KeyManager::getInstance()->getKeys($template, $paramsList);
    }
}

if (!function_exists('cache_kv_delete_by_prefix')) {
    /**
     * 按前缀删除缓存
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $params 参数数组（可选），用于生成具体的前缀
     * @return int 删除的键数量
     */
    function cache_kv_delete_by_prefix($template, array $params = array())
    {
        $cache = CacheKVFactory::getInstance();
        return $cache->deleteByPrefix($template, $params);
    }
}

if (!function_exists('cache_kv_delete_by_full_prefix')) {
    /**
     * 按完整前缀删除缓存
     * 
     * @param string $prefix 完整的键前缀，如 'myapp:user:v1:settings:'
     * @return int 删除的键数量
     */
    function cache_kv_delete_by_full_prefix($prefix)
    {
        $cache = CacheKVFactory::getInstance();
        return $cache->deleteByFullPrefix($prefix);
    }
}
