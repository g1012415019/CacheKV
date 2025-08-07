<?php

/**
 * 用户模块缓存配置
 * 
 * 文件名即为分组名，此文件对应 'user' 分组
 */
return array(
    'prefix' => 'user',
    'version' => 'v1',
    'description' => '用户相关数据缓存',
    
    // 组级缓存配置（覆盖全局配置）
    'cache' => array(
        'ttl' => 7200,                      // 用户数据缓存2小时
        'hot_key_threshold' => 50,          // 用户数据热点阈值更低
    ),
    
    // 键定义
    'keys' => array(
        'kv' => array(
            'profile' => array(
                'template' => 'profile:{id}',
                'description' => '用户基础资料',
                // 键级缓存配置（最高优先级）
                'cache' => array(
                    'ttl' => 10800,             // 用户资料缓存3小时
                    'hot_key_threshold' => 30,
                )
            ),
            'settings' => array(
                'template' => 'settings:{id}',
                'description' => '用户设置信息',
                'cache' => array(
                    'ttl' => 3600,              // 用户设置缓存1小时
                )
            ),
            'avatar' => array(
                'template' => 'avatar:{id}:{size}',
                'description' => '用户头像',
                'cache' => array(
                    'ttl' => 14400,             // 头像缓存4小时
                )
            ),
        ),
        'other' => array(
            'session' => array(
                'template' => 'session:{token}',
                'description' => '用户会话标识',
            ),
        ),
    ),
);
