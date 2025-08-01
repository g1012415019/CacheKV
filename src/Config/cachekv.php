<?php

return [
    'default' => 'array',
    
    'stores' => [
        'array' => [
            'driver' => new \Asfop\CacheKV\Cache\Drivers\ArrayDriver(),
            'ttl' => 3600
        ],
        
        'redis' => [
            // Redis 驱动需要在运行时注入 Redis 实例
            // 'driver' => new \Asfop\CacheKV\Cache\Drivers\RedisDriver($redisInstance),
            'ttl' => 3600
        ]
    ],
    
    'key_manager' => [
        'app_prefix' => 'cachekv',
        'env_prefix' => 'default',
        'version' => 'v1',
        'separator' => ':',
        'templates' => [
            'user' => 'user:{id}',
            'product' => 'product:{id}',
            'order' => 'order:{id}',
            'session' => 'session:{id}',
        ]
    ]
];
