<?php

/**
 * 文章模块缓存配置
 * 
 * 文件名即为分组名，此文件对应 'article' 分组
 */
return array(
    'prefix' => 'article',
    'version' => 'v1',
    'description' => '文章内容缓存',
    
    // 组级缓存配置
    'cache' => array(
        'ttl' => 28800,                     // 文章数据缓存8小时
        'hot_key_threshold' => 150,         // 文章热点阈值
        'hot_key_extend_ttl' => 86400,      // 热点文章延长到24小时
    ),
    
    // 键定义
    'keys' => array(
        'kv' => array(
            'content' => array(
                'template' => 'content:{id}',
                'description' => '文章内容',
                'cache' => array(
                    'ttl' => 43200,             // 文章内容缓存12小时
                )
            ),
            'meta' => array(
                'template' => 'meta:{id}',
                'description' => '文章元信息',
                'cache' => array(
                    'ttl' => 21600,             // 文章元信息缓存6小时
                )
            ),
            'comments' => array(
                'template' => 'comments:{id}:{page}',
                'description' => '文章评论',
                'cache' => array(
                    'ttl' => 3600,              // 评论缓存1小时
                    'hot_key_threshold' => 50,
                )
            ),
            'tags' => array(
                'template' => 'tags:{id}',
                'description' => '文章标签',
                'cache' => array(
                    'ttl' => 14400,             // 标签缓存4小时
                )
            ),
        ),
        'other' => array(
            'view_count' => array(
                'template' => 'view_count:{id}',
                'description' => '文章浏览计数',
            ),
        ),
    ),
);
