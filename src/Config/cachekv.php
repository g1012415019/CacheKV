<?php
return [
    'default' => 'redis', // 默认使用 Redis 驱动
    'stores' => [
        'redis' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\RedisDriver::class, // 通用 Redis 驱动
        ],
    ],
];