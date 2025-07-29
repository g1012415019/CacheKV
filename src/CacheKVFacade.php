<?php

declare(strict_types=1);

namespace Asfop\CacheKV;

/**
 * DataCacheFacade 提供了一个静态门面，允许通过静态方法便捷地访问 DataCache 的功能。
 * 这在不希望通过依赖注入方式获取 DataCache 实例的场景下非常有用。
 * 缓存的数据结构是键值对（key-value），其中 key 是字符串，value 可以是任意可序列化的 PHP 数据类型。
 */
class CacheKVFacade
{
    /**
     * @var DataCache|null 存储 DataCache 实例的静态属性。
     * 这是一个单例模式的实现，确保在整个应用程序生命周期中只有一个 DataCache 实例被门面使用。
     */
    protected static ?CacheKV $instance = null;

    /**
     * 设置 DataCache 实例。
     * 这是门面模式的关键一步，将一个 DataCache 实例绑定到静态门面，
     * 使得后续可以通过静态方法调用该实例的方法。
     *
     * @param DataCache $instance 要设置的 DataCache 实例。
     */
    public static function setInstance(CacheKV $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * 获取当前绑定的 DataCache 实例。
     * 如果在调用此方法之前没有通过 `setInstance` 方法设置实例，则会抛出运行时异常。
     *
     * @return DataCache 当前使用的 DataCache 实例。
     */
    public static function getInstance(): ?CacheKV
    {
        return self::$instance;
    }

    /**
     * 通过门面从缓存中获取指定键的值。
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
    public static function get(string $key, ?callable $callback = null, ?int $ttl = null)
    {
        return self::getInstance()->get($key, $callback, $ttl);
    }

    /**
     * 通过门面将一个键值对存储到缓存中。
     *
     * @param string $key 缓存项的唯一键名。
     * @param mixed $value 要存储的数据。可以是任意可序列化的 PHP 数据类型。
     * @param int|null $ttl 缓存有效期（秒）。如果为 null，则使用 DataCache 实例的默认 TTL。
     * @return bool 存储操作是否成功。
     */
    public static function set(string $key, $value, ?int $ttl = null): bool
    {
        return self::getInstance()->set($key, $value, $ttl);
    }

    /**
     * 通过门面将一个键值对存储到缓存中，并与一个或多个标签关联。
     * 标签系统允许对相关缓存项进行分组管理，方便批量失效。
     *
     * @param string $key 缓存项的唯一键名。
     * @param mixed $value 要存储的数据。可以是任意可序列化的 PHP 数据类型。
     * @param string|array $tags 单个标签名（字符串）或标签名数组。
     * @param int|null $ttl 缓存有效期（秒）。如果为 null，则使用 DataCache 实例的默认 TTL。
     * @return bool 存储操作是否成功。
     */
    public static function setWithTag(string $key, $value, $tags, ?int $ttl = null): bool
    {
        return self::getInstance()->setWithTag($key, $value, $tags, $ttl);
    }

    /**
     * 通过门面从缓存中批量获取多个键值对。
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
    public static function getMultiple(array $keys, callable $callback, ?int $ttl = null): array
    {
        return self::getInstance()->getMultiple($keys, $callback, $ttl);
    }

    /**
     * 通过门面从缓存中移除指定键的缓存项。
     *
     * @param string $key 要移除的缓存项的键名。
     * @return bool 移除操作是否成功。
     */
    public static function forget(string $key): bool
    {
        return self::getInstance()->forget($key);
    }

    /**
     * 通过门面清除指定标签下的所有缓存项。
     * 此操作会遍历与该标签关联的所有缓存键，并将其从缓存中移除。
     *
     * @param string $tag 要清除的标签名。
     * @return bool 清除操作是否成功。
     */
    public static function clearTag(string $tag): bool
    {
        return self::getInstance()->clearTag($tag);
    }

    /**
     * 通过门面检查缓存中是否存在指定键的缓存项。
     *
     * @param string $key 要检查的缓存项的键名。
     * @return bool 如果缓存中存在该键且未过期，则返回 true；否则返回 false。
     */
    public static function has(string $key): bool
    {
        return self::getInstance()->has($key);
    }

    /**
     * 通过门面获取缓存的统计信息，例如缓存命中次数和未命中次数。
     *
     * @return array 包含 'hits'（缓存命中次数）、'misses'（缓存未命中次数）和 'hit_rate'（缓存命中率）的关联数组。
     */
    public static function getStats(): array
    {
        return self::getInstance()->getStats();
    }
}
