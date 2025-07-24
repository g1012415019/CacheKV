<?php

declare(strict_types=1);

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\CacheDriver;

/**
 * DataCache 类是本缓存库的核心入口，提供统一的键值对缓存操作接口。
 * 它封装了“先从缓存读取，若无则从数据源获取并回填缓存”的常见模式，
 * 支持单键、多键操作、基于标签的缓存失效管理，以及基础的性能统计功能。
 *
 * 缓存的数据结构是键值对（key-value），其中 key 是字符串，value 可以是任意可序列化的 PHP 数据类型。
 */
class DataCache
{
    /**
     * @var CacheDriver 缓存驱动实例。
     * 负责与底层缓存存储（如 Redis, Array 等）进行实际的数据交互。
     */
    private CacheDriver $driver;

    /**
     * @var int $defaultTtl 默认缓存时间（秒）。
     * 当调用 set 或 get 方法时未指定 TTL (Time To Live) 时，将使用此默认值。
     */
    private int $defaultTtl;

    /**
     * 构造函数。
     *
     * @param CacheDriver $driver 缓存驱动实例，用于实际的数据存储和检索。
     * @param int $defaultTtl 默认缓存有效期（秒）。
     */
    public function __construct(CacheDriver $driver, int $defaultTtl = 3600)
    {
        $this->driver = $driver;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * 从缓存中获取指定键的值。
     * 如果缓存中不存在该键，且提供了回调函数，则会执行回调函数从数据源获取数据，
     * 并将获取到的数据（包括 null）回填到缓存中，以防止缓存穿透。
     * 如果缓存命中，且配置了滑动过期，则会自动延长缓存项的有效期。
     *
     * @param string $key 缓存项的唯一键名。
     * @param callable|null $callback 当缓存未命中时，用于从数据源获取数据的回调函数。
     *                                  该回调函数不接受任何参数，并应返回要缓存的值。
     * @param int|null $ttl 缓存有效期（秒）。如果为 null，则使用 DataCache 实例的默认 TTL。
     * @return mixed|null 缓存中存储的值，如果键不存在且未提供回调函数，则返回 null。
     */
    public function get(string $key, ?callable $callback = null, ?int $ttl = null)
    {
        $value = $this->driver->get($key);

        // 如果缓存命中，则执行滑动过期逻辑
        if ($value !== null) {
            $this->driver->touch($key, $this->defaultTtl);
            return $value;
        }

        // 缓存未命中，如果提供了回调函数，则从数据源获取并回填
        if ($callback !== null) {
            $fetchedValue = call_user_func($callback);
            // 即使 fetchedValue 为 null 也会被缓存，以防止缓存穿透
            $this->set($key, $fetchedValue, $ttl);
            return $fetchedValue;
        }

        // 缓存未命中且未提供回调函数
        return null;
    }

    /**
     * 将一个键值对存储到缓存中。
     *
     * @param string $key 缓存项的唯一键名。
     * @param mixed $value 要存储的数据。可以是任意可序列化的 PHP 数据类型。
     * @param int|null $ttl 缓存有效期（秒）。如果为 null，则使用 DataCache 实例的默认 TTL。
     * @return bool 存储操作是否成功。
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        return $this->driver->set($key, $value, $ttl ?? $this->defaultTtl);
    }

    /**
     * 将一个键值对存储到缓存中，并与一个或多个标签关联。
     * 标签系统允许对相关缓存项进行分组管理，方便批量失效。
     *
     * @param string $key 缓存项的唯一键名。
     * @param mixed $value 要存储的数据。可以是任意可序列化的 PHP 数据类型。
     * @param string|array $tags 单个标签名（字符串）或标签名数组。
     * @param int|null $ttl 缓存有效期（秒）。如果为 null，则使用 DataCache 实例的默认 TTL。
     * @return bool 存储操作是否成功。
     */
    public function setWithTag(string $key, $value, $tags, ?int $ttl = null): bool
    {
        $result = $this->driver->set($key, $value, $ttl ?? $this->defaultTtl);
        if ($result) {
            $this->driver->tag($key, (array) $tags);
        }
        return $result;
    }

    /**
     * 从缓存中批量获取多个键值对。
     * 对于缓存未命中的键，会调用提供的回调函数从数据源批量获取数据，
     * 并将获取到的数据回填到缓存中。
     *
     * @param array $keys 缓存项键名的数组。
     * @param callable $callback 当部分或全部缓存键未命中时，用于从数据源批量获取数据的回调函数。
     *                           回调函数接收一个数组参数，包含所有未命中的键，并应返回一个键值对数组，
     *                           其中键是原始的缓存键，值是对应的数据。
     * @param int|null $ttl 缓存有效期（秒）。如果为 null，则使用 DataCache 实例的默认 TTL。
     * @return array 包含所有请求键的键值对数组。如果某个键在缓存和数据源中都不存在，则该键不会出现在返回数组中。
     */
    public function getMultiple(array $keys, callable $callback, ?int $ttl = null): array
    {
        $cachedValues = $this->driver->getMultiple($keys);

        $missingKeys = [];
        $results = [];

        foreach ($keys as $originalKey) {
            if (isset($cachedValues[$originalKey])) {
                $results[$originalKey] = $cachedValues[$originalKey];
            } else {
                $missingKeys[] = $originalKey;
            }
        }

        if (!empty($missingKeys)) {
            $fetchedValues = call_user_func($callback, $missingKeys);
            if (!empty($fetchedValues)) {
                $this->driver->setMultiple($fetchedValues, $ttl ?? $this->defaultTtl);
            }
            foreach ($fetchedValues as $key => $value) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    /**
     * 从缓存中移除指定键的缓存项。
     *
     * @param string $key 要移除的缓存项的键名。
     * @return bool 移除操作是否成功。
     */
    public function forget(string $key): bool
    {
        return $this->driver->forget($key);
    }

    /**
     * 清除指定标签下的所有缓存项。
     * 此操作会遍历与该标签关联的所有缓存键，并将其从缓存中移除。
     *
     * @param string $tag 要清除的标签名。
     * @return bool 清除操作是否成功。
     */
    public function clearTag(string $tag): bool
    {
        return $this->driver->clearTag($tag);
    }

    /**
     * 检查缓存中是否存在指定键的缓存项。
     *
     * @param string $key 要检查的缓存项的键名。
     * @return bool 如果缓存中存在该键且未过期，则返回 true；否则返回 false。
     */
    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    /**
     * 获取缓存的统计信息，例如缓存命中次数和未命中次数。
     *
     * @return array 包含 'hits'（缓存命中次数）、'misses'（缓存未命中次数）和 'hit_rate'（缓存命中率）的关联数组。
     */
    public function getStats(): array
    {
        return $this->driver->getStats();
    }
}
