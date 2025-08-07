<?php

namespace Asfop\CacheKV\Core;

use Asfop\CacheKV\Drivers\DriverInterface;
use Asfop\CacheKV\Key\CacheKey;
use Asfop\CacheKV\Stats\KeyStats;

/**
 * CacheKV - 简洁版缓存操作核心类
 *
 * 根据 CacheKey 的配置决定是否启用统计功能
 * 保持简洁和高性能
 * 兼容 PHP 7.0
 */
class CacheKV
{
    /**
     * 缓存驱动
     *
     * @var DriverInterface
     */
    private $driver;

    /**
     * 空值标识符
     *
     * @var string
     */
    const NULL_VALUE = '__CACHE_KV_NULL__';

    /**
     * 构造函数
     *
     * @param DriverInterface $driver 缓存驱动
     */
    public function __construct($driver)
    {
        $this->driver = $driver;
        
        // 启用统计功能（如果配置启用）
        try {
            $cacheConfig = ConfigManager::getGlobalCacheConfigObject();
            if ($cacheConfig->isEnableStats()) {
                KeyStats::enable();
            }
        } catch (\Exception $e) {
            // 如果配置未加载，默认不启用统计功能
            // 这样可以保证在没有配置的情况下也能正常工作
        }
    }

    /**
     * 获取缓存，若无则执行回调并回填
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @param callable|null $callback 回调函数
     * @param int|null $ttl 自定义TTL（覆盖配置）
     * @return mixed 缓存数据或回调结果
     */
    public function get($cacheKey, $callback = null, $ttl = null)
    {
        $key = (string)$cacheKey;

        // 尝试从缓存获取
        $cached = $this->driver->get($key);

        if ($cached !== null) {
            // 根据键配置决定是否记录统计
            if ($cacheKey->getCacheConfig()->isEnableStats()) {
                KeyStats::recordHit($key);
                
                // 检查并处理热点键自动续期
                $this->checkAndRenewHotKey($cacheKey);
            }

            // 处理空值缓存
            if ($cached === self::NULL_VALUE) {
                return null;
            }

            return $this->unserialize($cached);
        }

        // 记录未命中统计
        if ($cacheKey->getKeyConfig()->getCacheConfig()->isEnableStats()) {
            KeyStats::recordMiss($key);
        }

        // 缓存未命中，执行回调
        if ($callback === null) {
            return null;
        }

        $data = $callback();

        // 回填缓存
        $this->set($cacheKey, $data, $ttl);

        return $data;
    }

    /**
     * 设置缓存
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @param mixed $data 要缓存的数据
     * @param int|null $ttl 自定义TTL（覆盖配置）
     * @return bool 是否设置成功
     */
    public function set($cacheKey, $data, $ttl = null)
    {
        $key = (string)$cacheKey;

        // 根据键配置决定是否记录统计
        if ($cacheKey->getCacheConfig()->isEnableStats()) {
            KeyStats::recordSet($key);
        }

        // 获取TTL：优先使用传入的TTL，否则使用配置中的TTL
        $finalTtl = $this->getTtl($cacheKey, $ttl);

        // 处理空值缓存
        if ($data === null && $this->shouldCacheNull($cacheKey)) {
            $nullTtl = $this->getNullCacheTtl($cacheKey);
            return $this->driver->set($key, self::NULL_VALUE, $nullTtl);
        }

        // 序列化并存储
        $serialized = $this->serialize($data);
        return $this->driver->set($key, $serialized, $finalTtl);
    }

    /**
     * 删除缓存
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @return bool 是否删除成功
     */
    public function delete($cacheKey)
    {
        $key = (string)$cacheKey;

        // 根据键配置决定是否记录统计
        if ($cacheKey->getCacheConfig()->isEnableStats()) {
            KeyStats::recordDelete($key);
        }

        return $this->driver->delete($key);
    }

    /**
     * 检查缓存是否存在
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @return bool 是否存在
     */
    public function exists($cacheKey)
    {
        $key = (string)$cacheKey;
        return $this->driver->exists($key);
    }

    /**
     * 批量设置缓存
     *
     * @param array $items CacheKey => data 的键值对数组
     * @param int|null $ttl 自定义TTL（覆盖配置）
     * @return bool 是否设置成功
     */
    public function setMultiple(array $items, $ttl = null)
    {
        if (empty($items)) {
            return true;
        }

        // 按TTL分组，相同TTL的键可以批量设置
        $groupedItems = array();
        $keyConfigs = array(); // 保存每个键的配置，用于统计

        foreach ($items as $cacheKey => $data) {
            if (!($cacheKey instanceof CacheKey)) {
                continue; // 跳过非CacheKey对象
            }

            $keyString = (string)$cacheKey;
            $keyConfigs[$keyString] = $cacheKey;

            // 获取有效TTL
            $effectiveTtl = $this->getTtl($cacheKey, $ttl);
            
            // 处理空值缓存
            if ($data === null && $this->shouldCacheNull($cacheKey)) {
                $nullTtl = $this->getNullCacheTtl($cacheKey);
                $groupedItems[$nullTtl][$keyString] = self::NULL_VALUE;
            } else {
                $serialized = $this->serialize($data);
                $groupedItems[$effectiveTtl][$keyString] = $serialized;
            }
        }

        // 批量设置每个TTL组
        $allSuccess = true;
        foreach ($groupedItems as $groupTtl => $groupItems) {
            $success = $this->driver->setMultiple($groupItems, $groupTtl);
            if (!$success) {
                $allSuccess = false;
            }

            // 批量记录统计
            foreach ($groupItems as $keyString => $value) {
                $cacheKey = $keyConfigs[$keyString];
                if ($cacheKey->getCacheConfig()->isEnableStats()) {
                    KeyStats::recordSet($keyString);
                }
            }
        }

        return $allSuccess;
    }

    /**
     * 批量获取缓存
     *
     * @param CacheKey[] $cacheKeys 缓存键对象数组
     * @param callable|null $callback 回调函数，参数为未命中的键数组，必须返回关联数组格式：['key_string' => 'data', ...]
     * @return array 结果数组，键为CacheKey字符串，值为缓存数据
     */
    public function getMultiple(array $cacheKeys, $callback = null)
    {
        if (empty($cacheKeys)) {
            return array();
        }

        // 转换为字符串键数组
        $stringKeys = array();
        $keyMap = array(); // 字符串键 -> CacheKey对象 的映射

        foreach ($cacheKeys as $cacheKey) {
            $stringKey = (string)$cacheKey;
            $stringKeys[] = $stringKey;
            $keyMap[$stringKey] = $cacheKey;
        }

        // 批量获取
        $results = $this->driver->getMultiple($stringKeys);
        $finalResults = array();
        $missedKeys = array();
        $hitKeys = array(); // 用于批量热点键检查
        $hitKeyStrings = array(); // 用于批量统计
        $missKeyStrings = array(); // 用于批量统计

        foreach ($stringKeys as $stringKey) {
            $cacheKey = $keyMap[$stringKey];
            $cached = isset($results[$stringKey]) ? $results[$stringKey] : null;

            if ($cached !== null) {
                // 收集命中的键，用于批量统计和热点检查
                if ($cacheKey->isStatsEnabled()) {
                    $hitKeys[] = $cacheKey;
                    $hitKeyStrings[] = $stringKey;
                }

                if ($cached === self::NULL_VALUE) {
                    $finalResults[$stringKey] = null;
                } else {
                    $finalResults[$stringKey] = $this->unserialize($cached);
                }
            } else {
                // 收集未命中的键，用于批量统计
                if ($cacheKey->isStatsEnabled()) {
                    $missKeyStrings[] = $stringKey;
                }
                $missedKeys[] = $cacheKey;
            }
        }

        // 批量记录统计（性能优化）
        if (!empty($hitKeyStrings)) {
            KeyStats::recordBatchHits($hitKeyStrings);
        }
        if (!empty($missKeyStrings)) {
            KeyStats::recordBatchMisses($missKeyStrings);
        }

        // 批量处理热点键续期（性能优化）
        $this->batchCheckAndRenewHotKeys($hitKeys);

        // 处理未命中的键
        if (!empty($missedKeys) && $callback !== null) {
            $callbackResults = $callback($missedKeys);

            if (is_array($callbackResults) && !empty($callbackResults)) {
                $batchSetData = array(); // 用于批量设置缓存
                $setKeyStrings = array(); // 用于批量统计
                
                // 只支持关联数组格式：键字符串 => 数据
                foreach ($callbackResults as $keyString => $data) {
                    if (isset($keyMap[$keyString])) {
                        $cacheKey = $keyMap[$keyString];
                        
                        // 收集统计键
                        if ($cacheKey->isStatsEnabled()) {
                            $setKeyStrings[] = $keyString;
                        }
                        
                        // 准备批量设置数据
                        $serializedData = ($data === null) ? self::NULL_VALUE : $this->serialize($data);
                        $batchSetData[$keyString] = $serializedData;
                        $finalResults[$keyString] = $data;
                    }
                }
                
                // 批量设置缓存（性能优化）
                if (!empty($batchSetData)) {
                    // 获取TTL（使用第一个键的配置）
                    $firstKey = reset($missedKeys);
                    $ttl = $this->getTtl($firstKey);
                    
                    $this->driver->setMultiple($batchSetData, $ttl);
                    
                    // 批量记录统计（性能优化）
                    if (!empty($setKeyStrings)) {
                        KeyStats::recordBatchSets($setKeyStrings);
                    }
                }
            }
        }

        return $finalResults;
    }

    /**
     * 获取全局统计（如果有键启用了统计）
     *
     * @return array
     */
    public function getStats()
    {
        return KeyStats::getGlobalStats();
    }

    /**
     * 获取热点键
     *
     * @param int $limit 返回数量限制
     * @return array
     */
    public function getHotKeys($limit = 10)
    {
        return KeyStats::getHotKeys($limit);
    }

    /**
     * 获取指定键的统计
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @return array|null
     */
    public function getKeyStats($cacheKey)
    {
        $key = (string)$cacheKey;
        return KeyStats::getKeyStats($key);
    }

    /**
     * 获取有效的TTL
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @param int|null $customTtl 自定义TTL
     * @return int 最终TTL
     */
    private function getTtl($cacheKey, $customTtl = null)
    {
        // 优先使用传入的TTL
        if ($customTtl !== null) {
            return $customTtl;
        }

        // 直接使用配置对象中的TTL
        $cacheConfig = $cacheKey->getCacheConfig();
        return $cacheConfig !== null ? $cacheConfig->getTtl() : 3600;
    }

    /**
     * 是否应该缓存空值
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @return bool
     */
    private function shouldCacheNull($cacheKey)
    {
        $cacheConfig = $cacheKey->getCacheConfig();
        return $cacheConfig !== null ? $cacheConfig->isEnableNullCache() : false;
    }

    /**
     * 获取空值缓存TTL
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @return int
     */
    private function getNullCacheTtl($cacheKey)
    {
        $cacheConfig = $cacheKey->getCacheConfig();
        return $cacheConfig !== null ? $cacheConfig->getNullCacheTtl() : 300;
    }

    /**
     * 序列化数据
     *
     * @param mixed $data 要序列化的数据
     * @return string 序列化后的字符串
     */
    private function serialize($data)
    {
        if (is_string($data)) {
            return $data;
        }

        return serialize($data);
    }

    /**
     * 反序列化数据
     *
     * @param string $data 序列化的数据
     * @return mixed 反序列化后的数据
     */
    private function unserialize($data)
    {
        // 尝试反序列化，如果失败则返回原始字符串
        $unserialized = @unserialize($data);

        if ($unserialized === false && $data !== serialize(false)) {
            return $data;
        }

        return $unserialized;
    }

    /**
     * 批量检查并处理热点键自动续期
     * 
     * 性能优化：批量处理热点键，减少单独检查的开销
     *
     * @param CacheKey[] $cacheKeys 缓存键对象数组
     * @return array 续期结果数组
     */
    private function batchCheckAndRenewHotKeys(array $cacheKeys)
    {
        if (empty($cacheKeys)) {
            return array();
        }

        // 检查驱动是否支持TTL操作
        if (!method_exists($this->driver, 'ttl') || !method_exists($this->driver, 'expire')) {
            return array();
        }

        $renewResults = array();
        $renewalItems = array(); // 需要续期的键，按TTL分组

        foreach ($cacheKeys as $cacheKey) {
            $cacheConfig = $cacheKey->getCacheConfig();
            
            // 检查是否启用热点键自动续期
            if (!$cacheConfig || !$cacheConfig->isHotKeyAutoRenewal()) {
                continue;
            }

            $key = (string)$cacheKey;
            
            // 检查是否为热点键
            $threshold = $cacheConfig->getHotKeyThreshold();
            $frequency = KeyStats::getKeyFrequency($key);
            
            if ($frequency < $threshold) {
                continue; // 不是热点键
            }

            // 获取当前TTL
            $currentTtl = $this->driver->ttl($key);
            if ($currentTtl <= 0) {
                continue; // 已过期或无TTL
            }

            // 计算新的TTL
            $extendTtl = $cacheConfig->getHotKeyExtendTtl();
            $maxTtl = $cacheConfig->getHotKeyMaxTtl();
            
            $newTtl = min($extendTtl, $maxTtl);
            $newTtl = max($newTtl, $currentTtl);
            
            // 如果新TTL比当前TTL大，则加入续期列表
            if ($newTtl > $currentTtl) {
                $renewalItems[$newTtl][] = $key;
                $renewResults[$key] = true;
            }
        }

        // 批量执行续期操作
        foreach ($renewalItems as $ttl => $keys) {
            foreach ($keys as $key) {
                $this->driver->expire($key, $ttl);
            }
        }

        return $renewResults;
    }

    /**
     * 检查并处理热点键自动续期
     * 
     * 简化实现：只在缓存命中时检查，避免额外开销
     *
     * @param CacheKey $cacheKey 缓存键对象
     * @return bool 是否进行了续期
     */
    private function checkAndRenewHotKey($cacheKey)
    {
        $cacheConfig = $cacheKey->getCacheConfig();
        
        // 检查是否启用热点键自动续期
        if (!$cacheConfig || !$cacheConfig->isHotKeyAutoRenewal()) {
            return false;
        }

        $key = (string)$cacheKey;
        
        // 检查是否为热点键
        $threshold = $cacheConfig->getHotKeyThreshold();
        $frequency = KeyStats::getKeyFrequency($key);
        
        if ($frequency < $threshold) {
            return false; // 不是热点键
        }

        // 检查驱动是否支持TTL操作
        if (!method_exists($this->driver, 'ttl') || !method_exists($this->driver, 'expire')) {
            return false; // 驱动不支持TTL操作
        }

        // 获取当前TTL
        $currentTtl = $this->driver->ttl($key);
        if ($currentTtl <= 0) {
            return false; // 已过期或无TTL
        }

        // 计算新的TTL（简化逻辑）
        $extendTtl = $cacheConfig->getHotKeyExtendTtl();
        $maxTtl = $cacheConfig->getHotKeyMaxTtl();
        
        // 使用延长TTL，但不超过最大TTL，也不小于当前TTL
        $newTtl = min($extendTtl, $maxTtl);
        $newTtl = max($newTtl, $currentTtl);
        
        // 如果新TTL比当前TTL大，则续期
        if ($newTtl > $currentTtl) {
            $this->driver->expire($key, $newTtl);
            return true;
        }

        return false;
    }

    /**
     * 手动触发热点键检查和续期
     * 
     * @param CacheKey $cacheKey 缓存键对象
     * @return bool 是否进行了续期
     */
    public function renewHotKey($cacheKey)
    {
        return $this->checkAndRenewHotKey($cacheKey);
    }

    /**
     * 按前缀删除缓存
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $params 参数数组（可选），用于生成具体的前缀
     * @return int 删除的键数量
     * @throws \Exception
     */
    public function deleteByPrefix($template, array $params = array())
    {
        // 解析模板获取分组和键名
        $parts = explode('.', $template, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Invalid template format: {$template}. Expected format: 'group.key'");
        }
        
        list($groupName, $keyName) = $parts;
        
        // 获取键管理器配置
        $keyManagerConfig = ConfigManager::getKeyManagerConfig();
        
        // 检查分组是否存在
        if (!isset($keyManagerConfig['groups'][$groupName])) {
            throw new \InvalidArgumentException("Group '{$groupName}' not found in configuration");
        }
        
        $groupConfig = $keyManagerConfig['groups'][$groupName];
        
        // 检查键是否存在（简化后的结构）
        if (!isset($groupConfig['keys'][$keyName])) {
            throw new \InvalidArgumentException("Key '{$keyName}' not found in group '{$groupName}'");
        }
        
        // 获取键配置
        $keyConfig = $groupConfig['keys'][$keyName];
        
        // 构建前缀
        $appPrefix = isset($keyManagerConfig['app_prefix']) ? $keyManagerConfig['app_prefix'] : 'app';
        $separator = isset($keyManagerConfig['separator']) ? $keyManagerConfig['separator'] : ':';
        $groupPrefix = $groupConfig['prefix'];
        $groupVersion = $groupConfig['version'];
        
        // 替换模板中的参数
        $keyTemplate = $keyConfig['template'];
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $placeholder = '{' . $param . '}';
                $keyTemplate = str_replace($placeholder, (string)$value, $keyTemplate);
            }
        }
        
        // 构建匹配模式
        // 如果还有未替换的参数，用通配符替换
        $pattern = preg_replace('/\{[^}]+\}/', '*', $keyTemplate);
        $fullPattern = $appPrefix . $separator . $groupPrefix . $separator . $groupVersion . $separator . $pattern;
        
        // 如果没有通配符，添加通配符以匹配该前缀开头的所有键
        if (strpos($fullPattern, '*') === false) {
            $fullPattern .= '*';
        }
        
        // 执行删除
        $deletedCount = $this->driver->deleteByPattern($fullPattern);
        
        return $deletedCount;
    }

    /**
     * 按完整前缀删除缓存（更直接的方式）
     * 
     * @param string $prefix 完整的键前缀，如 'myapp:user:v1:settings:'
     * @return int 删除的键数量
     */
    public function deleteByFullPrefix($prefix)
    {
        // 确保前缀以通配符结尾
        $pattern = rtrim($prefix, '*') . '*';
        
        // 执行删除
        $deletedCount = $this->driver->deleteByPattern($pattern);
        
        return $deletedCount;
    }

    /**
     * 通过模板获取缓存数据
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $params 参数数组
     * @param callable|null $callback 回调函数
     * @param int|null $ttl 自定义TTL
     * @return mixed 缓存数据或回调结果
     */
    public function getByTemplate($template, array $params, $callback = null, $ttl = null)
    {
        // 创建 CacheKey 对象
        $cacheKey = KeyManager::getInstance()->createKeyFromTemplate($template, $params);
        
        // 使用现有的 get 方法
        return $this->get($cacheKey, $callback, $ttl);
    }

    /**
     * 通过模板批量获取缓存数据
     * 
     * @param string $template 键模板，格式：'group.key'
     * @param array $paramsList 参数数组列表
     * @param callable|null $callback 回调函数
     * @return array 结果数组
     */
    public function getMultipleByTemplate($template, array $paramsList, $callback = null)
    {
        if (empty($paramsList)) {
            return array();
        }
        
        // 使用批量键生成函数，获取 CacheKeyCollection
        $keyCollection = KeyManager::getInstance()->createKeyCollection($template, $paramsList);
        
        // 获取 CacheKey 数组
        $cacheKeys = $keyCollection->getKeys();

        return $this->getMultiple($cacheKeys, $callback);
    }
}
