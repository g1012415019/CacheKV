<?php

namespace Asfop\CacheKV\Services;

use Asfop\CacheKV\CacheKVFacade;
use Asfop\CacheKV\CacheTemplates;

/**
 * 缓存服务基类
 * 提供统一的缓存操作方法，避免直接使用模板名称
 */
abstract class CacheServiceBase
{
    /**
     * 获取缓存数据
     * 
     * @param string $template 模板常量
     * @param array $params 参数
     * @param callable $callback 回调函数
     * @param int|null $ttl 过期时间
     * @return mixed
     */
    protected function getCache($template, array $params, callable $callback, $ttl = null)
    {
        // 验证模板是否存在
        if (!CacheTemplates::exists($template)) {
            throw new \InvalidArgumentException("模板 '{$template}' 不存在");
        }
        
        return CacheKVFacade::getByTemplate($template, $params, $callback, $ttl);
    }
    
    /**
     * 设置缓存数据
     * 
     * @param string $template 模板常量
     * @param array $params 参数
     * @param mixed $value 值
     * @param int|null $ttl 过期时间
     * @return bool
     */
    protected function setCache($template, array $params, $value, $ttl = null)
    {
        if (!CacheTemplates::exists($template)) {
            throw new \InvalidArgumentException("模板 '{$template}' 不存在");
        }
        
        return CacheKVFacade::setByTemplate($template, $params, $value, $ttl);
    }
    
    /**
     * 删除缓存数据
     * 
     * @param string $template 模板常量
     * @param array $params 参数
     * @return bool
     */
    protected function forgetCache($template, array $params)
    {
        if (!CacheTemplates::exists($template)) {
            throw new \InvalidArgumentException("模板 '{$template}' 不存在");
        }
        
        return CacheKVFacade::forgetByTemplate($template, $params);
    }
    
    /**
     * 带标签设置缓存
     * 
     * @param string $template 模板常量
     * @param array $params 参数
     * @param mixed $value 值
     * @param array $tags 标签
     * @param int|null $ttl 过期时间
     * @return bool
     */
    protected function setCacheWithTag($template, array $params, $value, array $tags, $ttl = null)
    {
        if (!CacheTemplates::exists($template)) {
            throw new \InvalidArgumentException("模板 '{$template}' 不存在");
        }
        
        return CacheKVFacade::setByTemplateWithTag($template, $params, $value, $tags, $ttl);
    }
    
    /**
     * 清除标签相关的所有缓存
     * 
     * @param string $tag 标签名
     * @return bool
     */
    protected function clearTag($tag)
    {
        return CacheKVFacade::clearTag($tag);
    }
    
    /**
     * 批量获取缓存
     * 
     * @param string $template 模板常量
     * @param array $paramsList 参数列表
     * @param callable $callback 回调函数
     * @return array
     */
    protected function getMultipleCache($template, array $paramsList, callable $callback)
    {
        if (!CacheTemplates::exists($template)) {
            throw new \InvalidArgumentException("模板 '{$template}' 不存在");
        }
        
        $keys = [];
        foreach ($paramsList as $params) {
            $keys[] = CacheKVFacade::getInstance()->getKeyManager()->make($template, $params);
        }
        
        return CacheKVFacade::getMultiple($keys, $callback);
    }
}
