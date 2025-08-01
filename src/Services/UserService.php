<?php

namespace Asfop\CacheKV\Services;

use Asfop\CacheKV\CacheTemplates;

/**
 * 用户服务类
 * 封装所有用户相关的缓存操作
 */
class UserService extends CacheServiceBase
{
    /**
     * 获取用户档案
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserProfile($userId)
    {
        return $this->getCache(CacheTemplates::USER_PROFILE, ['id' => $userId], function() use ($userId) {
            return $this->fetchUserFromDatabase($userId);
        });
    }
    
    /**
     * 获取用户设置
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserSettings($userId)
    {
        return $this->getCache(CacheTemplates::USER_SETTINGS, ['id' => $userId], function() use ($userId) {
            return $this->fetchUserSettingsFromDatabase($userId);
        });
    }
    
    /**
     * 获取用户购物车
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserCart($userId)
    {
        return $this->getCache(CacheTemplates::USER_CART, ['user_id' => $userId], function() use ($userId) {
            return $this->fetchUserCartFromDatabase($userId);
        }, 600); // 购物车缓存10分钟
    }
    
    /**
     * 获取用户愿望清单
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserWishlist($userId)
    {
        return $this->getCache(CacheTemplates::USER_WISHLIST, ['user_id' => $userId], function() use ($userId) {
            return $this->fetchUserWishlistFromDatabase($userId);
        });
    }
    
    /**
     * 获取用户订单
     * 
     * @param int $userId 用户ID
     * @param int $page 页码
     * @return array
     */
    public function getUserOrders($userId, $page = 1)
    {
        return $this->getCache(CacheTemplates::USER_ORDERS, [
            'user_id' => $userId,
            'page' => $page
        ], function() use ($userId, $page) {
            return $this->fetchUserOrdersFromDatabase($userId, $page);
        });
    }
    
    /**
     * 更新用户档案
     * 
     * @param int $userId 用户ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateUserProfile($userId, array $data)
    {
        $result = $this->updateUserInDatabase($userId, $data);
        
        if ($result) {
            // 清除用户相关缓存
            $this->forgetCache(CacheTemplates::USER_PROFILE, ['id' => $userId]);
            $this->clearTag("user_{$userId}");
        }
        
        return $result;
    }
    
    /**
     * 更新用户购物车
     * 
     * @param int $userId 用户ID
     * @param array $cartData 购物车数据
     * @return bool
     */
    public function updateUserCart($userId, array $cartData)
    {
        $result = $this->updateUserCartInDatabase($userId, $cartData);
        
        if ($result) {
            // 清除购物车缓存
            $this->forgetCache(CacheTemplates::USER_CART, ['user_id' => $userId]);
        }
        
        return $result;
    }
    
    /**
     * 批量获取用户档案
     * 
     * @param array $userIds 用户ID列表
     * @return array
     */
    public function getMultipleUserProfiles(array $userIds)
    {
        $paramsList = array_map(function($id) {
            return ['id' => $id];
        }, $userIds);
        
        return $this->getMultipleCache(CacheTemplates::USER_PROFILE, $paramsList, function($missingKeys) {
            $result = [];
            foreach ($missingKeys as $key) {
                if (preg_match('/user_profile:(\d+)/', $key, $matches)) {
                    $id = $matches[1];
                    $result[$key] = $this->fetchUserFromDatabase($id);
                }
            }
            return $result;
        });
    }
    
    // 私有方法：模拟数据库操作
    private function fetchUserFromDatabase($userId)
    {
        return [
            'id' => $userId,
            'name' => "User {$userId}",
            'email' => "user{$userId}@example.com",
            'avatar' => "avatar{$userId}.jpg",
            'level' => 'VIP',
            'points' => rand(100, 1000),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    private function fetchUserSettingsFromDatabase($userId)
    {
        return [
            'user_id' => $userId,
            'theme' => 'dark',
            'language' => 'zh-CN',
            'notifications' => true,
            'privacy_level' => 'medium',
        ];
    }
    
    private function fetchUserCartFromDatabase($userId)
    {
        return [
            'user_id' => $userId,
            'items' => [
                ['product_id' => 1, 'quantity' => 2, 'price' => 299],
                ['product_id' => 2, 'quantity' => 1, 'price' => 599],
            ],
            'total' => 1197,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    private function fetchUserWishlistFromDatabase($userId)
    {
        return [
            'user_id' => $userId,
            'items' => [
                ['product_id' => 3, 'added_at' => '2024-01-01'],
                ['product_id' => 4, 'added_at' => '2024-01-02'],
            ],
            'count' => 2,
        ];
    }
    
    private function fetchUserOrdersFromDatabase($userId, $page)
    {
        return [
            'user_id' => $userId,
            'page' => $page,
            'total_pages' => 5,
            'orders' => [
                ['id' => 1001, 'total' => 299.99, 'status' => 'completed'],
                ['id' => 1002, 'total' => 199.99, 'status' => 'processing'],
            ]
        ];
    }
    
    private function updateUserInDatabase($userId, array $data)
    {
        return true;
    }
    
    private function updateUserCartInDatabase($userId, array $cartData)
    {
        return true;
    }
}
