<?php

declare(strict_types=1);

namespace Asfop\CacheKV\Cache\Drivers;

use Asfop\CacheKV\Cache\CacheDriver;

/**
 * ArrayDriver 是一个基于 PHP 内存数组实现的缓存驱动。
 * 它实现了 CacheDriver 接口，将数据存储在当前请求的内存中，适用于单次请求内的缓存需求。
 * 缓存的数据结构是键值对（key-value），其中 key 是字符串，value 是可序列化的 PHP 数据类型。
 */
class ArrayDriver implements CacheDriver
{
    /**
     * @var array $storage 存储缓存数据的内部数组。
     * 结构示例：
     * [
     *   'cache_key' => ['value' => serialized_data, 'expires' => timestamp],
     * ]
     */
    private $storage = [];

    /**
     * @var array $tags 存储标签与缓存键的映射关系。
     * 结构示例：
     * [
     *   'tag_name' => ['cache_key1', 'cache_key2'],
     * ]
     */
    private $tags = [];

    /**
     * @var int $hits 缓存命中次数统计。
     */
    private $hits = 0;

    /**
     * @var int $misses 缓存未命中次数统计。
     */
    private $misses = 0;

    /**
     * 从内存缓存中获取指定键的值。
     * 如果缓存项存在且未过期，则返回其值；否则返回 null。
     * 此方法会更新缓存命中/未命中统计。
     *
     * @param string $key 缓存项的唯一键名。
     * @return mixed|null 缓存中存储的值，如果键不存在或已过期，则返回 null。
     */
    public function get(string $key)
    {
        if (!isset($this->storage[$key])) {
            $this->misses++;
            return null;
        }

        $item = $this->storage[$key];

        // 检查缓存是否过期
        if (time() >= $item['expires']) {
            $this->forget($key);
            $this->misses++;
            return null;
        }

        $this->hits++;
        return unserialize($item['value']);
    }

    /**
     * 从内存缓存中批量获取多个键的值。
     * 此方法会遍历请求的键，并为每个键调用 `get` 方法，以处理过期和统计。
     *
     * @param array $keys 缓存项键名的数组。
     * @return array 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据。
     *               如果某个键在缓存中不存在或已过期，则该键不会出现在返回的数组中。
     */
    public function getMultiple(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $value = $this->get($key); // 使用get方法来处理过期和统计
            if ($value !== null) {
                $results[$key] = $value;
            }
        }
        return $results;
    }

    /**
     * 将一个键值对存储到内存缓存中。
     * 数据会被序列化后存储，并记录过期时间。
     *
     * @param string $key 缓存项的唯一键名。
     * @param mixed $value 要存储的数据。可以是任意可序列化的 PHP 数据类型。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储操作是否成功（在 ArrayDriver 中总是返回 true）。
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
     * 批量将多个键值对存储到内存缓存中。
     * 此方法会遍历提供的键值对数组，并为每个键值对调用 `set` 方法进行存储。
     *
     * @param array $values 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据。
     *                      值可以是任意可序列化的 PHP 数据类型。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储操作是否成功（在 ArrayDriver 中总是返回 true）。
     */
    public function setMultiple(array $values, int $ttl): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * 从内存缓存中移除指定键的缓存项。
     * 如果缓存项存在，则将其从存储中移除，并解除其与所有标签的关联。
     *
     * @param string $key 要移除的缓存项的唯一键名。
     * @return bool 移除操作是否成功。
     */
    public function forget(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
            // 移除与该键关联的标签
            foreach ($this->tags as $tag => $keys) {
                $index = array_search($key, $keys);
                if ($index !== false) {
                    unset($this->tags[$tag][$index]);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 检查内存缓存中是否存在指定键的缓存项。
     * 此方法会同时检查缓存项是否存在以及是否已过期。
     *
     * @param string $key 缓存项的唯一键名。
     * @return bool 如果缓存中存在该键且未过期，则返回 true；否则返回 false。
     */
    public function has(string $key): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        $item = $this->storage[$key];

        if (time() >= $item['expires']) {
            $this->forget($key);
            return false;
        }

        return true;
    }

    /**
     * 将一个缓存项与一个或多个标签关联。
     * 标签用于对缓存项进行逻辑分组，方便后续通过标签进行批量操作（如清除）。
     * 在 ArrayDriver 中，标签通过维护一个从标签名到缓存键数组的映射来实现。
     *
     * @param string $key 缓存项的唯一键名。
     * @param array $tags 包含一个或多个标签名的数组。
     * @return bool 关联操作是否成功（在 ArrayDriver 中总是返回 true）。
     */
    public function tag(string $key, array $tags): bool
    {
        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            if (!in_array($key, $this->tags[$tag])) {
                $this->tags[$tag][] = $key;
            }
        }
        return true;
    }

    /**
     * 清除指定标签下的所有缓存项。
     * 此方法会遍历与给定标签关联的所有缓存键，并逐一将其从缓存中移除。
     * 移除后，该标签与这些键的关联也会被清除。
     *
     * @param string $tag 要清除的标签名。
     * @return bool 清除操作是否成功。
     */
    public function clearTag(string $tag): bool
    {
        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $key) {
                $this->forget($key);
            }
            unset($this->tags[$tag]);
            return true;
        }
        return false;
    }

    /**
     * 获取内存缓存的统计信息，包括命中次数、未命中次数和命中率。
     *
     * @return array 包含 'hits'（缓存命中次数）、'misses'（缓存未命中次数）和 'hit_rate'（缓存命中率）的关联数组。
     */
    public function getStats(): array
    {
        $total = $this->hits + $this->misses;
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => $total > 0 ? ($this->hits / $total) : 0,
        ];
    }

    /**
     * 更新内存缓存中指定键的缓存项的过期时间。
     * 如果缓存项存在，则将其过期时间更新为当前时间加上新的 TTL。
     * 主要用于实现滑动过期，当缓存项被访问时，延长其有效期。
     *
     * @param string $key 缓存项的唯一键名。
     * @param int $ttl 新的缓存有效期（秒）。
     * @return bool 更新操作是否成功。如果键不存在，则返回 false；否则返回 true。
     */
    public function touch(string $key, int $ttl): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        $this->storage[$key]['expires'] = time() + $ttl;
        return true;
    }
}