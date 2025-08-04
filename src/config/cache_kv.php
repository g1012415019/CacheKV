<?php

/**
 * CacheKV 简化配置文件
 * 
 * 配置层级：全局配置 -> 组配置 -> 键配置
 * 下级配置会覆盖上级配置
 */

return array(
    
    // ==================== 缓存全局配置 ====================
    'cache' => array(
        // 基础缓存配置
        'ttl' => 3600,                              // 默认缓存时间（秒）
        'null_cache_ttl' => 300,                    // 空值缓存时间（秒）
        'enable_null_cache' => true,                // 是否启用空值缓存
        'ttl_random_range' => 300,                  // TTL随机范围（秒）
        
        // 统计配置
        'enable_stats' => true,                     // 是否启用统计
        'stats_prefix' => 'cachekv:stats:',         // 统计数据Redis键前缀
        'stats_ttl' => 604800,                      // 统计数据TTL（秒，默认7天）
        
        // 热点键自动续期配置（简化版）
        'hot_key_auto_renewal' => true,             // 是否启用热点键自动续期
        'hot_key_threshold' => 100,                 // 热点键阈值（访问次数）
        'hot_key_extend_ttl' => 7200,               // 热点键延长TTL（秒，默认2小时）
        'hot_key_max_ttl' => 86400,                 // 热点键最大TTL（秒，默认24小时）
        
        // 标签配置
        'tag_prefix' => 'tag:',                     // 标签前缀
    ),
    
    // ==================== KeyManager 配置 ====================
    'key_manager' => array(
        // KeyManager 基础配置
        'app_prefix' => 'app',                      // 应用前缀
        'separator' => ':',                         // 分隔符
        
        // 组配置
        'groups' => array(
            
            // 用户相关缓存组
            'user' => array(
                'prefix' => 'user',
                'version' => 'v1',
                'description' => '用户相关数据缓存',
                
                // 组级缓存配置（覆盖全局配置）
                'cache' => array(
                    'ttl' => 7200,                  // 用户数据缓存2小时
                    'hot_key_threshold' => 50,      // 用户数据热点阈值更低
                    'hot_key_max_ttl' => 172800,    // 用户热点数据最大2天
                ),
                
                // 键定义
                'keys' => array(
                    // K-V类型的键（会应用缓存配置）
                    'kv' => array(
                        'profile' => array(
                            'template' => 'profile:{id}',
                            'description' => '用户基础资料',
                            'cache' => array(
                                'ttl' => 10800,     // 用户资料缓存3小时
                                'hot_key_threshold' => 30,
                            )
                        ),
                        'settings' => array(
                            'template' => 'settings:{id}',
                            'description' => '用户设置信息',
                            'cache' => array(
                                'ttl' => 14400,     // 用户设置缓存4小时
                            )
                        ),
                        'avatar' => array(
                            'template' => 'avatar:{id}:{size}',
                            'description' => '用户头像URL',
                            'cache' => array(
                                'ttl' => 86400,     // 头像URL缓存1天
                                'hot_key_auto_renewal' => false, // 头像不需要热点续期
                            )
                        ),
                    ),
                    
                    // 其他类型的键（不应用缓存配置，仅用于键生成）
                    'other' => array(
                        'session' => array(
                            'template' => 'session:{token}',
                            'description' => '用户会话标识',
                        ),
                        'lock' => array(
                            'template' => 'lock:{id}:{action}',
                            'description' => '用户操作锁',
                        ),
                        'counter' => array(
                            'template' => 'counter:{id}:{type}',
                            'description' => '用户计数器',
                        ),
                    ),
                ),
            ),
        ),
    ),
);
