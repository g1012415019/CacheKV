<?php

namespace Asfop\CacheKV\Drivers;

/**
 * 数组驱动器（用于测试）
 * 
 * 将数据存储在内存数组中，仅用于测试目的
 */
class ArrayDriver implements DriverInterface
{
    /**
     * 存储数据的数组
     * 
     * @var array
     */
    private $data = array();

    /**
     * 获取缓存值
     * 
     * @param string $key 缓存键
     * @return mixed|null 缓存值，不存在时返回null
     */
    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * 设置缓存值
     * 
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒），0表示永不过期
     * @return bool 成功返回true，失败返回false
     */
    public function set($key, $value, $ttl = 0)
    {
        $this->data[$key] = $value;
        return true;
    }

    /**
     * 删除缓存
     * 
     * @param string $key 缓存键
     * @return bool 成功返回true，失败返回false
     */
    public function delete($key)
    {
        if (isset($this->data[$key])) {
            unset($this->data[$key]);
            return true;
        }
        return false;
    }

    /**
     * 检查缓存是否存在
     * 
     * @param string $key 缓存键
     * @return bool 存在返回true，不存在返回false
     */
    public function exists($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * 批量获取缓存值
     * 
     * @param array $keys 缓存键数组
     * @return array 关联数组，键为缓存键，值为缓存值
     */
    public function getMultiple(array $keys)
    {
        $result = array();
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                $result[$key] = $this->data[$key];
            }
        }
        return $result;
    }

    /**
     * 批量设置缓存值
     * 
     * @param array $values 关联数组，键为缓存键，值为缓存值
     * @param int $ttl 过期时间（秒），0表示永不过期
     * @return bool 成功返回true，失败返回false
     */
    public function setMultiple(array $values, $ttl = 0)
    {
        foreach ($values as $key => $value) {
            $this->data[$key] = $value;
        }
        return true;
    }

    /**
     * 批量删除缓存
     * 
     * @param array $keys 缓存键数组
     * @return bool 成功返回true，失败返回false
     */
    public function deleteMultiple(array $keys)
    {
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                unset($this->data[$key]);
            }
        }
        return true;
    }

    /**
     * 清空所有缓存
     * 
     * @return bool 成功返回true，失败返回false
     */
    public function flush()
    {
        $this->data = array();
        return true;
    }

    /**
     * 按前缀删除缓存
     * 
     * @param string $prefix 前缀
     * @return bool 成功返回true，失败返回false
     */
    public function deleteByPrefix($prefix)
    {
        foreach ($this->data as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                unset($this->data[$key]);
            }
        }
        return true;
    }

    /**
     * 获取统计信息
     * 
     * @return array 统计信息数组
     */
    public function getStats()
    {
        return array(
            'keys' => count($this->data),
            'memory_usage' => memory_get_usage(),
        );
    }
}
