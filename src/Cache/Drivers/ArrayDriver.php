<?php

namespace Asfop\CacheKV\Cache\Drivers;

use Asfop\CacheKV\Cache\CacheDriver;

/**
 * ArrayDriver 是一个基于内存数组实现的缓存驱动。
 * 它实现了 CacheDriver 接口，主要用于测试和开发环境。
 * 注意：此驱动的数据只在当前请求生命周期内有效，不会持久化。
 */
class ArrayDriver implements CacheDriver
{
    /**
     * @var array 存储缓存数据的数组
     */
    protected $cache = array();

    /**
     * @var array 存储过期时间的数组
     */
    protected $expiration = array();

    /**
     * @var array 存储标签关联的数组
     */
    protected $tags = array();

    /**
     * @var array 存储键对应标签的数组
     */
    protected $keyTags = array();

    /**
     * @var int 缓存命中次数
     */
    protected $hits = 0;

    /**
     * @var int 缓存未命中次数
     */
    protected $misses = 0;

    /**
     * 从缓存中获取指定键的值
     *
     * @param string $key 缓存项的唯一键名
     * @return mixed|null 缓存中存储的值，如果键不存在或已过期，则返回 null
     */
    public function get($key)
    {
        if (!$this->has($key)) {
            $this->misses++;
            return null;
        }

        $this->hits++;
        return $this->cache[$key];
    }

    /**
     * 从缓存中批量获取多个键的值
     *
     * @param array $keys 缓存项键名的数组
     * @return array 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据
     */
    public function getMultiple(array $keys)
    {
        $results = array();
        
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $results[$key] = $this->cache[$key];
                $this->hits++;
            } else {
                $this->misses++;
            }
        }

        return $results;
    }

    /**
     * 将一个键值对存储到缓存中
     *
     * @param string $key 缓存项的唯一键名
     * @param mixed $value 要存储的数据
     * @param int $ttl 缓存有效期（秒）
     * @return bool 存储操作是否成功
     */
    public function set($key, $value, $ttl)
    {
        $this->cache[$key] = $value;
        $this->expiration[$key] = time() + $ttl;
        return true;
    }

    /**
     * 批量将多个键值对存储到缓存中
     *
     * @param array $values 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据
     * @param int $ttl 缓存有效期（秒）
     * @return bool 存储操作是否成功
     */
    public function setMultiple(array $values, $ttl)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * 从缓存中移除指定键的缓存项
     *
     * @param string $key 要移除的缓存项的键名
     * @return bool 移除操作是否成功
     */
    public function forget($key)
    {
        $existed = isset($this->cache[$key]);
        
        unset($this->cache[$key]);
        unset($this->expiration[$key]);
        
        // 清理标签关联
        if (isset($this->keyTags[$key])) {
            foreach ($this->keyTags[$key] as $tag) {
                if (isset($this->tags[$tag])) {
                    $this->tags[$tag] = array_diff($this->tags[$tag], array($key));
                    if (empty($this->tags[$tag])) {
                        unset($this->tags[$tag]);
                    }
                }
            }
            unset($this->keyTags[$key]);
        }
        
        return $existed;
    }

    /**
     * 检查缓存中是否存在指定键的缓存项
     *
     * @param string $key 要检查的缓存项的键名
     * @return bool 如果缓存中存在该键且未过期，则返回 true；否则返回 false
     */
    public function has($key)
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        if (isset($this->expiration[$key]) && $this->expiration[$key] < time()) {
            $this->forget($key);
            return false;
        }

        return true;
    }

    /**
     * 将一个缓存项与一个或多个标签关联
     *
     * @param string $key 缓存项的唯一键名
     * @param array $tags 包含一个或多个标签名的数组
     * @return bool 关联操作是否成功
     */
    public function tag($key, array $tags)
    {
        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = array();
            }
            if (!in_array($key, $this->tags[$tag])) {
                $this->tags[$tag][] = $key;
            }
        }

        $this->keyTags[$key] = array_unique(array_merge(
            isset($this->keyTags[$key]) ? $this->keyTags[$key] : array(),
            $tags
        ));

        return true;
    }

    /**
     * 清除指定标签下的所有缓存项
     *
     * @param string $tag 要清除的标签名
     * @return bool 清除操作是否成功
     */
    public function clearTag($tag)
    {
        if (!isset($this->tags[$tag])) {
            return false;
        }

        $keys = $this->tags[$tag];
        foreach ($keys as $key) {
            $this->forget($key);
        }

        return true;
    }

    /**
     * 获取缓存的统计信息
     *
     * @return array 包含 'hits'、'misses' 和 'hit_rate' 的关联数组
     */
    public function getStats()
    {
        $total = $this->hits + $this->misses;
        $hitRate = $total > 0 ? ($this->hits / $total) * 100 : 0;

        return array(
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => round($hitRate, 2)
        );
    }

    /**
     * 更新缓存项的过期时间
     *
     * @param string $key 缓存项的唯一键名
     * @param int $ttl 新的缓存有效期（秒）
     * @return bool 更新操作是否成功
     */
    public function touch($key, $ttl)
    {
        if (!$this->has($key)) {
            return false;
        }

        $this->expiration[$key] = time() + $ttl;
        return true;
    }
}
