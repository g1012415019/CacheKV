<?php

namespace Asfop\CacheKV\Drivers;

/**
 * 缓存驱动接口
 */
interface DriverInterface
{
    /**
     * 获取缓存值
     * 
     * @param string $key 缓存键
     * @return string|null
     */
    public function get($key);
    
    /**
     * 设置缓存值
     * 
     * @param string $key 缓存键
     * @param string $value 缓存值
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public function set($key, $value, $ttl = 0);
    
    /**
     * 删除缓存
     * 
     * @param string $key 缓存键
     * @return bool
     */
    public function delete($key);
    
    /**
     * 检查缓存是否存在
     * 
     * @param string $key 缓存键
     * @return bool
     */
    public function exists($key);
    
    /**
     * 批量获取缓存
     * 
     * @param array $keys 缓存键数组
     * @return array
     */
    public function getMultiple(array $keys);
    
    /**
     * 批量设置缓存
     * 
     * @param array $items 键值对数组，格式：['key' => 'value', ...]
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public function setMultiple(array $items, $ttl = 0);
    
    /**
     * 批量删除缓存
     * 
     * @param array $keys 缓存键数组
     * @return bool
     */
    public function deleteMultiple(array $keys);
    
    /**
     * 获取集合成员
     * 
     * @param string $key 集合键
     * @return array
     */
    public function getSet($key);
    
    /**
     * 添加到集合
     * 
     * @param string $key 集合键
     * @param string $member 成员
     * @return bool
     */
    public function addToSet($key, $member);
    
    /**
     * 设置过期时间
     * 
     * @param string $key 缓存键
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public function expire($key, $ttl);
    
    /**
     * 获取键的剩余TTL
     * 
     * @param string $key 缓存键
     * @return int TTL秒数，-1表示永不过期，-2表示键不存在
     */
    public function ttl($key);
    
    /**
     * 设置键值（如果不存在）
     * 
     * @param string $key 缓存键
     * @param string $value 缓存值
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public function setNx($key, $value, $ttl = 0);
}
