<?php

declare(strict_types=1);

namespace Asfop\CacheKV\Cache\Drivers;

use Asfop\CacheKV\Cache\CacheDriver;

/**
 * MultiLevelCacheDriver 实现了多级缓存机制。
 * 它允许将多个 CacheDriver 实例组合成一个缓存层级，
 * 当数据被请求时，会按照驱动在构造函数中提供的顺序（优先级从高到低）依次尝试获取。
 * 如果在较低级别的缓存中命中，数据会被回填到较高级别的缓存中，以优化后续访问。
 * 缓存的数据结构是键值对（key-value），其中 key 是字符串，value 是可序列化的 PHP 数据类型。
 */
class MultiLevelCacheDriver implements CacheDriver
{
    /**
     * @var CacheDriver[] $drivers 缓存驱动数组，按优先级从高到低排列。
     * 数组中的第一个驱动优先级最高，最后一个优先级最低。
     */
    private array $drivers;

    /**
     * 构造函数。
     *
     * @param CacheDriver[] $drivers 一个包含 CacheDriver 实例的数组，按照优先级从高到低排列。
     *                                数组中的第一个驱动优先级最高，最后一个优先级最低。
     */
    public function __construct(array $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * 从多级缓存中获取指定键的值。
     * 它会按照驱动的优先级（从高到低）依次尝试从每个缓存驱动中获取数据。
     * 如果在某个较低级别的缓存中命中，数据不会自动回填到较高级别的缓存中，
     * 因为当前设计中 `get` 方法不返回原始 TTL，无法进行精确回填。
     *
     * @param string $key 缓存项的唯一键名。
     * @return mixed|null 缓存中存储的值，如果键在所有缓存级别中都不存在或已过期，则返回 null。
     */
    public function get(string $key)

    /**
     * 从多级缓存中批量获取多个键的值。
     * 此方法会按照驱动的优先级（从高到低）依次尝试从每个缓存驱动中获取数据。
     * 对于在某个级别未命中的键，会继续尝试从下一个级别的驱动中获取，直到所有键都被处理或所有驱动都被尝试。
     *
     * @param array $keys 缓存项键名的数组。
     * @return array 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据。
     *               如果某个键在所有缓存级别中都不存在或已过期，则该键不会出现在返回的数组中。
     */
    public function getMultiple(array $keys): array
    {
        $results = [];
        $missingKeys = $keys;

        foreach ($this->drivers as $driver) {
            if (empty($missingKeys)) {
                break;
            }
            $fetched = $driver->getMultiple($missingKeys);
            foreach ($fetched as $key => $value) {
                $results[$key] = $value;
                $index = array_search($key, $missingKeys);
                if ($index !== false) {
                    unset($missingKeys[$index]);
                }
            }
            $missingKeys = array_values($missingKeys); // Re-index array
        }
        return $results;
    }

    /**
     * 将一个键值对存储到所有级别的缓存中。
     * 数据会同时写入所有配置的缓存驱动。如果任何一个驱动写入失败，整个操作将被视为失败。
     *
     * @param string $key 缓存项的唯一键名。
     * @param mixed $value 要存储的数据。可以是任意可序列化的 PHP 数据类型。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储操作是否成功。只有当所有驱动都成功存储时才返回 true。
     */
    public function set(string $key, $value, int $ttl): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 批量将多个键值对存储到所有级别的缓存中。
     * 数据会同时写入所有配置的缓存驱动。如果任何一个驱动写入失败，整个操作将被视为失败。
     *
     * @param array $values 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据。
     *                      值可以是任意可序列化的 PHP 数据类型。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储操作是否成功。只有当所有驱动都成功存储时才返回 true。
     */
    public function setMultiple(array $values, int $ttl): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->setMultiple($values, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 从所有级别的缓存中移除指定键的缓存项。
     * 此操作会尝试从所有配置的缓存驱动中移除该键。如果任何一个驱动移除失败，整个操作将被视为失败。
     *
     * @param string $key 要移除的缓存项的唯一键名。
     * @return bool 移除操作是否成功。只有当所有驱动都成功移除时才返回 true。
     */
    public function forget(string $key): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->forget($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 检查多级缓存中是否存在指定键的缓存项。
     * 它会按照驱动的优先级（从高到低）依次尝试从每个缓存驱动中检查是否存在该键。
     * 只要在任何一个级别的缓存中找到该键（且未过期），就返回 true。
     *
     * @param string $key 缓存项的唯一键名。
     * @return bool 如果缓存中存在该键且未过期，则返回 true；否则返回 false。
     */
    public function has(string $key): bool
    {
        foreach ($this->drivers as $driver) {
            if ($driver->has($key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 将一个缓存项与一个或多个标签关联到所有级别的缓存中。
     * 此操作会尝试将该键与所有配置的缓存驱动中的标签进行关联。如果任何一个驱动关联失败，整个操作将被视为失败。
     *
     * @param string $key 缓存项的唯一键名。
     * @param array $tags 包含一个或多个标签名的数组。
     * @return bool 关联操作是否成功。只有当所有驱动都成功关联时才返回 true。
     */
    public function tag(string $key, array $tags): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->tag($key, $tags)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 清除指定标签下的所有缓存项，从所有级别的缓存中移除。
     * 此操作会尝试从所有配置的缓存驱动中清除与该标签关联的缓存项。如果任何一个驱动清除失败，整个操作将被视为失败。
     *
     * @param string $tag 要清除的标签名。
     * @return bool 清除操作是否成功。只有当所有驱动都成功清除时才返回 true。
     */
    public function clearTag(string $tag): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->clearTag($tag)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 获取多级缓存的统计信息，包括总命中次数、总未命中次数和总命中率。
     * 统计信息通过汇总所有配置的缓存驱动的统计数据来计算。
     *
     * @return array 包含 'hits'（总命中次数）、'misses'（总未命中次数）和 'hit_rate'（总命中率）的关联数组。
     */
    public function getStats(): array
    {
        $totalHits = 0;
        $totalMisses = 0;
        foreach ($this->drivers as $driver) {
            $stats = $driver->getStats();
            $totalHits += $stats['hits'];
            $totalMisses += $stats['misses'];
        }
        $total = $totalHits + $totalMisses;
        return [
            'hits' => $totalHits,
            'misses' => $totalMisses,
            'hit_rate' => $total > 0 ? ($totalHits / $total) : 0,
        ];
    }

    /**
     * 更新多级缓存中指定键的缓存项的过期时间。
     * 此操作会尝试更新所有配置的缓存驱动中该键的过期时间。如果任何一个驱动更新失败，整个操作将被视为失败。
     * 主要用于实现滑动过期，当缓存项被访问时，延长其有效期。
     *
     * @param string $key 缓存项的唯一键名。
     * @param int $ttl 新的缓存有效期（秒）。
     * @return bool 更新操作是否成功。只有当所有驱动都成功更新时才返回 true。
     */
    public function touch(string $key, int $ttl): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->touch($key, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }
}
