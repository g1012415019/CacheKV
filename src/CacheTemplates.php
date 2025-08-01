<?php

namespace Asfop\CacheKV;

/**
 * 缓存模板常量示例
 * 
 * 这个类展示了如何定义缓存模板常量。
 * 在实际项目中，建议创建自己的模板常量类。
 */
class CacheTemplates
{
    // 用户相关模板
    const USER = 'user';
    const USER_PROFILE = 'user_profile';
    const USER_SETTINGS = 'user_settings';
    
    // 内容相关模板
    const POST = 'post';
    const CATEGORY = 'category';
    
    // 会话相关模板
    const SESSION = 'session';
    
    // API 缓存模板
    const API_RESPONSE = 'api_response';
    
    // 计算结果缓存模板
    const CALCULATION = 'calculation';
}
