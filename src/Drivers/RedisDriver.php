<?php

namespace Asfop\CacheKV\Drivers;

/**
 * Redis 驱动实现
 */
class RedisDriver implements DriverInterface
{
    /**
     * Redis 实例
     * 
     * @var mixed
     */
    private $redis;

    /**
     * 构造函数
     * 
     * @param mixed $redis Redis 实例
     */
    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    /**
     * 获取缓存值
     */
    public function get($key)
    {
        $value = $this->redis->get($key);
        return $value === false ? null : $value;
    }

    /**
     * 设置缓存值
     */
    public function set($key, $value, $ttl = 0)
    {
        if ($ttl > 0) {
            return $this->redis->setex($key, $ttl, $value);
        } else {
            return $this->redis->set($key, $value);
        }
    }

    /**
     * 删除缓存
     */
    public function delete($key)
    {
        return $this->redis->del($key) > 0;
    }

    /**
     * 检查缓存是否存在
     */
    public function exists($key)
    {
        return $this->redis->exists($key) > 0;
    }

    /**
     * 批量获取缓存
     */
    public function getMultiple(array $keys)
    {
        if (empty($keys)) {
            return array();
        }
        
        $values = $this->redis->mget($keys);
        $result = array();
        
        foreach ($keys as $index => $key) {
            $value = isset($values[$index]) ? $values[$index] : null;
            if ($value !== false) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * 批量设置缓存
     */
    public function setMultiple(array $items, $ttl = 0)
    {
        if (empty($items)) {
            return true;
        }

        if ($ttl > 0) {
            // 有TTL时，使用pipeline批量设置
            $pipe = $this->redis->multi(\Redis::PIPELINE);
            foreach ($items as $key => $value) {
                $pipe->setex($key, $ttl, $value);
            }
            $results = $pipe->exec();
            
            // 检查是否所有操作都成功
            foreach ($results as $result) {
                if (!$result) {
                    return false;
                }
            }
            return true;
        } else {
            // 无TTL时，使用mset
            return $this->redis->mset($items);
        }
    }

    /**
     * 批量删除缓存
     */
    public function deleteMultiple(array $keys)
    {
        if (empty($keys)) {
            return true;
        }
        
        $keyList = array_values($keys);
        return $this->redis->del($keyList) > 0;
    }

    /**
     * 获取集合成员
     */
    public function getSet($key)
    {
        return $this->redis->smembers($key);
    }

    /**
     * 添加到集合
     */
    public function addToSet($key, $member)
    {
        return $this->redis->sadd($key, $member) > 0;
    }

    /**
     * 设置过期时间
     */
    public function expire($key, $ttl)
    {
        return $this->redis->expire($key, $ttl);
    }

    /**
     * 获取键的剩余TTL
     * 
     * @param string $key 缓存键
     * @return int TTL秒数，-1表示永不过期，-2表示键不存在
     */
    public function ttl($key)
    {
        return $this->redis->ttl($key);
    }

    /**
     * 设置键值（如果不存在）
     */
    public function setNx($key, $value, $ttl = 0)
    {
        if ($ttl > 0) {
            return $this->redis->set($key, $value, 'NX', 'EX', $ttl);
        } else {
            return $this->redis->setnx($key, $value);
        }
    }
}
