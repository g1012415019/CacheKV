<?php

namespace Asfop\CacheKV\Key;

/**
 * 分组键构建器 - 极简版
 * 
 * 只用于链式调用：keyManager->group('user')->key('profile', ['id' => 123])
 * 兼容 PHP 7.0
 */
class GroupKeyBuilder
{
    /**
     * KeyManager 实例
     * 
     * @var KeyManager
     */
    private $keyManager;
    
    /**
     * 分组名称
     * 
     * @var string
     */
    private $groupName;

    /**
     * 构造函数
     * 
     * @param KeyManager $keyManager KeyManager 实例
     * @param string $groupName 分组名称
     */
    public function __construct($keyManager, $groupName)
    {
        $this->keyManager = $keyManager;
        $this->groupName = $groupName;
    }

    /**
     * 创建键对象
     * 
     * @param string $keyName 键名称
     * @param array $params 参数数组
     * @return CacheKey 缓存键对象
     */
    public function key($keyName, array $params = array())
    {
        return $this->keyManager->createKey($this->groupName, $keyName, $params);
    }
}
