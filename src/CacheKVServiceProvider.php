<?php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\KeyManager;

class CacheKVServiceProvider
{
    /**
     * 注册 CacheKV 服务并设置门面
     * 
     * @param array|null $config 可选的配置覆盖
     * @return CacheKV
     * @throws \Exception 当配置无效时抛出异常
     */
    public static function register($config = null)
    {
        // 清除旧实例
        CacheKVFacade::clearInstance();
        
        // 如果没有传入配置，使用默认配置
        if ($config === null) {
            $config = self::getDefaultConfig();
        }

        // 使用工厂模式设置配置并创建实例
        CacheKVFactory::setDefaultConfig($config);
        $cacheKV = CacheKVFactory::create();
        
        // 注册到门面
        CacheKVFacade::setInstance($cacheKV);
        
        return $cacheKV;
    }

    /**
     * 获取默认配置
     * 
     * @return array
     */
    public static function getDefaultConfig()
    {
        return require __DIR__ . '/Config/cachekv.php';
    }

    /**
     * 使用自定义配置注册服务
     * 
     * @param array $config 完整的配置数组
     * @return CacheKV
     */
    public static function registerWithConfig(array $config)
    {
        // 验证配置格式
        self::validateConfig($config);
        
        return self::register($config);
    }

    /**
     * 快速注册（使用 ArrayDriver）
     * 
     * @param string $appPrefix 应用前缀
     * @param string $envPrefix 环境前缀
     * @param array $templates 模板配置
     * @param int $ttl 过期时间
     * @return CacheKV
     */
    public static function quickRegister($appPrefix = 'app', $envPrefix = 'dev', $templates = [], $ttl = 3600)
    {
        $config = [
            'default' => 'array',
            'stores' => [
                'array' => [
                    'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
                    'ttl' => $ttl
                ]
            ],
            'key_manager' => [
                'app_prefix' => $appPrefix,
                'env_prefix' => $envPrefix,
                'version' => 'v1',
                'templates' => $templates
            ]
        ];

        return self::register($config);
    }

    /**
     * 验证配置格式
     * 
     * @param array $config
     * @throws \Exception
     */
    private static function validateConfig(array $config)
    {
        if (!isset($config['default'])) {
            throw new \Exception('配置中缺少 default 字段');
        }

        if (!isset($config['stores']) || !is_array($config['stores'])) {
            throw new \Exception('配置中缺少 stores 字段或格式不正确');
        }

        $defaultStore = $config['default'];
        if (!isset($config['stores'][$defaultStore])) {
            throw new \Exception("默认存储 '{$defaultStore}' 在 stores 中未找到");
        }

        $storeConfig = $config['stores'][$defaultStore];
        if (!isset($storeConfig['driver'])) {
            throw new \Exception("存储 '{$defaultStore}' 缺少 driver 配置");
        }
    }

    /**
     * 重置服务（主要用于测试）
     */
    public static function reset()
    {
        CacheKVFacade::clearInstance();
    }
}
