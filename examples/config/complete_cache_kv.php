<?php

/**
 * 完整的 CacheKV 配置文件
 * 用于全面功能验证测试
 */

return array(
    // 全局缓存配置
    'cache' => array(
        'ttl' => 3600,                    // 默认TTL: 1小时
        'null_cache_ttl' => 300,          // 空值缓存TTL: 5分钟
        'enable_null_cache' => true,      // 启用空值缓存
        'ttl_random_range' => 300,        // TTL随机范围: ±5分钟
        'enable_stats' => true,           // 启用统计功能
        'stats_prefix' => 'cachekv:stats:', // 统计键前缀
        'stats_ttl' => 604800,            // 统计数据TTL: 7天
        'hot_key_auto_renewal' => true,   // 启用热点键自动续期
        'hot_key_threshold' => 100,       // 热点键阈值: 100次访问
        'hot_key_extend_ttl' => 7200,     // 热点键延长TTL: 2小时
        'hot_key_max_ttl' => 86400,       // 热点键最大TTL: 24小时
        'tag_prefix' => 'tag:'            // 标签前缀
    ),
    
    // 键管理器配置
    'key_manager' => array(
        'app_prefix' => 'myapp',          // 应用前缀
        'separator' => ':',               // 分隔符
        
        // 分组配置
        'groups' => array(
            
            // 用户相关数据
            'user' => array(
                'prefix' => 'user',
                'version' => 'v1',
                'description' => '用户相关数据缓存',
                'cache' => array(
                    'ttl' => 7200,           // 组级TTL: 2小时
                    'hot_key_threshold' => 50 // 组级热点阈值
                ),
                'keys' => array(
                    'profile' => array(
                        'template' => 'profile:{id}',
                        'description' => '用户资料',
                        'cache' => array(
                            'ttl' => 10800,         // 键级TTL: 3小时
                            'hot_key_threshold' => 30 // 键级热点阈值
                        )
                    ),
                    'settings' => array(
                        'template' => 'settings:{id}',
                        'description' => '用户设置',
                        'cache' => array(
                            'ttl' => 14400          // 键级TTL: 4小时
                        )
                    ),
                    'avatar' => array(
                        'template' => 'avatar:{id}:{size}',
                        'description' => '用户头像',
                        'cache' => array(
                            'ttl' => 86400          // 键级TTL: 24小时
                        )
                    ),
                    'session' => array(
                        'template' => 'session:{token}',
                        'description' => '会话标识'
                        // 注意：没有cache配置，仅用于键生成
                    ),
                    'lock' => array(
                        'template' => 'lock:{id}:{action}',
                        'description' => '分布式锁'
                        // 注意：没有cache配置，仅用于键生成
                    )
                )
            ),
            
            // 商品相关数据
            'goods' => array(
                'prefix' => 'goods',
                'version' => 'v1',
                'description' => '商品相关数据缓存',
                'cache' => array(
                    'ttl' => 1800,           // 组级TTL: 30分钟
                    'hot_key_threshold' => 80
                ),
                'keys' => array(
                    'info' => array(
                        'template' => 'info:{id}',
                        'description' => '商品基本信息',
                        'cache' => array(
                            'ttl' => 3600           // 键级TTL: 1小时
                        )
                    ),
                    'price' => array(
                        'template' => 'price:{id}',
                        'description' => '商品价格',
                        'cache' => array(
                            'ttl' => 900            // 键级TTL: 15分钟
                        )
                    ),
                    'stock' => array(
                        'template' => 'stock:{id}',
                        'description' => '商品库存',
                        'cache' => array(
                            'ttl' => 300            // 键级TTL: 5分钟
                        )
                    ),
                    'category' => array(
                        'template' => 'category:{id}',
                        'description' => '商品分类',
                        'cache' => array(
                            'ttl' => 7200           // 键级TTL: 2小时
                        )
                    )
                )
            ),
            
            // 文章相关数据
            'article' => array(
                'prefix' => 'article',
                'version' => 'v1',
                'description' => '文章相关数据缓存',
                'cache' => array(
                    'ttl' => 14400,          // 组级TTL: 4小时
                    'hot_key_threshold' => 60
                ),
                'keys' => array(
                    'content' => array(
                        'template' => 'content:{id}',
                        'description' => '文章内容',
                        'cache' => array(
                            'ttl' => 21600          // 键级TTL: 6小时
                        )
                    ),
                    'meta' => array(
                        'template' => 'meta:{id}',
                        'description' => '文章元数据',
                        'cache' => array(
                            'ttl' => 28800          // 键级TTL: 8小时
                        )
                    ),
                    'comments' => array(
                        'template' => 'comments:{id}:{page}',
                        'description' => '文章评论',
                        'cache' => array(
                            'ttl' => 1800           // 键级TTL: 30分钟
                        )
                    ),
                    'tags' => array(
                        'template' => 'tags:{id}',
                        'description' => '文章标签',
                        'cache' => array(
                            'ttl' => 7200           // 键级TTL: 2小时
                        )
                    ),
                    'view_count' => array(
                        'template' => 'view_count:{id}',
                        'description' => '文章浏览次数'
                        // 注意：没有cache配置，仅用于键生成
                    )
                )
            ),
            
            // API相关数据
            'api' => array(
                'prefix' => 'api',
                'version' => 'v2',
                'description' => 'API响应缓存',
                'cache' => array(
                    'ttl' => 600,            // 组级TTL: 10分钟
                    'hot_key_threshold' => 200
                ),
                'keys' => array(
                    'response' => array(
                        'template' => 'response:{endpoint}:{params_hash}',
                        'description' => 'API响应缓存',
                        'cache' => array(
                            'ttl' => 1200           // 键级TTL: 20分钟
                        )
                    ),
                    'rate_limit' => array(
                        'template' => 'rate_limit:{user_id}:{endpoint}',
                        'description' => 'API限流计数'
                        // 注意：没有cache配置，仅用于键生成
                    )
                )
            ),
            
            // 系统相关数据
            'system' => array(
                'prefix' => 'sys',
                'version' => 'v1',
                'description' => '系统级缓存',
                'cache' => array(
                    'ttl' => 86400,          // 组级TTL: 24小时
                    'hot_key_threshold' => 10
                ),
                'keys' => array(
                    'config' => array(
                        'template' => 'config:{key}',
                        'description' => '系统配置',
                        'cache' => array(
                            'ttl' => 172800         // 键级TTL: 48小时
                        )
                    ),
                    'stats' => array(
                        'template' => 'stats:{type}:{date}',
                        'description' => '系统统计',
                        'cache' => array(
                            'ttl' => 43200          // 键级TTL: 12小时
                        )
                    )
                )
            )
        )
    )
);
