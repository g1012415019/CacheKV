<?php

namespace Asfop\CacheKV;

/**
 * 缓存模板常量类
 * 统一管理所有缓存模板名称，避免硬编码
 */
class CacheTemplates
{
    // 用户相关模板
    const USER_PROFILE = 'user_profile';
    const USER_SETTINGS = 'user_settings';
    const USER_CART = 'user_cart';
    const USER_WISHLIST = 'user_wishlist';
    const USER_ORDERS = 'user_orders';
    
    // 产品相关模板
    const PRODUCT_DETAIL = 'product_detail';
    const PRODUCT_REVIEWS = 'product_reviews';
    const PRODUCT_STOCK = 'product_stock';
    const PRODUCT_IMAGES = 'product_images';
    const PRODUCT_RELATED = 'product_related';
    
    // 订单相关模板
    const ORDER_DETAIL = 'order_detail';
    const ORDER_ITEMS = 'order_items';
    const ORDER_STATUS = 'order_status';
    const ORDER_TRACKING = 'order_tracking';
    
    // 分类相关模板
    const CATEGORY_PRODUCTS = 'category_products';
    const CATEGORY_INFO = 'category_info';
    const CATEGORY_TREE = 'category_tree';
    
    // 搜索相关模板
    const SEARCH_RESULTS = 'search_results';
    const SEARCH_SUGGESTIONS = 'search_suggestions';
    const SEARCH_FILTERS = 'search_filters';
    
    // 系统相关模板
    const SYSTEM_CONFIG = 'system_config';
    const SYSTEM_STATS = 'system_stats';
    const SYSTEM_HEALTH = 'system_health';
    
    // API 相关模板
    const API_CACHE = 'api_cache';
    const API_RATE_LIMIT = 'api_rate_limit';
    const API_TOKEN = 'api_token';
    
    // 会话相关模板
    const SESSION_DATA = 'session_data';
    const SESSION_CART = 'session_cart';
    const SESSION_TEMP = 'session_temp';
    
    // 通知相关模板
    const NOTIFICATION_USER = 'notification_user';
    const NOTIFICATION_SYSTEM = 'notification_system';
    const NOTIFICATION_EMAIL = 'notification_email';
    
    /**
     * 获取所有模板常量
     * 
     * @return array
     */
    public static function getAllTemplates()
    {
        $reflection = new \ReflectionClass(__CLASS__);
        return $reflection->getConstants();
    }
    
    /**
     * 检查模板是否存在
     * 
     * @param string $template
     * @return bool
     */
    public static function exists($template)
    {
        return in_array($template, self::getAllTemplates());
    }
    
    /**
     * 获取模板分组
     * 
     * @return array
     */
    public static function getTemplateGroups()
    {
        return [
            'user' => [
                self::USER_PROFILE,
                self::USER_SETTINGS,
                self::USER_CART,
                self::USER_WISHLIST,
                self::USER_ORDERS,
            ],
            'product' => [
                self::PRODUCT_DETAIL,
                self::PRODUCT_REVIEWS,
                self::PRODUCT_STOCK,
                self::PRODUCT_IMAGES,
                self::PRODUCT_RELATED,
            ],
            'order' => [
                self::ORDER_DETAIL,
                self::ORDER_ITEMS,
                self::ORDER_STATUS,
                self::ORDER_TRACKING,
            ],
            'category' => [
                self::CATEGORY_PRODUCTS,
                self::CATEGORY_INFO,
                self::CATEGORY_TREE,
            ],
            'search' => [
                self::SEARCH_RESULTS,
                self::SEARCH_SUGGESTIONS,
                self::SEARCH_FILTERS,
            ],
            'system' => [
                self::SYSTEM_CONFIG,
                self::SYSTEM_STATS,
                self::SYSTEM_HEALTH,
            ],
            'api' => [
                self::API_CACHE,
                self::API_RATE_LIMIT,
                self::API_TOKEN,
            ],
            'session' => [
                self::SESSION_DATA,
                self::SESSION_CART,
                self::SESSION_TEMP,
            ],
            'notification' => [
                self::NOTIFICATION_USER,
                self::NOTIFICATION_SYSTEM,
                self::NOTIFICATION_EMAIL,
            ],
        ];
    }
}
