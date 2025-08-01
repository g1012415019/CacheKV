<?php

namespace Asfop\CacheKV\Services;

use Asfop\CacheKV\CacheTemplates;

/**
 * 产品服务类
 * 封装所有产品相关的缓存操作
 */
class ProductService extends CacheServiceBase
{
    /**
     * 获取产品详情
     * 
     * @param int $productId 产品ID
     * @return array
     */
    public function getProductDetail($productId)
    {
        return $this->getCache(CacheTemplates::PRODUCT_DETAIL, ['id' => $productId], function() use ($productId) {
            // 模拟从数据库获取产品详情
            return $this->fetchProductFromDatabase($productId);
        });
    }
    
    /**
     * 获取产品评论
     * 
     * @param int $productId 产品ID
     * @param int $page 页码
     * @return array
     */
    public function getProductReviews($productId, $page = 1)
    {
        return $this->getCache(CacheTemplates::PRODUCT_REVIEWS, [
            'id' => $productId, 
            'page' => $page
        ], function() use ($productId, $page) {
            return $this->fetchProductReviewsFromDatabase($productId, $page);
        });
    }
    
    /**
     * 获取产品库存
     * 
     * @param int $productId 产品ID
     * @return array
     */
    public function getProductStock($productId)
    {
        return $this->getCache(CacheTemplates::PRODUCT_STOCK, ['id' => $productId], function() use ($productId) {
            return $this->fetchProductStockFromDatabase($productId);
        }, 300); // 库存信息缓存5分钟
    }
    
    /**
     * 获取相关产品
     * 
     * @param int $productId 产品ID
     * @param int $limit 限制数量
     * @return array
     */
    public function getRelatedProducts($productId, $limit = 10)
    {
        return $this->getCache(CacheTemplates::PRODUCT_RELATED, [
            'id' => $productId,
            'limit' => $limit
        ], function() use ($productId, $limit) {
            return $this->fetchRelatedProductsFromDatabase($productId, $limit);
        });
    }
    
    /**
     * 批量获取产品详情
     * 
     * @param array $productIds 产品ID列表
     * @return array
     */
    public function getMultipleProductDetails(array $productIds)
    {
        $paramsList = array_map(function($id) {
            return ['id' => $id];
        }, $productIds);
        
        return $this->getMultipleCache(CacheTemplates::PRODUCT_DETAIL, $paramsList, function($missingKeys) {
            // 批量获取缺失的产品数据
            $result = [];
            foreach ($missingKeys as $key) {
                if (preg_match('/product_detail:(\d+)/', $key, $matches)) {
                    $id = $matches[1];
                    $result[$key] = $this->fetchProductFromDatabase($id);
                }
            }
            return $result;
        });
    }
    
    /**
     * 更新产品信息（清除相关缓存）
     * 
     * @param int $productId 产品ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateProduct($productId, array $data)
    {
        // 更新数据库
        $result = $this->updateProductInDatabase($productId, $data);
        
        if ($result) {
            // 清除相关缓存
            $this->forgetCache(CacheTemplates::PRODUCT_DETAIL, ['id' => $productId]);
            $this->forgetCache(CacheTemplates::PRODUCT_STOCK, ['id' => $productId]);
            
            // 如果有标签，也可以清除标签缓存
            $this->clearTag("product_{$productId}");
        }
        
        return $result;
    }
    
    /**
     * 设置产品详情缓存（带标签）
     * 
     * @param int $productId 产品ID
     * @param array $productData 产品数据
     * @return bool
     */
    public function setProductDetailWithTag($productId, array $productData)
    {
        return $this->setCacheWithTag(
            CacheTemplates::PRODUCT_DETAIL,
            ['id' => $productId],
            $productData,
            ['products', "product_{$productId}", 'product_details']
        );
    }
    
    // 私有方法：模拟数据库操作
    private function fetchProductFromDatabase($productId)
    {
        // 模拟数据库查询
        return [
            'id' => $productId,
            'name' => "Product {$productId}",
            'price' => rand(100, 1000),
            'description' => "Description for product {$productId}",
            'category_id' => rand(1, 10),
            'stock' => rand(0, 100),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    private function fetchProductReviewsFromDatabase($productId, $page)
    {
        // 模拟数据库查询
        return [
            'product_id' => $productId,
            'page' => $page,
            'total_pages' => 10,
            'reviews' => [
                ['rating' => 5, 'comment' => 'Great product!', 'user' => 'User A'],
                ['rating' => 4, 'comment' => 'Good quality', 'user' => 'User B'],
            ]
        ];
    }
    
    private function fetchProductStockFromDatabase($productId)
    {
        // 模拟数据库查询
        return [
            'product_id' => $productId,
            'stock' => rand(0, 100),
            'reserved' => rand(0, 10),
            'available' => rand(0, 90),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    private function fetchRelatedProductsFromDatabase($productId, $limit)
    {
        // 模拟数据库查询
        $products = [];
        for ($i = 1; $i <= $limit; $i++) {
            $relatedId = $productId + $i;
            $products[] = [
                'id' => $relatedId,
                'name' => "Related Product {$relatedId}",
                'price' => rand(50, 500),
            ];
        }
        return $products;
    }
    
    private function updateProductInDatabase($productId, array $data)
    {
        // 模拟数据库更新
        return true;
    }
}
