<?php

/**
 * 商品模块缓存配置
 * 
 * 文件名即为分组名，此文件对应 'goods' 分组
 */
return array(
    'prefix' => 'goods',
    'version' => 'v1',
    'description' => '商品相关数据缓存',
    
    // 组级缓存配置
    'cache' => array(
        'ttl' => 14400,                     // 商品数据缓存4小时
        'hot_key_threshold' => 200,         // 商品数据热点阈值更高
        'hot_key_extend_ttl' => 28800,      // 热点商品延长到8小时
    ),
    
    // 键定义
    'keys' => array(
        'kv' => array(
            'info' => array(
                'template' => 'info:{id}',
                'description' => '商品基础信息',
                'cache' => array(
                    'ttl' => 21600,             // 商品信息缓存6小时
                )
            ),
            'price' => array(
                'template' => 'price:{id}',
                'description' => '商品价格',
                'cache' => array(
                    'ttl' => 1800,              // 价格缓存30分钟（更新频繁）
                    'hot_key_threshold' => 100,
                )
            ),
            'stock' => array(
                'template' => 'stock:{id}',
                'description' => '商品库存',
                'cache' => array(
                    'ttl' => 300,               // 库存缓存5分钟（实时性要求高）
                    'hot_key_threshold' => 50,
                )
            ),
            'category' => array(
                'template' => 'category:{id}',
                'description' => '商品分类信息',
                'cache' => array(
                    'ttl' => 43200,             // 分类信息缓存12小时
                )
            ),
        ),
    ),
);
