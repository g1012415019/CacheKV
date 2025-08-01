<?php

use Asfop\CacheKV\CacheKV;

if (!function_exists('cache_kv_get')) {
    /**
     * 快速缓存获取
     * 
     * @param CacheKV $cache CacheKV 实例
     * @param string $template 模板名称
     * @param array $params 参数
     * @param callable|null $callback 回调函数（可选）
     * @param int|null $ttl 过期时间
     * @return mixed
     */
    function cache_kv_get(CacheKV $cache, $template, $params, $callback = null, $ttl = null) {
        return $cache->getByTemplate($template, $params, $callback, $ttl);
    }
}

if (!function_exists('cache_kv_set')) {
    /**
     * 快速缓存设置
     * 
     * @param CacheKV $cache CacheKV 实例
     * @param string $template 模板名称
     * @param array $params 参数
     * @param mixed $value 值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    function cache_kv_set(CacheKV $cache, $template, $params, $value, $ttl = null) {
        return $cache->setByTemplate($template, $params, $value, $ttl);
    }
}

if (!function_exists('cache_kv_delete')) {
    /**
     * 快速缓存删除
     * 
     * @param CacheKV $cache CacheKV 实例
     * @param string $template 模板名称
     * @param array $params 参数
     * @return bool
     */
    function cache_kv_delete(CacheKV $cache, $template, $params) {
        return $cache->deleteByTemplate($template, $params);
    }
}

if (!function_exists('cache_kv_forget')) {
    /**
     * 快速缓存清除（别名）
     * 
     * @param CacheKV $cache CacheKV 实例
     * @param string $template 模板名称
     * @param array $params 参数
     * @return bool
     */
    function cache_kv_forget(CacheKV $cache, $template, $params) {
        return cache_kv_delete($cache, $template, $params);
    }
}

if (!function_exists('cache_kv_clear_tag')) {
    /**
     * 清除指定标签的所有缓存
     * 
     * @param CacheKV $cache CacheKV 实例
     * @param string $tag 标签名称
     * @return bool
     */
    function cache_kv_clear_tag(CacheKV $cache, $tag) {
        return $cache->clearTag($tag);
    }
}
