<?php

declare(strict_types=1);

namespace Asfop\QueryCache\Cache;

/**
 * 缓存驱动接口。
 * 定义了缓存操作的基本契约，使得QueryCache核心逻辑与具体缓存实现解耦。
 */
interface CacheDriver
{
    /**
     * 根据键获取缓存项。
     *
     * @param string $key 缓存键。
     * @return mixed|null 缓存数据，如果不存在或已过期则返回null。
     */
    public function get(string $key);

    /**
     * 存储缓存项。
     *
     * @param string $key 缓存键。
     * @param mixed $value 要存储的数据。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储是否成功。
     */
    public function set(string $key, $value, int $ttl): bool;

    /**
     * 移除缓存项。
     *
     * @param string $key 缓存键。
     * @return bool 移除是否成功。
     */
    public function forget(string $key): bool;

    /**
     * 检查缓存项是否存在。
     *
     * @param string $key 缓存键。
     * @return bool 如果存在则返回true，否则返回false。
     */
    public function has(string $key): bool;
}