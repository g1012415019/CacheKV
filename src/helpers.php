<?php

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFactory;

if (!function_exists('cache_kv_get')) {
    /**
     * 快速缓存获取（使用默认实例）
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param callable|null $callback 回调函数（可选）
     * @param int|null $ttl 过期时间
     * @return mixed
     */
    function cache_kv_get($template, $params, $callback = null, $ttl = null) {
        return CacheKVFactory::getInstance()->getByTemplate($template, $params, $callback, $ttl);
    }
}

if (!function_exists('cache_kv_set')) {
    /**
     * 快速缓存设置（使用默认实例）
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param mixed $value 值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    function cache_kv_set($template, $params, $value, $ttl = null) {
        return CacheKVFactory::getInstance()->setByTemplate($template, $params, $value, $ttl);
    }
}

if (!function_exists('cache_kv_delete')) {
    /**
     * 快速缓存删除（使用默认实例）
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @return bool
     */
    function cache_kv_delete($template, $params) {
        return CacheKVFactory::getInstance()->deleteByTemplate($template, $params);
    }
}

if (!function_exists('cache_kv_forget')) {
    /**
     * 快速缓存清除（别名）
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @return bool
     */
    function cache_kv_forget($template, $params) {
        return cache_kv_delete($template, $params);
    }
}

if (!function_exists('cache_kv_clear_tag')) {
    /**
     * 清除指定标签的所有缓存（使用默认实例）
     * 
     * @param string $tag 标签名称
     * @return bool
     */
    function cache_kv_clear_tag($tag) {
        return CacheKVFactory::getInstance()->clearTag($tag);
    }
}

if (!function_exists('cache_kv_config')) {
    /**
     * 配置默认缓存实例
     * 
     * @param array $config 配置数组
     */
    function cache_kv_config(array $config) {
        CacheKVFactory::setDefaultConfig($config);
    }
}

if (!function_exists('cache_kv_instance')) {
    /**
     * 获取默认缓存实例
     * 
     * @return CacheKV
     */
    function cache_kv_instance() {
        return CacheKVFactory::getInstance();
    }
}

// 兼容性：支持传入实例的版本
if (!function_exists('cache_kv_get_with_instance')) {
    /**
     * 使用指定实例获取缓存
     * 
     * @param CacheKV $cache CacheKV 实例
     * @param string $template 模板名称
     * @param array $params 参数
     * @param callable|null $callback 回调函数（可选）
     * @param int|null $ttl 过期时间
     * @return mixed
     */
    function cache_kv_get_with_instance(CacheKV $cache, $template, $params, $callback = null, $ttl = null) {
        return $cache->getByTemplate($template, $params, $callback, $ttl);
    }
}
