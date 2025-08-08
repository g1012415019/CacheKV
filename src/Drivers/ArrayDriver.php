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
     * 存储TTL信息的数组
     * 
     * @var array
     */
    private $ttls = array();

    /**
     * 获取缓存值
     * 
     * @param string $key 缓存键
     * @return mixed|null 缓存值，不存在时返回null
     */
    public function get($key)
    {
        // 检查是否过期
        if ($this->isExpired($key)) {
            $this->delete($key);
            return null;
        }
        
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
        
        if ($ttl > 0) {
            $this->ttls[$key] = time() + $ttl;
        } else {
            unset($this->ttls[$key]);
        }
        
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
        $existed = isset($this->data[$key]);
        unset($this->data[$key]);
        unset($this->ttls[$key]);
        return $existed;
    }

    /**
     * 检查缓存是否存在
     * 
     * @param string $key 缓存键
     * @return bool 存在返回true，不存在返回false
     */
    public function exists($key)
    {
        if ($this->isExpired($key)) {
            $this->delete($key);
            return false;
        }
        
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
            $value = $this->get($key);
            if ($value !== null) {
                $result[$key] = $value;
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
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * 设置过期时间
     * 
     * @param string $key 缓存键
     * @param int $ttl 过期时间（秒）
     * @return bool 成功返回true，失败返回false
     */
    public function expire($key, $ttl)
    {
        if (!isset($this->data[$key])) {
            return false;
        }
        
        if ($ttl > 0) {
            $this->ttls[$key] = time() + $ttl;
        } else {
            unset($this->ttls[$key]);
        }
        
        return true;
    }

    /**
     * 获取键的剩余TTL
     * 
     * @param string $key 缓存键
     * @return int TTL秒数，-1表示永不过期，-2表示键不存在
     */
    public function ttl($key)
    {
        if (!isset($this->data[$key])) {
            return -2; // 键不存在
        }
        
        if (!isset($this->ttls[$key])) {
            return -1; // 永不过期
        }
        
        $remaining = $this->ttls[$key] - time();
        return $remaining > 0 ? $remaining : -2; // 已过期视为不存在
    }

    /**
     * 按模式删除缓存键
     * 
     * @param string $pattern 匹配模式，支持通配符 * 和 ?
     * @return int 删除的键数量
     */
    public function deleteByPattern($pattern)
    {
        $deletedCount = 0;
        $regex = $this->patternToRegex($pattern);
        
        foreach (array_keys($this->data) as $key) {
            if (preg_match($regex, $key)) {
                $this->delete($key);
                $deletedCount++;
            }
        }
        
        return $deletedCount;
    }

    /**
     * 检查键是否过期
     * 
     * @param string $key 缓存键
     * @return bool 过期返回true，未过期返回false
     */
    private function isExpired($key)
    {
        if (!isset($this->ttls[$key])) {
            return false; // 没有TTL，永不过期
        }
        
        return time() > $this->ttls[$key];
    }

    /**
     * 将通配符模式转换为正则表达式
     * 
     * @param string $pattern 通配符模式
     * @return string 正则表达式
     */
    private function patternToRegex($pattern)
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        $pattern = str_replace('\?', '.', $pattern);
        return '/^' . $pattern . '$/';
    }

    /**
     * 清空所有缓存（测试辅助方法）
     * 
     * @return bool 成功返回true，失败返回false
     */
    public function flush()
    {
        $this->data = array();
        $this->ttls = array();
        return true;
    }
}
