<?php

declare(strict_types=1);

namespace Asfop\CacheKV\Cache;

use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use InvalidArgumentException;

/**
 * CacheManager 负责管理和解析不同的缓存驱动实例。
 * 它提供了一个统一的接口来获取和注册缓存驱动，使得应用程序可以灵活地切换和扩展缓存后端。
 */
class CacheManager
{
    /**
     * @var array 存储已解析的缓存驱动实例。
     * 键是驱动名称，值是对应的 CacheDriver 实例。
     */
    private static $drivers = [];

    /**
     * @var array 存储自定义的缓存驱动创建器（闭包）。
     * 键是驱动名称，值是一个用于创建 CacheDriver 实例的匿名函数。
     */
    private static $customCreators = [];

    /**
     * 解析并获取一个缓存驱动实例。
     * 如果指定的驱动实例已经存在于管理器中，则直接返回该实例，避免重复创建。
     * 如果实例不存在，则会通过 `createDriver` 方法创建新的实例并存储。
     *
     * @param string|null $name 驱动的唯一名称。如果为 null，将使用 `getDefaultDriver()` 方法返回的默认驱动名称。
     * @return CacheDriver 对应名称的缓存驱动实例。
     * @throws InvalidArgumentException 如果指定的驱动名称无效或未被支持。
     */
    public static function resolve(?string $name = null): CacheDriver
    {
        $name = $name ?: self::getDefaultDriver();

        if (!isset(self::$drivers[$name])) {
            self::$drivers[$name] = self::createDriver($name);
        }

        return self::$drivers[$name];
    }

    /**
     * 根据给定的驱动名称创建一个新的缓存驱动实例。
     * 优先检查是否存在自定义的创建器（通过 `extend` 方法注册），如果存在则调用自定义创建器。
     * 否则，根据内置的驱动类型（如 'array'）创建相应的驱动实例。
     *
     * @param string $name 要创建的驱动的名称。
     * @return CacheDriver 新创建的缓存驱动实例。
     * @throws InvalidArgumentException 如果指定的驱动名称没有对应的创建器或内置实现。
     */
    private static function createDriver(string $name): CacheDriver
    {
        // 如果存在自定义创建器，则调用自定义创建器
        if (isset(self::$customCreators[$name])) {
            return call_user_func(self::$customCreators[$name]);
        }

        // 根据内置驱动类型创建
        switch ($name) {
            case 'array':
                return new ArrayDriver();
            case 'redis':
                return new RedisDriver();
            case 'multi_level':
                throw new InvalidArgumentException("Multi-level driver must be configured via CacheManager::extend().");
            default:
                throw new InvalidArgumentException("Driver [{$name}] is not supported.");
        }
    }

    /**
     * 注册一个自定义的缓存驱动创建器。
     * 这允许应用程序扩展 CacheManager，支持除了内置驱动之外的更多类型的缓存后端。
     * 注册的创建器是一个闭包，当需要该驱动时，CacheManager 会调用此闭包来实例化驱动。
     *
     * @param string $name 驱动的唯一名称，例如 'redis_cluster' 或 'memcached'。
     * @param callable $creator 一个匿名函数（闭包），该函数不接受参数，并应返回一个 CacheDriver 实例。
     */
    public static function extend(string $name, callable $creator)
    {
        self::$customCreators[$name] = $creator;
    }

    /**
     * 获取默认的缓存驱动名称。
     * 在实际应用中，这个方法通常会从应用程序的配置中读取默认的缓存驱动设置，
     * 以便在没有明确指定驱动时提供一个回退选项。
     *
     * @return string 默认缓存驱动的名称，例如 'array' 或 'redis'。
     */
    public static function getDefaultDriver(): string
    {
        return 'redis';
    }

    /**
     * 清除所有已解析的缓存驱动实例和自定义创建器。
     * 这个方法主要用于测试环境，以确保每次测试运行时都能够获得一个干净的、隔离的 CacheManager 状态，
     * 避免不同测试用例之间的数据互相影响。
     */
    public static function clearResolvedInstances(): void
    {
        self::$drivers = [];
        self::$customCreators = [];
    }
}