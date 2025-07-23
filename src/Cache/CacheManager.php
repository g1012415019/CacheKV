<?php

declare(strict_types=1);

namespace Asfop\QueryCache\Cache;

use Asfop\QueryCache\Cache\Drivers\ArrayDriver;
use InvalidArgumentException;

/**
 * 缓存管理器。
 * 负责管理和解析不同的缓存驱动实例，提供统一的缓存访问入口。
 */
class CacheManager
{
    /**
     * @var array 存储已解析的缓存驱动实例。
     */
    private static $drivers = [];

    /**
     * @var array 存储自定义的缓存驱动创建器（闭包）。
     */
    private static $customCreators = [];

    /**
     * 解析并获取一个缓存驱动实例。
     * 如果驱动实例已存在，则直接返回；否则通过createDriver方法创建。
     *
     * @param string|null $name 驱动名称，如果为null则使用默认驱动。
     * @return CacheDriver 缓存驱动实例。
     * @throws InvalidArgumentException 如果指定的驱动不存在。
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
     * 创建一个缓存驱动实例。
     * 优先使用自定义创建器，否则根据内置驱动类型创建。
     *
     * @param string $name 驱动名称。
     * @return CacheDriver 缓存驱动实例。
     * @throws InvalidArgumentException 如果指定的驱动不存在。
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
            default:
                throw new InvalidArgumentException("Driver [{$name}] is not supported.");
        }
    }

    /**
     * 注册一个自定义的缓存驱动创建器。
     * 允许用户扩展支持更多类型的缓存驱动。
     *
     * @param string $name 驱动名称。
     * @param callable $creator 创建驱动实例的闭包。
     */
    public static function extend(string $name, callable $creator)
    {
        self::$customCreators[$name] = $creator;
    }

    /**
     * 获取默认的缓存驱动名称。
     * 在实际应用中，这通常会从配置文件中读取。
     *
     * @return string 默认驱动名称。
     */
    public static function getDefaultDriver(): string
    {
        return 'array';
    }

    /**
     * 清除所有已解析的缓存驱动实例和自定义创建器。
     * 主要用于测试环境，确保测试之间的隔离性。
     */
    public static function clearResolvedInstances(): void
    {
        self::$drivers = [];
        self::$customCreators = [];
    }
}