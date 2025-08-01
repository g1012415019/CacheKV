<?php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\CacheDriver;
use Asfop\CacheKV\Cache\KeyManager;

/**
 * CacheKV 主缓存管理类
 * 
 * 提供统一的缓存操作接口，支持自动回填、批量操作、标签管理和键管理等功能。
 */
class CacheKV
{
    /**
     * @var CacheDriver 缓存驱动实例
     */
    private $driver;

    /**
     * @var int 默认缓存时间（秒）
     */
    private $defaultTtl;
    
    /**
     * @var KeyManager|null 键管理器实例
     */
    private $keyManager;

    /**
     * 构造函数
     *
     * @param CacheDriver $driver 缓存驱动实例
     * @param int $defaultTtl 默认缓存有效期（秒）
     * @param KeyManager|null $keyManager 键管理器实例
     */
    public function __construct(CacheDriver $driver, $defaultTtl = 3600, KeyManager $keyManager = null)
    {
        $this->driver = $driver;
        $this->defaultTtl = $defaultTtl;
        $this->keyManager = $keyManager;
    }

    /**
     * 从缓存中获取指定键的值
     * 
     * 如果缓存中不存在该键，且提供了回调函数，则会执行回调函数从数据源获取数据，
     * 并将获取到的数据（包括 null）回填到缓存中，以防止缓存穿透。
     *
     * @param string $key 缓存项的唯一键名
     * @param callable|null $callback 当缓存未命中时的回调函数
     * @param int|null $ttl 缓存有效期（秒）
     * @param bool $slidingExpiration 是否启用滑动过期
     * @return mixed|null 缓存中存储的值
     */
    public function get($key, $callback = null, $ttl = null, $slidingExpiration = false)
    {
        $value = $this->driver->get($key);

        // 如果缓存命中且启用了滑动过期，则延长过期时间
        if ($value !== null && $slidingExpiration) {
            $slidingTtl = $ttl !== null ? $ttl : $this->defaultTtl;
            $this->driver->touch($key, $slidingTtl);
        }

        // 缓存命中，直接返回
        if ($value !== null) {
            return $value;
        }

        // 缓存未命中，如果提供了回调函数，则从数据源获取并回填
        if ($callback !== null && is_callable($callback)) {
            $fetchedValue = call_user_func($callback);
            // 即使 fetchedValue 为 null 也会被缓存，以防止缓存穿透
            $this->set($key, $fetchedValue, $ttl);
            return $fetchedValue;
        }

        // 缓存未命中且未提供回调函数
        return null;
    }

    /**
     * 将一个键值对存储到缓存中
     *
     * @param string $key 缓存项的唯一键名
     * @param mixed $value 要存储的数据
     * @param int|null $ttl 缓存有效期（秒）
     * @return bool 存储操作是否成功
     */
    public function set($key, $value, $ttl = null)
    {
        if (empty($key)) {
            return false;
        }
        
        return $this->driver->set($key, $value, $ttl !== null ? $ttl : $this->defaultTtl);
    }

    /**
     * 将一个键值对存储到缓存中，并与一个或多个标签关联
     *
     * @param string $key 缓存项的唯一键名
     * @param mixed $value 要存储的数据
     * @param string|array $tags 单个标签名或标签名数组
     * @param int|null $ttl 缓存有效期（秒）
     * @return bool 存储操作是否成功
     */
    public function setWithTag($key, $value, $tags, $ttl = null)
    {
        if (empty($key) || empty($tags)) {
            return false;
        }
        
        $result = $this->set($key, $value, $ttl);
        if ($result) {
            $this->driver->tag($key, (array) $tags);
        }
        return $result;
    }

    /**
     * 从缓存中批量获取多个键值对
     * 
     * 对于缓存未命中的键，会调用提供的回调函数从数据源批量获取数据，
     * 并将获取到的数据回填到缓存中。
     *
     * @param array $keys 缓存项键名的数组
     * @param callable|null $callback 批量获取数据的回调函数
     * @param int|null $ttl 缓存有效期（秒）
     * @return array 包含所有请求键的键值对数组
     */
    public function getMultiple($keys, $callback = null, $ttl = null)
    {
        if (empty($keys) || !is_array($keys)) {
            return [];
        }

        $cachedValues = $this->driver->getMultiple($keys);
        $missingKeys = [];
        $results = [];

        // 分离缓存命中和未命中的键
        foreach ($keys as $key) {
            if (array_key_exists($key, $cachedValues)) {
                $results[$key] = $cachedValues[$key];
            } else {
                $missingKeys[] = $key;
            }
        }

        // 如果有未命中的键且提供了回调函数，则批量获取
        if (!empty($missingKeys) && $callback !== null && is_callable($callback)) {
            try {
                $fetchedValues = call_user_func($callback, $missingKeys);
                
                if (is_array($fetchedValues) && !empty($fetchedValues)) {
                    // 批量写入缓存
                    $this->driver->setMultiple($fetchedValues, $ttl !== null ? $ttl : $this->defaultTtl);
                    
                    // 合并到结果中
                    foreach ($fetchedValues as $key => $value) {
                        $results[$key] = $value;
                    }
                }
            } catch (\Exception $e) {
                // 回调函数执行失败，记录错误但不影响已缓存的数据返回
                error_log("CacheKV getMultiple callback failed: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * 从缓存中移除指定键的缓存项
     *
     * @param string $key 要移除的缓存项的键名
     * @return bool 移除操作是否成功
     */
    public function forget($key)
    {
        return $this->delete($key);
    }

    /**
     * 清除指定标签下的所有缓存项
     *
     * @param string $tag 要清除的标签名
     * @return bool 清除操作是否成功
     */
    public function clearTag($tag)
    {
        if (empty($tag)) {
            return false;
        }
        
        return $this->driver->clearTag($tag);
    }

    /**
     * 检查缓存中是否存在指定键的缓存项
     *
     * @param string $key 要检查的缓存项的键名
     * @return bool 如果缓存中存在该键且未过期，则返回 true
     */
    public function has($key)
    {
        if (empty($key)) {
            return false;
        }
        
        return $this->driver->has($key);
    }

    /**
     * 获取缓存的统计信息
     *
     * @return array 包含命中次数、未命中次数和命中率的数组
     */
    public function getStats()
    {
        return $this->driver->getStats();
    }
    
    /**
     * 设置键管理器
     * 
     * @param KeyManager $keyManager 键管理器实例
     * @return void
     */
    public function setKeyManager(KeyManager $keyManager)
    {
        $this->keyManager = $keyManager;
    }
    
    /**
     * 获取键管理器
     * 
     * @return KeyManager|null
     */
    public function getKeyManager()
    {
        return $this->keyManager;
    }
    
    /**
     * 使用模板生成键并获取缓存
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param callable|null $callback 回调函数
     * @param int|null $ttl 缓存过期时间
     * @param bool $slidingExpiration 是否启用滑动过期
     * @return mixed
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function getByTemplate($template, $params = [], $callback = null, $ttl = null, $slidingExpiration = false)
    {
        $this->ensureKeyManagerSet();
        
        $key = $this->keyManager->make($template, $params);
        return $this->get($key, $callback, $ttl, $slidingExpiration);
    }
    
    /**
     * 使用模板生成键并设置缓存
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param mixed $value 缓存值
     * @param int|null $ttl 缓存过期时间
     * @return bool
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function setByTemplate($template, $params = [], $value = null, $ttl = null)
    {
        $this->ensureKeyManagerSet();
        
        $key = $this->keyManager->make($template, $params);
        return $this->set($key, $value, $ttl);
    }
    
    /**
     * 使用模板生成键并设置带标签的缓存
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param mixed $value 缓存值
     * @param string|array $tags 标签
     * @param int|null $ttl 缓存过期时间
     * @return bool
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function setByTemplateWithTag($template, $params = [], $value = null, $tags = [], $ttl = null)
    {
        $this->ensureKeyManagerSet();
        
        $key = $this->keyManager->make($template, $params);
        return $this->setWithTag($key, $value, $tags, $ttl);
    }
    
    /**
     * 使用模板生成键并删除缓存
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @return bool
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function forgetByTemplate($template, $params = [])
    {
        return $this->deleteByTemplate($template, $params);
    }
    
    /**
     * 使用模板生成键并检查缓存是否存在
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @return bool
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function hasByTemplate($template, $params = [])
    {
        $this->ensureKeyManagerSet();
        
        $key = $this->keyManager->make($template, $params);
        return $this->has($key);
    }
    
    /**
     * 生成缓存键（不执行缓存操作）
     * 
     * @param string $template 模板名称
     * @param array $params 参数
     * @param bool $withPrefix 是否包含前缀
     * @return string
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function makeKey($template, $params = [], $withPrefix = true)
    {
        $this->ensureKeyManagerSet();
        
        return $this->keyManager->make($template, $params, $withPrefix);
    }
    
    /**
     * 获取默认 TTL
     * 
     * @return int
     */
    public function getDefaultTtl()
    {
        return $this->defaultTtl;
    }
    
    /**
     * 设置默认 TTL
     * 
     * @param int $ttl 默认过期时间（秒）
     * @return void
     */
    public function setDefaultTtl($ttl)
    {
        $this->defaultTtl = max(1, (int) $ttl); // 确保 TTL 至少为 1 秒
    }
    
    /**
     * 获取缓存驱动实例
     * 
     * @return CacheDriver
     */
    public function getDriver()
    {
        return $this->driver;
    }
    
    /**
     * 删除指定键的缓存项
     * 
     * @param string $key 要删除的缓存键
     * @return bool 删除操作是否成功
     */
    public function delete($key)
    {
        if (empty($key)) {
            return false;
        }
        
        return $this->driver->delete($key);
    }
    
    /**
     * 根据模板删除缓存项
     * 
     * @param string $template 模板名称
     * @param array $params 模板参数
     * @return bool 删除操作是否成功
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function deleteByTemplate($template, $params = [])
    {
        $this->ensureKeyManagerSet();
        
        $key = $this->keyManager->make($template, $params);
        return $this->delete($key);
    }
    
    /**
     * 批量设置缓存项
     * 
     * @param array $values 键值对数组
     * @param int|null $ttl 缓存时间（秒）
     * @return bool 设置操作是否成功
     */
    public function setMultiple($values, $ttl = null)
    {
        if (empty($values) || !is_array($values)) {
            return false;
        }
        
        $effectiveTtl = $ttl !== null ? $ttl : $this->defaultTtl;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $effectiveTtl)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 批量删除缓存项
     * 
     * @param array $keys 要删除的键数组
     * @return bool 删除操作是否成功
     */
    public function deleteMultiple($keys)
    {
        if (empty($keys) || !is_array($keys)) {
            return false;
        }
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 根据模式获取匹配的键
     * 
     * @param string $pattern 匹配模式（支持通配符 *）
     * @return array 匹配的键数组
     */
    public function keys($pattern = '*')
    {
        if (method_exists($this->driver, 'keys')) {
            return $this->driver->keys($pattern);
        }
        
        // 如果驱动不支持 keys 方法，返回空数组
        return [];
    }
    
    /**
     * 清空所有缓存
     * 
     * @return bool 清空操作是否成功
     */
    public function flush()
    {
        if (method_exists($this->driver, 'flush')) {
            return $this->driver->flush();
        }
        
        // 如果驱动不支持 flush 方法，尝试通过其他方式清空
        if (method_exists($this->driver, 'clear')) {
            return $this->driver->clear();
        }
        
        return false;
    }
    
    /**
     * 确保 KeyManager 已设置
     * 
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    private function ensureKeyManagerSet()
    {
        if (!$this->keyManager) {
            throw new \RuntimeException('KeyManager not set. Please set KeyManager first.');
        }
    }
}
