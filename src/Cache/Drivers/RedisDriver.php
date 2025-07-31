<?php

namespace Asfop\CacheKV\Cache\Drivers;

use Asfop\CacheKV\Cache\CacheDriver;

/**
 * RedisDriver 是一个基于 Redis 数据库实现的缓存驱动。
 * 它实现了 CacheDriver 接口，利用 Redis 的高性能特性进行键值对缓存操作。
 * 缓存的数据结构是键值对（key-value），其中 key 是字符串，value 是可序列化的 PHP 数据类型。
 */
class RedisDriver implements CacheDriver
{
    protected static $redisFactory;
    protected $redis;
    protected $hits = 0;
    protected $misses = 0;

    public function __construct()
    {
        if (static::$redisFactory) {
            $this->redis = call_user_func(static::$redisFactory);
        } else {
            throw new \Exception('Redis factory not configured.');
        }
    }

    public static function setRedisFactory(callable $factory)
    {
        static::$redisFactory = $factory;
    }

    /**
     * 从 Redis 缓存中获取指定键的值。
     * 数据在存储时经过序列化，因此获取后需要反序列化。
     * 此方法会更新缓存命中/未命中统计。
     *
     * @param string $key 缓存项的唯一键名。
     * @return mixed|null 缓存中存储的值，如果键不存在或已过期，则返回 null。
     */
    public function get($key)
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            $this->misses++;
            return null;
        }

        $this->hits++;
        return unserialize($value);
    }

    /**
     * 从 Redis 缓存中批量获取多个键的值。
     * 数据在存储时经过序列化，因此获取后需要反序列化。
     * 此方法会更新缓存命中/未命中统计。
     *
     * @param array $keys 缓存项键名的数组。
     * @return array 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据。
     *               如果某个键在缓存中不存在或已过期，则该键不会出现在返回的数组中。
     */
    public function getMultiple(array $keys)
    {
        $values = $this->redis->mget($keys);

        $results = [];
        foreach ($keys as $index => $key) {
            if ($values[$index] !== false) {
                $results[$key] = unserialize($values[$index]);
                $this->hits++;
            } else {
                $this->misses++;
            }
        }
        return $results;
    }

    /**
     * 将一个键值对存储到 Redis 缓存中。
     * 数据会被序列化后存储，并设置过期时间。
     *
     * @param string $key 缓存项的唯一键名。
     * @param mixed $value 要存储的数据。可以是任意可序列化的 PHP 数据类型。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储操作是否成功。
     */
    public function set($key, $value, $ttl)
    {
        return $this->redis->setex($key, $ttl, serialize($value));
    }

    /**
     * 批量将多个键值对存储到 Redis 缓存中。
     * 此方法使用 Redis 管道（pipeline）来提高批量操作的效率，减少网络往返时间。
     * 数据会被序列化后存储，并为每个键设置过期时间。
     *
     * @param array $values 一个关联数组，其中键是缓存项的键名，值是对应的缓存数据。
     *                      值可以是任意可序列化的 PHP 数据类型。
     * @param int $ttl 缓存有效期（秒）。
     * @return bool 存储操作是否成功。由于使用了管道，通常总是返回 true，除非 Redis 连接出现问题。
     */
    public function setMultiple(array $values, $ttl)
    {
        $pipeline = $this->redis->pipeline();

        foreach ($values as $key => $value) {
            $pipeline->setex($key, $ttl, serialize($value));
        }

        $results = $pipeline->exec();

        // Check if all operations were successful
        foreach ($results as $result) {
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 从 Redis 缓存中移除指定键的缓存项。
     * 此方法不仅会删除缓存键本身，还会清除与该键关联的所有标签引用，确保数据一致性。
     *
     * @param string $key 要移除的缓存项的唯一键名。
     * @return bool 移除操作是否成功。
     */
    public function forget($key)
    {
        // 移除键本身
        $deleted = $this->redis->del($key) > 0;

        // 移除与该键关联的标签引用
        $tagKeys = $this->redis->smembers('tags:' . $key);
        if (!empty($tagKeys)) {
            foreach ($tagKeys as $tag) {
                $this->redis->srem('tag_keys:' . $tag, $key);
            }
            $this->redis->del('tags:' . $key); // 清除键上的标签列表
        }

        return $deleted;
    }

    /**
     * 检查 Redis 缓存中是否存在指定键的缓存项。
     * 此方法利用 Redis 的 `EXISTS` 命令来判断键是否存在。
     *
     * @param string $key 缓存项的唯一键名。
     * @return bool 如果缓存中存在该键，则返回 true；否则返回 false。
     */
    public function has($key)
    {
        return $this->redis->exists($key) === 1;
    }

    /**
     * 将一个缓存项与一个或多个标签关联到 Redis 中。
     * 标签通过 Redis 的 Set 数据结构实现，每个标签对应一个 Set，其中存储了关联的缓存键。
     * 同时，每个缓存键也会记录其所属的标签，方便在删除缓存键时同步更新标签信息。
     *
     * @param string $key 缓存项的唯一键名。
     * @param array $tags 包含一个或多个标签名的数组。
     * @return bool 关联操作是否成功。
     */
    public function tag($key, array $tags)
    {
        $pipeline = $this->redis->pipeline();

        foreach ($tags as $tag) {
            $formattedTagKey = 'tag_keys:' . $tag;
            $pipeline->sadd($formattedTagKey, $key);
            // 记录键所属的标签，方便forget时清理
            $pipeline->sadd('tags:' . $key, $tag);
        }
        $pipeline->exec();
        return true;
    }

    /**
     * 清除指定标签下的所有缓存项。
     * 此方法会首先获取与给定标签关联的所有缓存键，然后通过 Redis 管道批量删除这些缓存键。
     * 同时，也会清除标签本身在 Redis 中存储的键列表，以及缓存键上记录的标签引用，确保数据一致性。
     *
     * @param string $tag 要清除的标签名。
     * @return bool 清除操作是否成功。如果标签不存在或没有关联的键，则返回 false；否则返回 true。
     */
    public function clearTag($tag)
    {
        $formattedTagKey = 'tag_keys:' . $tag;
        $keysToClear = $this->redis->smembers($formattedTagKey);

        if (empty($keysToClear)) {
            return false;
        }

        $pipeline = $this->redis->pipeline();
        foreach ($keysToClear as $key) {
            $pipeline->del($key); // 删除实际的缓存项
            // 移除键上的标签引用
            $pipeline->srem('tags:' . $key, $tag);
        }
        $pipeline->del($formattedTagKey); // 删除标签本身存储的键列表
        $pipeline->exec();

        return true;
    }

    /**
     * 获取 Redis 缓存的统计信息，包括命中次数、未命中次数和命中率。
     * 注意：此统计信息是基于当前驱动实例内部维护的计数器，而不是 Redis 服务器的全局统计。
     * 对于更详细的 Redis 统计，可以考虑使用 Redis 的 INFO 命令。
     *
     * @return array 包含 'hits'（缓存命中次数）、'misses'（缓存未命中次数）和 'hit_rate'（缓存命中率）的关联数组。
     */
    public function getStats()
    {
        $total = $this->hits + $this->misses;
        $hitRate = $total > 0 ? ($this->hits / $total) * 100 : 0;

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => round($hitRate, 2)
        ];
    }

    /**
     * 更新 Redis 缓存中指定键的缓存项的过期时间。
     * 此方法利用 Redis 的 `EXPIRE` 命令来设置键的过期时间。
     * 主要用于实现滑动过期，当缓存项被访问时，延长其有效期。
     *
     * @param string $key 缓存项的唯一键名。
     * @param int $ttl 新的缓存有效期（秒）。
     * @return bool 更新操作是否成功。如果键不存在，则返回 false；否则返回 true。
     */
    public function touch($key, $ttl)
    {
        return $this->redis->expire($key, $ttl);
    }
}
