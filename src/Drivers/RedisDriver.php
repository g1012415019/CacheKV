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
     * 按模式删除缓存键
     * 
     * @param string $pattern 匹配模式，支持通配符 * 和 ?
     * @return int 删除的键数量
     */
    public function deleteByPattern($pattern)
    {
        // 使用 SCAN 命令安全地遍历键，避免阻塞Redis
        $iterator = null;
        $deletedCount = 0;
        $batchSize = 1000; // 每批处理的键数量
        
        do {
            // 使用 SCAN 命令获取匹配的键
            $keys = $this->redis->scan($iterator, $pattern, $batchSize);
            
            if ($keys !== false && !empty($keys)) {
                // 批量删除找到的键
                $deleted = $this->redis->del($keys);
                $deletedCount += $deleted;
            }
        } while ($iterator > 0);
        
        return $deletedCount;
    }
}
