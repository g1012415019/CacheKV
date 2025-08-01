<?php

use Asfop\CacheKV\CacheKVFactory;

if (!function_exists('cache_kv')) {
    /**
     * 获取 CacheKV 实例
     * 
     * @param string|null $name 实例名称
     * @return \Asfop\CacheKV\CacheKV
     */
    function cache_kv($name = null) {
        return CacheKVFactory::create($name ?: 'default');
    }
}

if (!function_exists('cache_kv_get')) {
    /**
     * 快速缓存获取
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param callable $callback 回调函数
     * @param int|null $ttl 过期时间
     * @return mixed
     */
    function cache_kv_get($template, $params, $callback, $ttl = null) {
        return cache_kv()->getByTemplate($template, $params, $callback, $ttl);
    }
}

if (!function_exists('cache_kv_set')) {
    /**
     * 快速缓存设置
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param mixed $value 值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    function cache_kv_set($template, $params, $value, $ttl = null) {
        return cache_kv()->setByTemplate($template, $params, $value, $ttl);
    }
}

if (!function_exists('cache_kv_forget')) {
    /**
     * 快速缓存清除
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @return bool
     */
    function cache_kv_forget($template, $params) {
        return cache_kv()->forgetByTemplate($template, $params);
    }
}

if (!function_exists('cache_kv_quick')) {
    /**
     * 快速创建 CacheKV 实例
     * 
     * @param string $appPrefix 应用前缀
     * @param string $envPrefix 环境前缀
     * @param array $templates 模板配置
     * @return \Asfop\CacheKV\CacheKV
     */
    function cache_kv_quick($appPrefix = 'app', $envPrefix = 'dev', $templates = []) {
        return CacheKVFactory::quick($appPrefix, $envPrefix, $templates);
    }
}
