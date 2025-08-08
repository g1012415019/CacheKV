<?php

namespace Asfop\CacheKV\Tag;

use Asfop\CacheKV\Drivers\DriverInterface;

/**
 * 缓存标签管理器
 * 
 * 管理缓存标签的分组和关联关系
 * 支持清除单个缓存时自动清除关联缓存
 */
class CacheTagManager
{
    /**
     * 驱动实例
     * 
     * @var DriverInterface
     */
    private $driver;
    
    /**
     * 标签前缀
     * 
     * @var string
     */
    private $tagPrefix;
    
    /**
     * 键标签映射前缀
     * 
     * @var string
     */
    private $keyTagPrefix;

    /**
     * 构造函数
     * 
     * @param DriverInterface $driver 驱动实例
     * @param string $tagPrefix 标签前缀
     */
    public function __construct($driver, $tagPrefix = 'tag:')
    {
        $this->driver = $driver;
        $this->tagPrefix = $tagPrefix;
        $this->keyTagPrefix = 'key_tags:';
    }

    /**
     * 获取标签键
     * 
     * @param string $tag 标签名
     * @return string 标签键
     */
    private function getTagKey($tag)
    {
        return $this->tagPrefix . $tag;
    }
    
    /**
     * 获取键标签映射键
     * 
     * @param string $key 缓存键
     * @return string 键标签映射键
     */
    private function getKeyTagsKey($key)
    {
        return $this->keyTagPrefix . $key;
    }

    /**
     * 为缓存键添加标签
     * 
     * @param string $key 缓存键
     * @param array $tags 标签数组
     * @param int $ttl 过期时间
     */
    public function addTags($key, array $tags, $ttl = 0)
    {
        if (empty($tags)) {
            return;
        }
        
        // 为每个标签添加键的关联
        foreach ($tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $this->driver->addToSet($tagKey, $key);
            
            if ($ttl > 0) {
                $this->driver->expire($tagKey, $ttl);
            }
        }
        
        // 记录键的标签列表（用于删除键时清理标签）
        $keyTagsKey = $this->getKeyTagsKey($key);
        $existingTags = $this->driver->getSet($keyTagsKey);
        $allTags = array_unique(array_merge($existingTags, $tags));
        
        // 清空并重新添加所有标签
        $this->driver->delete($keyTagsKey);
        foreach ($allTags as $tag) {
            $this->driver->addToSet($keyTagsKey, $tag);
        }
        
        if ($ttl > 0) {
            $this->driver->expire($keyTagsKey, $ttl);
        }
    }

    /**
     * 根据标签获取所有相关的缓存键
     * 
     * @param string $tag 标签名称
     * @return array
     */
    public function getKeysByTag($tag)
    {
        $tagKey = $this->getTagKey($tag);
        return $this->driver->getSet($tagKey);
    }

    /**
     * 根据标签清除缓存
     * 
     * @param string $tag 标签名称
     * @return int 清除的缓存数量
     */
    public function clearByTag($tag)
    {
        $keys = $this->getKeysByTag($tag);
        
        if (empty($keys)) {
            return 0;
        }
        
        // 删除所有相关的缓存键
        $this->driver->deleteMultiple($keys);
        
        // 清理标签关联
        $this->cleanupTagAssociations($keys);
        
        // 删除标签集合
        $tagKey = $this->getTagKey($tag);
        $this->driver->delete($tagKey);
        
        return count($keys);
    }

    /**
     * 清除单个缓存键及其关联缓存
     * 
     * @param string $key 缓存键
     * @return array 清除的所有键列表
     */
    public function clearKeyWithAssociations($key)
    {
        $clearedKeys = array($key);
        
        // 获取键的所有标签
        $keyTagsKey = $this->getKeyTagsKey($key);
        $tags = $this->driver->getSet($keyTagsKey);
        
        if (!empty($tags)) {
            // 获取所有关联的键
            $associatedKeys = array();
            foreach ($tags as $tag) {
                $tagKeys = $this->getKeysByTag($tag);
                $associatedKeys = array_merge($associatedKeys, $tagKeys);
            }
            
            // 去重并移除自身
            $associatedKeys = array_unique($associatedKeys);
            $associatedKeys = array_diff($associatedKeys, array($key));
            
            if (!empty($associatedKeys)) {
                // 删除关联的缓存键
                $this->driver->deleteMultiple($associatedKeys);
                $clearedKeys = array_merge($clearedKeys, $associatedKeys);
                
                // 清理关联键的标签关联
                $this->cleanupTagAssociations($associatedKeys);
            }
        }
        
        // 删除主键
        $this->driver->delete($key);
        
        // 清理主键的标签关联
        $this->cleanupTagAssociations(array($key));
        
        return $clearedKeys;
    }

    /**
     * 获取缓存键的标签列表
     * 
     * @param string $key 缓存键
     * @return array
     */
    public function getKeyTags($key)
    {
        $keyTagsKey = $this->getKeyTagsKey($key);
        return $this->driver->getSet($keyTagsKey);
    }

    /**
     * 检查缓存键是否有指定标签
     * 
     * @param string $key 缓存键
     * @param string $tag 标签名称
     * @return bool
     */
    public function keyHasTag($key, $tag)
    {
        $tags = $this->getKeyTags($key);
        return in_array($tag, $tags);
    }

    /**
     * 移除缓存键的指定标签
     * 
     * @param string $key 缓存键
     * @param array $tags 要移除的标签数组
     */
    public function removeTags($key, array $tags)
    {
        if (empty($tags)) {
            return;
        }
        
        $keyTagsKey = $this->getKeyTagsKey($key);
        $existingTags = $this->driver->getSet($keyTagsKey);
        
        // 从标签集合中移除键
        foreach ($tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            // 这里需要实现从集合中移除特定成员的方法
            // 由于简化，我们重新构建集合
            $tagKeys = $this->driver->getSet($tagKey);
            $tagKeys = array_diff($tagKeys, array($key));
            
            $this->driver->delete($tagKey);
            foreach ($tagKeys as $remainingKey) {
                $this->driver->addToSet($tagKey, $remainingKey);
            }
        }
        
        // 更新键的标签列表
        $remainingTags = array_diff($existingTags, $tags);
        $this->driver->delete($keyTagsKey);
        foreach ($remainingTags as $tag) {
            $this->driver->addToSet($keyTagsKey, $tag);
        }
    }

    /**
     * 清理标签关联
     * 
     * @param array $keys 要清理的键数组
     */
    private function cleanupTagAssociations(array $keys)
    {
        foreach ($keys as $key) {
            // 获取键的标签
            $keyTagsKey = $this->getKeyTagsKey($key);
            $tags = $this->driver->getSet($keyTagsKey);
            
            // 从每个标签集合中移除这个键
            foreach ($tags as $tag) {
                $tagKey = $this->getTagKey($tag);
                $tagKeys = $this->driver->getSet($tagKey);
                $tagKeys = array_diff($tagKeys, array($key));
                
                $this->driver->delete($tagKey);
                foreach ($tagKeys as $remainingKey) {
                    $this->driver->addToSet($tagKey, $remainingKey);
                }
            }
            
            // 删除键的标签记录
            $this->driver->delete($keyTagsKey);
        }
    }

    /**
     * 获取所有标签列表
     * 
     * @return array
     */
    public function getAllTags()
    {
        // 这里需要实现获取所有以标签前缀开头的键
        // 由于简化，返回空数组
        return array();
    }

    /**
     * 获取标签统计信息
     * 
     * @return array
     */
    public function getTagStats()
    {
        $stats = array();
        
        // 这里可以实现更详细的标签统计
        // 比如每个标签关联的键数量等
        
        return $stats;
    }
}
