<?php

namespace Asfop\CacheKV\Key;

/**
 * 缓存键集合类
 * 
 * 包装 CacheKey 数组，提供便捷的操作方法
 */
class CacheKeyCollection
{
    /**
     * CacheKey 对象数组
     * 
     * @var CacheKey[]
     */
    private $cacheKeys;

    /**
     * 构造函数
     * 
     * @param CacheKey[] $cacheKeys 缓存键对象数组
     */
    public function __construct(array $cacheKeys)
    {
        $this->cacheKeys = $cacheKeys;
    }

    /**
     * 获取 CacheKey 对象数组
     * 
     * @return CacheKey[]
     */
    public function getKeys()
    {
        return $this->cacheKeys;
    }

    /**
     * 转换为字符串数组
     * 
     * @return string[]
     */
    public function toStrings()
    {
        $keyStrings = array();
        foreach ($this->cacheKeys as $cacheKey) {
            $keyStrings[] = (string)$cacheKey;
        }
        return $keyStrings;
    }

    /**
     * 获取集合大小
     * 
     * @return int
     */
    public function count()
    {
        return count($this->cacheKeys);
    }

    /**
     * 检查是否为空
     * 
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->cacheKeys);
    }

    /**
     * 获取第一个 CacheKey
     * 
     * @return CacheKey|null
     */
    public function first()
    {
        return !empty($this->cacheKeys) ? $this->cacheKeys[0] : null;
    }

    /**
     * 获取最后一个 CacheKey
     * 
     * @return CacheKey|null
     */
    public function last()
    {
        return !empty($this->cacheKeys) ? end($this->cacheKeys) : null;
    }

    /**
     * 支持 foreach 遍历
     * 
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->cacheKeys);
    }

    /**
     * 支持数组访问
     * 
     * @param int $offset 索引
     * @return CacheKey|null
     */
    public function get($offset)
    {
        return isset($this->cacheKeys[$offset]) ? $this->cacheKeys[$offset] : null;
    }

    /**
     * 转换为数组格式（用于调试）
     * 
     * @return array
     */
    public function toArray()
    {
        $result = array();
        foreach ($this->cacheKeys as $i => $cacheKey) {
            $result[$i] = array(
                'string' => (string)$cacheKey,
                'group' => $cacheKey->getGroupName(),
                'key' => $cacheKey->getKeyName(),
                'params' => $cacheKey->getParams()
            );
        }
        return $result;
    }
}
