<?php

declare(strict_types=1);

namespace Asfop\QueryCache\Cache\Drivers;

use Asfop\QueryCache\Cache\CacheDriver;

/**
 * 内存数组缓存驱动。
 * 实现了CacheDriver接口，将数据存储在PHP内存数组中，适用于单次请求内的缓存。
 */
class ArrayDriver implements CacheDriver
{
    /**
     * @var array 存储缓存数据的数组。
     */
    private $storage = [];

    /**
     * 根据键获取缓存项。
     *
     * @param string $key 缓存键。
     * @return mixed|null 缓存数据，如果不存在或已过期则返回null。
     */
    public function get(string $key)
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        $item = $this->storage[$key];
        // 检查缓存是否过期
        if (time() >= $item['expires']) {
            $this->forget($key);
            return null;
        }

        return unserialize($item['value']);
    }

    /**
     * 存储缓存项。
     *
     * @param string $key 缓存键。
     * @param mixed $value 要存储的数据。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储是否成功。
     */
    public function set(string $key, $value, int $ttl): bool
    {
        $this->storage[$key] = [
            'value' => serialize($value),
            'expires' => time() + $ttl,
        ];

        return true;
    }

    /**
     * 移除缓存项。
     *
     * @param string $key 缓存键。
     * @return bool 移除是否成功。
     */
    public function forget(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
            return true;
        }
        return false;
    }

    /**
     * 检查缓存项是否存在。
     *
     * @param string $key 缓存键。
     * @return bool 如果存在则返回true，否则返回false。
     */
    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }
}