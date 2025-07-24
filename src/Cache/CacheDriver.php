<?php

declare(strict_types=1);

namespace Asfop\CacheKV\Cache;

/**
 * CacheDriver 接口定义了所有缓存驱动必须实现的基本操作。
 * 它提供了一个抽象层，使得上层应用逻辑可以与具体的缓存实现（如 Redis, Memcached, Array 等）解耦。
 * 所有缓存操作都基于键值对（key-value）进行，其中 key 是字符串，value 是可序列化的 PHP 数据。
 */
interface CacheDriver
{
    /**
     * 从缓存中获取指定键的值。
     *
     * @param string $key 缓存项的唯一键名。
     * @return mixed|null 键对应的值，如果缓存中不存在或已过期，则返回 null。
     */
    public function get(string $key);

    /**
     * 从缓存中批量获取多个键的值。
     *
     * @param array $keys 缓存项键名的数组。
     * @return array 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据。
     *               如果某个键在缓存中不存在或已过期，则该键不会出现在返回的数组中。
     */
    public function getMultiple(array $keys): array;

    /**
     * 将一个键值对存储到缓存中。
     *
     * @param string $key 缓存项的唯一键名。
     * @param mixed $value 要存储的数据。可以是任意可序列化的 PHP 数据类型。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储操作是否成功。
     */
    public function set(string $key, $value, int $ttl): bool;

    /**
     * 批量将多个键值对存储到缓存中。
     *
     * @param array $values 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据。
     *                      值可以是任意可序列化的 PHP 数据类型。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储操作是否成功。
     */
    public function setMultiple(array $values, int $ttl): bool;

    /**
     * 从缓存中移除指定键的缓存项。
     *
     * @param string $key 要移除的缓存项的键名。
     * @return bool 移除操作是否成功。
     */
    public function forget(string $key): bool;

    /**
     * 检查缓存中是否存在指定键的缓存项。
     *
     * @param string $key 要检查的缓存项的键名。
     * @return bool 如果缓存中存在该键且未过期，则返回 true；否则返回 false。
     */
    public function has(string $key): bool;

    /**
     * 将一个缓存项与一个或多个标签关联。
     * 标签用于对缓存项进行逻辑分组，方便后续通过标签进行批量操作（如清除）。
     *
     * @param string $key 缓存项的唯一键名。
     * @param array $tags 包含一个或多个标签名的数组。
     * @return bool 关联操作是否成功。
     */
    public function tag(string $key, array $tags): bool;

    /**
     * 清除指定标签下的所有缓存项。
     * 此操作会遍历与该标签关联的所有缓存键，并将其从缓存中移除。
     *
     * @param string $tag 要清除的标签名。
     * @return bool 清除操作是否成功。
     */
    public function clearTag(string $tag): bool;

    /**
     * 获取缓存的统计信息，例如缓存命中次数和未命中次数。
     *
     * @return array 包含 'hits'（缓存命中次数）、'misses'（缓存未命中次数）和 'hit_rate'（缓存命中率）的关联数组。
     */
    public function getStats(): array;

    /**
     * 更新缓存项的过期时间。
     * 主要用于实现滑动过期，当缓存项被访问时，延长其有效期。
     *
     * @param string $key 缓存项的唯一键名。
     * @param int $ttl 新的缓存有效期（秒）。
     * @return bool 更新操作是否成功。
     */
    public function touch(string $key, int $ttl): bool;
}