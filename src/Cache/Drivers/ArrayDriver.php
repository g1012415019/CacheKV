<?php

namespace Asfop\CacheKV\Cache\Drivers;

use Asfop\CacheKV\Cache\CacheDriver;

/**
 * ArrayDriver 是一个基于内存数组实现的缓存驱动
 * 
 * 主要用于开发和测试环境，数据只在当前请求生命周期内有效，不会持久化。
 * 支持过期时间、标签管理等完整的缓存功能。
 */
class ArrayDriver implements CacheDriver
{
    /**
     * @var array 存储缓存数据的数组
     */
    protected $cache = [];

    /**
     * @var array 存储过期时间的数组
     */
    protected $expiration = [];

    /**
     * @var array 存储标签关联的数组 [tag => [key1, key2, ...]]
     */
    protected $tags = [];

    /**
     * @var array 存储键对应标签的数组 [key => [tag1, tag2, ...]]
     */
    protected $keyTags = [];

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
        if (empty($key)) {
            $this->misses++;
            return null;
        }

        // 检查键是否存在
        if (!array_key_exists($key, $this->cache)) {
            $this->misses++;
            return null;
        }

        // 检查是否过期
        if ($this->isExpired($key)) {
            $this->removeExpiredKey($key);
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
     * @return array 键值对数组，不存在或已过期的键不会出现在结果中
     */
    public function getMultiple(array $keys)
    {
        $results = [];
        
        foreach ($keys as $key) {
            if (empty($key)) {
                $this->misses++;
                continue;
            }

            if (array_key_exists($key, $this->cache) && !$this->isExpired($key)) {
                $results[$key] = $this->cache[$key];
                $this->hits++;
            } else {
                if ($this->isExpired($key)) {
                    $this->removeExpiredKey($key);
                }
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
        if (empty($key) || $ttl <= 0) {
            return false;
        }

        $this->cache[$key] = $value;
        $this->expiration[$key] = time() + $ttl;
        
        return true;
    }

    /**
     * 批量将多个键值对存储到缓存中
     *
     * @param array $values 键值对数组
     * @param int $ttl 缓存有效期（秒）
     * @return bool 存储操作是否成功
     */
    public function setMultiple(array $values, $ttl)
    {
        if (empty($values) || $ttl <= 0) {
            return false;
        }

        $expirationTime = time() + $ttl;
        
        foreach ($values as $key => $value) {
            if (!empty($key)) {
                $this->cache[$key] = $value;
                $this->expiration[$key] = $expirationTime;
            }
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
        if (empty($key)) {
            return false;
        }

        $existed = array_key_exists($key, $this->cache);
        
        // 移除缓存数据
        unset($this->cache[$key]);
        unset($this->expiration[$key]);
        
        // 移除标签关联
        if (isset($this->keyTags[$key])) {
            foreach ($this->keyTags[$key] as $tag) {
                if (isset($this->tags[$tag])) {
                    $this->tags[$tag] = array_diff($this->tags[$tag], [$key]);
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
     * @param string $key 缓存项的唯一键名
     * @return bool 如果缓存中存在该键且未过期，则返回 true
     */
    public function has($key)
    {
        if (empty($key) || !array_key_exists($key, $this->cache)) {
            return false;
        }

        if ($this->isExpired($key)) {
            $this->removeExpiredKey($key);
            return false;
        }

        return true;
    }

    /**
     * 将一个缓存项与一个或多个标签关联
     *
     * @param string $key 缓存项的唯一键名
     * @param array $tags 标签名数组
     * @return bool 关联操作是否成功
     */
    public function tag($key, array $tags)
    {
        if (empty($key) || empty($tags)) {
            return false;
        }

        // 确保键存在于缓存中
        if (!array_key_exists($key, $this->cache)) {
            return false;
        }

        foreach ($tags as $tag) {
            if (!empty($tag)) {
                // 添加标签到键的关联
                if (!isset($this->keyTags[$key])) {
                    $this->keyTags[$key] = [];
                }
                if (!in_array($tag, $this->keyTags[$key])) {
                    $this->keyTags[$key][] = $tag;
                }

                // 添加键到标签的关联
                if (!isset($this->tags[$tag])) {
                    $this->tags[$tag] = [];
                }
                if (!in_array($key, $this->tags[$tag])) {
                    $this->tags[$tag][] = $key;
                }
            }
        }

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
        if (empty($tag) || !isset($this->tags[$tag])) {
            return false;
        }

        $keysToRemove = $this->tags[$tag];
        
        foreach ($keysToRemove as $key) {
            $this->forget($key);
        }

        return !empty($keysToRemove);
    }

    /**
     * 获取缓存的统计信息
     *
     * @return array 包含命中次数、未命中次数和命中率的数组
     */
    public function getStats()
    {
        $total = $this->hits + $this->misses;
        $hitRate = $total > 0 ? ($this->hits / $total) * 100 : 0;

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => round($hitRate, 2)
        ];
    }

    /**
     * 更新缓存项的过期时间（滑动过期）
     *
     * @param string $key 缓存项的唯一键名
     * @param int $ttl 新的缓存有效期（秒）
     * @return bool 更新操作是否成功
     */
    public function touch($key, $ttl)
    {
        if (empty($key) || $ttl <= 0) {
            return false;
        }

        if (!array_key_exists($key, $this->cache) || $this->isExpired($key)) {
            return false;
        }

        $this->expiration[$key] = time() + $ttl;
        return true;
    }

    /**
     * 检查键是否已过期
     *
     * @param string $key 缓存项的键名
     * @return bool 如果已过期返回 true
     */
    protected function isExpired($key)
    {
        if (!isset($this->expiration[$key])) {
            return false;
        }

        return time() > $this->expiration[$key];
    }

    /**
     * 移除已过期的键
     *
     * @param string $key 要移除的键名
     * @return void
     */
    protected function removeExpiredKey($key)
    {
        unset($this->cache[$key]);
        unset($this->expiration[$key]);
        
        // 清理标签关联
        if (isset($this->keyTags[$key])) {
            foreach ($this->keyTags[$key] as $tag) {
                if (isset($this->tags[$tag])) {
                    $this->tags[$tag] = array_diff($this->tags[$tag], [$key]);
                    if (empty($this->tags[$tag])) {
                        unset($this->tags[$tag]);
                    }
                }
            }
            unset($this->keyTags[$key]);
        }
    }

    /**
     * 清理所有过期的缓存项
     *
     * @return int 清理的项目数量
     */
    public function cleanup()
    {
        $cleaned = 0;
        $currentTime = time();
        
        foreach ($this->expiration as $key => $expireTime) {
            if ($currentTime > $expireTime) {
                $this->removeExpiredKey($key);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }

    /**
     * 获取当前缓存的项目数量
     *
     * @return int 缓存项目数量
     */
    public function count()
    {
        // 先清理过期项目
        $this->cleanup();
        return count($this->cache);
    }

    /**
     * 清空所有缓存
     *
     * @return bool 操作是否成功
     */
    public function flush()
    {
        $this->cache = [];
        $this->expiration = [];
        $this->tags = [];
        $this->keyTags = [];
        $this->hits = 0;
        $this->misses = 0;
        
        return true;
    }
}
