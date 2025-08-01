<?php

namespace Asfop\CacheKV;

use Asfop\CacheKV\Cache\CacheDriver;
use Asfop\CacheKV\Cache\KeyManager;

/**
 * CacheKV 主缓存管理类
 * 
 * 提供统一的缓存操作接口，核心功能包括：
 * - 自动回填缓存（若无则从数据源获取并回填）
 * - 模板化键管理
 * - 批量操作
 * - 标签管理
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
     * 并将获取到的数据回填到缓存中。
     *
     * @param string $key 缓存项的唯一键名
     * @param callable|null $callback 当缓存未命中时执行的回调函数
     * @param int|null $ttl 缓存有效期（秒），null 使用默认值
     * @return mixed 缓存中存储的值或回调函数返回的值
     */
    public function get($key, $callback = null, $ttl = null)
    {
        $value = $this->driver->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        if ($callback !== null && is_callable($callback)) {
            $value = $callback();
            $this->set($key, $value, $ttl);
            return $value;
        }
        
        return null;
    }

    /**
     * 将指定的键值对存储到缓存中
     *
     * @param string $key 缓存项的唯一键名
     * @param mixed $value 要存储的值
     * @param int|null $ttl 缓存有效期（秒），null 使用默认值
     * @return bool 存储操作是否成功
     */
    public function set($key, $value, $ttl = null)
    {
        if (empty($key)) {
            return false;
        }
        
        $effectiveTtl = $ttl !== null ? $ttl : $this->defaultTtl;
        return $this->driver->set($key, $value, $effectiveTtl);
    }

    /**
     * 设置带标签的缓存项
     *
     * @param string $key 缓存项的唯一键名
     * @param mixed $value 要存储的值
     * @param array $tags 标签数组
     * @param int|null $ttl 缓存有效期（秒），null 使用默认值
     * @return bool 存储操作是否成功
     */
    public function setWithTag($key, $value, $tags, $ttl = null)
    {
        if (empty($key) || empty($tags)) {
            return false;
        }
        
        $effectiveTtl = $ttl !== null ? $ttl : $this->defaultTtl;
        
        // 先设置缓存
        if (!$this->driver->set($key, $value, $effectiveTtl)) {
            return false;
        }
        
        // 再设置标签关联
        if (method_exists($this->driver, 'tag')) {
            return $this->driver->tag($key, $tags);
        }
        
        return true;
    }

    /**
     * 批量获取多个键的值
     *
     * @param array $keys 要获取的键名数组
     * @param callable|null $callback 处理未命中键的回调函数
     * @param int|null $ttl 缓存有效期（秒），null 使用默认值
     * @return array 包含所有请求键的键值对数组
     */
    public function getMultiple($keys, $callback = null, $ttl = null)
    {
        if (empty($keys) || !is_array($keys)) {
            return [];
        }
        
        // 批量获取现有缓存
        $results = [];
        if (method_exists($this->driver, 'getMultiple')) {
            $results = $this->driver->getMultiple($keys);
        } else {
            foreach ($keys as $key) {
                $value = $this->driver->get($key);
                if ($value !== null) {
                    $results[$key] = $value;
                }
            }
        }
        
        // 找出未命中的键
        $missingKeys = array_values(array_diff($keys, array_keys($results)));
        
        if (!empty($missingKeys) && $callback !== null && is_callable($callback)) {
            $newData = $callback($missingKeys);
            
            if (is_array($newData)) {
                foreach ($newData as $key => $value) {
                    $this->set($key, $value, $ttl);
                    $results[$key] = $value;
                }
            }
        }
        
        return $results;
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
     * 删除指定键的缓存项（别名方法）
     *
     * @param string $key 要删除的缓存键
     * @return bool 删除操作是否成功
     */
    public function forget($key)
    {
        return $this->delete($key);
    }

    /**
     * 清除指定标签的所有缓存项
     *
     * @param string $tag 要清除的标签名称
     * @return bool 清除操作是否成功
     */
    public function clearTag($tag)
    {
        if (empty($tag)) {
            return false;
        }
        
        if (method_exists($this->driver, 'clearTag')) {
            return $this->driver->clearTag($tag);
        }
        
        return false;
    }

    /**
     * 检查缓存中是否存在指定键的缓存项
     *
     * @param string $key 缓存项的唯一键名
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
     * 获取缓存统计信息
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
     * 使用模板生成键并获取缓存数据
     *
     * @param string $template 模板名称
     * @param array $params 参数
     * @param callable|null $callback 回调函数
     * @param int|null $ttl 缓存时间
     * @return mixed
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function getByTemplate($template, $params = [], $callback = null, $ttl = null)
    {
        $this->ensureKeyManagerSet();
        
        $key = $this->keyManager->make($template, $params);
        return $this->get($key, $callback, $ttl);
    }

    /**
     * 使用模板生成键并设置缓存数据
     *
     * @param string $template 模板名称
     * @param array $params 参数
     * @param mixed $value 值
     * @param int|null $ttl 缓存时间
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
     * 使用模板生成键并设置带标签的缓存数据
     *
     * @param string $template 模板名称
     * @param array $params 参数
     * @param mixed $value 值
     * @param array $tags 标签
     * @param int|null $ttl 缓存时间
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
     * 使用模板生成键并删除缓存数据
     *
     * @param string $template 模板名称
     * @param array $params 参数
     * @return bool
     * @throws \RuntimeException 当 KeyManager 未设置时
     */
    public function deleteByTemplate($template, $params = [])
    {
        $this->ensureKeyManagerSet();
        
        $key = $this->keyManager->make($template, $params);
        return $this->delete($key);
    }

    /**
     * 使用模板生成键并删除缓存数据（别名方法）
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
     * 使用模板生成缓存键
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
     * @param int $ttl TTL 值（秒）
     * @return void
     */
    public function setDefaultTtl($ttl)
    {
        $this->defaultTtl = max(1, (int) $ttl);
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
        
        if (method_exists($this->driver, 'setMultiple')) {
            return $this->driver->setMultiple($values, $effectiveTtl);
        }
        
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
