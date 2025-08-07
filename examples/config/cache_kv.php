<?php

/**
 * CacheKV 主配置文件
 * 
 * 分组配置会自动从 kvconf/ 目录加载
 * 每个分组一个文件：kvconf/user.php, kvconf/goods.php, kvconf/article.php
 */
return array(
    // ==================== 全局缓存配置 ====================
    'cache' => array(
        // 基础配置
        'ttl' => 3600,                          // 默认缓存时间（秒）
        'null_cache_ttl' => 300,                // 空值缓存时间（秒）
        'enable_null_cache' => true,            // 是否启用空值缓存
        'ttl_random_range' => 300,              // TTL随机范围（秒）
        
        // 统计配置
        'enable_stats' => true,                 // 是否启用统计
        'stats_prefix' => 'cachekv:stats:',     // 统计数据Redis键前缀
        'stats_ttl' => 604800,                  // 统计数据TTL（7天）
        
        // 热点键自动续期配置
        'hot_key_auto_renewal' => true,         // 是否启用热点键自动续期
        'hot_key_threshold' => 100,             // 热点键阈值（访问次数）
        'hot_key_extend_ttl' => 7200,           // 热点键延长TTL（秒）
        'hot_key_max_ttl' => 86400,             // 热点键最大TTL（秒）
        
        // 标签配置
        'tag_prefix' => 'tag:',                 // 标签前缀
    ),
    
    // ==================== KeyManager 配置 ====================
    'key_manager' => array(
        'app_prefix' => 'myapp',                // 应用前缀
        'separator' => ':',                     // 分隔符
        
        // 分组配置会自动从 kvconf/ 目录加载
        // 不需要在这里手动配置 groups
        'groups' => array(
            // 这里可以保留一些基础分组配置，会与 kvconf/ 目录中的配置合并
            // 如果 kvconf/ 中有同名分组，会覆盖这里的配置
        ),
    ),
);
