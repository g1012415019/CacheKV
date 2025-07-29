<?php
return [
    'default' => 'redis', // 默认使用 ArrayDriver
    'stores' => [
        'redis' => [
            'driver' => \Asfop\CacheKV\Cache\Drivers\RedisDriver::class, // 通用 Redis 驱动
        ],
    ],
];