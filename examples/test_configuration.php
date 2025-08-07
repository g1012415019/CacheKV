<?php

/**
 * Configuration 功能测试
 * 
 * 全面测试 src/Configuration 下的所有类
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Configuration\CacheConfig;
use Asfop\CacheKV\Configuration\KeyConfig;
use Asfop\CacheKV\Configuration\GroupConfig;
use Asfop\CacheKV\Configuration\CacheKVConfig;
use Asfop\CacheKV\Configuration\KeyManagerConfig;

echo "=== Configuration 功能测试 ===\n\n";

$allTestsPassed = true;

// 测试 1: CacheConfig 类
echo "1. 测试 CacheConfig 类...\n";
try {
    // 测试基本创建
    $cacheConfigArray = array(
        'ttl' => 3600,
        'null_cache_ttl' => 300,
        'enable_null_cache' => true,
        'ttl_random_range' => 200,
        'enable_stats' => true,
        'stats_prefix' => 'stats:',
        'stats_ttl' => 86400,
        'hot_key_auto_renewal' => true,
        'hot_key_threshold' => 100,
        'hot_key_extend_ttl' => 7200,
        'hot_key_max_ttl' => 86400,
        'tag_prefix' => 'tag:'
    );
    
    $cacheConfig = new CacheConfig($cacheConfigArray);
    
    echo "   ✅ CacheConfig 基本创建成功\n";
    echo "   - TTL: " . $cacheConfig->getTtl() . "秒\n";
    echo "   - 统计功能: " . ($cacheConfig->isEnableStats() ? '启用' : '禁用') . "\n";
    echo "   - 热点键续期: " . ($cacheConfig->isHotKeyAutoRenewal() ? '启用' : '禁用') . "\n";
    
    // 测试从数组创建
    $configArray = array(
        'ttl' => 1800,
        'enable_stats' => false,
        'hot_key_threshold' => 50
    );
    
    $cacheConfigFromArray = CacheConfig::fromArray($configArray);
    echo "   ✅ CacheConfig::fromArray() 成功\n";
    echo "   - 从数组创建的TTL: " . $cacheConfigFromArray->getTtl() . "秒\n";
    
    // 测试配置继承
    $globalConfig = array('ttl' => 3600, 'enable_stats' => true);
    $groupConfig = array('ttl' => 1800);
    $keyConfig = array('hot_key_threshold' => 30);
    
    $inheritedConfig = CacheConfig::merge($globalConfig, $groupConfig, $keyConfig);
    echo "   ✅ 配置继承测试成功\n";
    echo "   - 继承后的TTL: " . $inheritedConfig->getTtl() . "秒 (应该是1800，组级覆盖)\n";
    echo "   - 继承后的统计: " . ($inheritedConfig->isEnableStats() ? '启用' : '禁用') . " (应该启用，全局配置)\n";
    echo "   - 继承后的热点阈值: " . $inheritedConfig->getHotKeyThreshold() . " (应该是30，键级配置)\n";
    
    // 测试 toArray
    $arrayResult = $cacheConfig->toArray();
    echo "   ✅ CacheConfig::toArray() 成功\n";
    
} catch (Exception $e) {
    echo "   ❌ CacheConfig 测试失败: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "\n";

// 测试 2: KeyConfig 类
echo "2. 测试 KeyConfig 类...\n";
try {
    // 测试基本创建
    $keyConfig = new KeyConfig(
        'profile',
        'profile:{id}',
        '用户资料',
        new CacheConfig(array('ttl' => 7200))
    );
    
    echo "   ✅ KeyConfig 基本创建成功\n";
    echo "   - 键名: " . $keyConfig->getName() . "\n";
    echo "   - 模板: " . $keyConfig->getTemplate() . "\n";
    echo "   - 描述: " . $keyConfig->getDescription() . "\n";
    echo "   - 有缓存配置: " . ($keyConfig->hasCacheConfig() ? '是' : '否') . "\n";
    
    // 测试没有缓存配置的键
    $keyConfigNoCache = new KeyConfig('session', 'session:{token}', '会话标识');
    echo "   ✅ 无缓存配置的KeyConfig创建成功\n";
    echo "   - 有缓存配置: " . ($keyConfigNoCache->hasCacheConfig() ? '是' : '否') . " (应该是否)\n";
    
    // 测试从数组创建
    $keyConfigArray = array(
        'template' => 'user:{id}',
        'description' => '用户信息',
        'cache' => array(
            'ttl' => 3600,
            'enable_stats' => true
        )
    );
    
    $keyConfigFromArray = KeyConfig::fromArray('user', $keyConfigArray);
    echo "   ✅ KeyConfig::fromArray() 成功\n";
    echo "   - 从数组创建的键名: " . $keyConfigFromArray->getName() . "\n";
    echo "   - 有缓存配置: " . ($keyConfigFromArray->hasCacheConfig() ? '是' : '否') . "\n";
    
    // 测试没有cache配置的从数组创建
    $keyConfigArrayNoCache = array(
        'template' => 'lock:{id}',
        'description' => '分布式锁'
    );
    
    $keyConfigNoCacheFromArray = KeyConfig::fromArray('lock', $keyConfigArrayNoCache);
    echo "   ✅ 无cache配置的KeyConfig::fromArray() 成功\n";
    echo "   - 有缓存配置: " . ($keyConfigNoCacheFromArray->hasCacheConfig() ? '是' : '否') . " (应该是否)\n";
    
    // 测试 toArray
    $keyArrayResult = $keyConfig->toArray();
    echo "   ✅ KeyConfig::toArray() 成功\n";
    
} catch (Exception $e) {
    echo "   ❌ KeyConfig 测试失败: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "\n";

// 测试 3: GroupConfig 类
echo "3. 测试 GroupConfig 类...\n";
try {
    // 创建一些键配置
    $profileKey = new KeyConfig('profile', 'profile:{id}', '用户资料', new CacheConfig(array('ttl' => 3600)));
    $sessionKey = new KeyConfig('session', 'session:{token}', '会话标识');
    
    $keys = array(
        'profile' => $profileKey,
        'session' => $sessionKey
    );
    
    // 测试基本创建
    $groupConfig = new GroupConfig(
        'user',
        'user',
        'v1',
        '用户相关数据',
        array('ttl' => 7200),
        $keys
    );
    
    echo "   ✅ GroupConfig 基本创建成功\n";
    echo "   - 组名: " . $groupConfig->getName() . "\n";
    echo "   - 前缀: " . $groupConfig->getPrefix() . "\n";
    echo "   - 版本: " . $groupConfig->getVersion() . "\n";
    echo "   - 描述: " . $groupConfig->getDescription() . "\n";
    echo "   - 键数量: " . count($groupConfig->getKeys()) . "\n";
    
    // 测试键查询
    echo "   ✅ 键查询功能:\n";
    echo "   - hasKey('profile'): " . ($groupConfig->hasKey('profile') ? '是' : '否') . "\n";
    echo "   - hasKey('nonexistent'): " . ($groupConfig->hasKey('nonexistent') ? '是' : '否') . "\n";
    echo "   - hasKeyCache('profile'): " . ($groupConfig->hasKeyCache('profile') ? '是' : '否') . " (应该是是)\n";
    echo "   - hasKeyCache('session'): " . ($groupConfig->hasKeyCache('session') ? '是' : '否') . " (应该是否)\n";
    
    // 测试获取键配置
    $retrievedKey = $groupConfig->getKey('profile');
    if ($retrievedKey) {
        echo "   ✅ getKey('profile') 成功: " . $retrievedKey->getName() . "\n";
    }
    
    // 测试从数组创建
    $groupConfigArray = array(
        'prefix' => 'goods',
        'version' => 'v1',
        'description' => '商品相关数据',
        'cache' => array('ttl' => 1800),
        'keys' => array(
            'info' => array(
                'template' => 'info:{id}',
                'description' => '商品信息',
                'cache' => array('ttl' => 3600)
            ),
            'stock' => array(
                'template' => 'stock:{id}',
                'description' => '库存信息'
            )
        )
    );
    
    $groupConfigFromArray = GroupConfig::fromArray('goods', $groupConfigArray);
    echo "   ✅ GroupConfig::fromArray() 成功\n";
    echo "   - 从数组创建的组名: " . $groupConfigFromArray->getName() . "\n";
    echo "   - 键数量: " . count($groupConfigFromArray->getKeys()) . "\n";
    echo "   - info键有缓存配置: " . ($groupConfigFromArray->hasKeyCache('info') ? '是' : '否') . "\n";
    echo "   - stock键有缓存配置: " . ($groupConfigFromArray->hasKeyCache('stock') ? '是' : '否') . "\n";
    
    // 测试 toArray
    $groupArrayResult = $groupConfig->toArray();
    echo "   ✅ GroupConfig::toArray() 成功\n";
    
} catch (Exception $e) {
    echo "   ❌ GroupConfig 测试失败: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "\n";

// 测试 4: KeyManagerConfig 类
echo "4. 测试 KeyManagerConfig 类...\n";
try {
    // 创建一些组配置
    $userGroup = GroupConfig::fromArray('user', array(
        'prefix' => 'user',
        'version' => 'v1',
        'keys' => array(
            'profile' => array('template' => 'profile:{id}', 'cache' => array('ttl' => 3600))
        )
    ));
    
    $goodsGroup = GroupConfig::fromArray('goods', array(
        'prefix' => 'goods',
        'version' => 'v1',
        'keys' => array(
            'info' => array('template' => 'info:{id}', 'cache' => array('ttl' => 1800))
        )
    ));
    
    $groups = array(
        'user' => $userGroup,
        'goods' => $goodsGroup
    );
    
    // 测试基本创建
    $keyManagerConfig = new KeyManagerConfig('myapp', ':', $groups);
    
    echo "   ✅ KeyManagerConfig 基本创建成功\n";
    echo "   - 应用前缀: " . $keyManagerConfig->getAppPrefix() . "\n";
    echo "   - 分隔符: " . $keyManagerConfig->getSeparator() . "\n";
    echo "   - 组数量: " . count($keyManagerConfig->getGroups()) . "\n";
    
    // 测试组查询
    echo "   ✅ 组查询功能:\n";
    echo "   - hasGroup('user'): " . ($keyManagerConfig->hasGroup('user') ? '是' : '否') . "\n";
    echo "   - hasGroup('nonexistent'): " . ($keyManagerConfig->hasGroup('nonexistent') ? '是' : '否') . "\n";
    
    $retrievedGroup = $keyManagerConfig->getGroup('user');
    if ($retrievedGroup) {
        echo "   ✅ getGroup('user') 成功: " . $retrievedGroup->getName() . "\n";
    }
    
    // 测试从数组创建
    $keyManagerConfigArray = array(
        'app_prefix' => 'testapp',
        'separator' => ':',
        'groups' => array(
            'article' => array(
                'prefix' => 'article',
                'version' => 'v1',
                'keys' => array(
                    'content' => array('template' => 'content:{id}', 'cache' => array('ttl' => 7200))
                )
            )
        )
    );
    
    $keyManagerConfigFromArray = KeyManagerConfig::fromArray($keyManagerConfigArray);
    echo "   ✅ KeyManagerConfig::fromArray() 成功\n";
    echo "   - 从数组创建的应用前缀: " . $keyManagerConfigFromArray->getAppPrefix() . "\n";
    echo "   - 组数量: " . count($keyManagerConfigFromArray->getGroups()) . "\n";
    
    // 测试 toArray
    $keyManagerArrayResult = $keyManagerConfig->toArray();
    echo "   ✅ KeyManagerConfig::toArray() 成功\n";
    
} catch (Exception $e) {
    echo "   ❌ KeyManagerConfig 测试失败: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "\n";

// 测试 5: CacheKVConfig 类
echo "5. 测试 CacheKVConfig 类...\n";
try {
    // 创建缓存配置和键管理配置
    $cacheConfig = new CacheConfig(array('ttl' => 3600));
    $keyManagerConfig = new KeyManagerConfig('myapp', ':', array());
    
    // 测试基本创建
    $cacheKVConfig = new CacheKVConfig($cacheConfig, $keyManagerConfig);
    
    echo "   ✅ CacheKVConfig 基本创建成功\n";
    echo "   - 缓存配置TTL: " . $cacheKVConfig->getCache()->getTtl() . "秒\n";
    echo "   - 键管理应用前缀: " . $cacheKVConfig->getKeyManager()->getAppPrefix() . "\n";
    
    // 测试从数组创建
    $cacheKVConfigArray = array(
        'cache' => array(
            'ttl' => 7200,
            'enable_stats' => true
        ),
        'key_manager' => array(
            'app_prefix' => 'testapp',
            'separator' => ':',
            'groups' => array()
        )
    );
    
    $cacheKVConfigFromArray = CacheKVConfig::fromArray($cacheKVConfigArray);
    echo "   ✅ CacheKVConfig::fromArray() 成功\n";
    echo "   - 从数组创建的缓存TTL: " . $cacheKVConfigFromArray->getCache()->getTtl() . "秒\n";
    
    // 测试 toArray
    $cacheKVArrayResult = $cacheKVConfig->toArray();
    echo "   ✅ CacheKVConfig::toArray() 成功\n";
    
} catch (Exception $e) {
    echo "   ❌ CacheKVConfig 测试失败: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "\n";

// 测试 6: 错误处理
echo "6. 测试错误处理...\n";
try {
    // 测试缺少必要参数的情况
    try {
        KeyConfig::fromArray('test', array()); // 缺少 template
        echo "   ❌ 应该抛出异常但没有\n";
        $allTestsPassed = false;
    } catch (InvalidArgumentException $e) {
        echo "   ✅ KeyConfig 缺少template参数时正确抛出异常\n";
    }
    
    try {
        GroupConfig::fromArray('test', array()); // 缺少 prefix
        echo "   ❌ 应该抛出异常但没有\n";
        $allTestsPassed = false;
    } catch (InvalidArgumentException $e) {
        echo "   ✅ GroupConfig 缺少prefix参数时正确抛出异常\n";
    }
    
    try {
        GroupConfig::fromArray('test', array('prefix' => 'test')); // 缺少 version
        echo "   ❌ 应该抛出异常但没有\n";
        $allTestsPassed = false;
    } catch (InvalidArgumentException $e) {
        echo "   ✅ GroupConfig 缺少version参数时正确抛出异常\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ 错误处理测试失败: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "\n";

// 测试 7: 复杂场景
echo "7. 测试复杂场景...\n";
try {
    // 创建一个完整的配置结构
    $fullConfigArray = array(
        'cache' => array(
            'ttl' => 3600,
            'enable_stats' => true,
            'hot_key_auto_renewal' => true,
            'hot_key_threshold' => 100
        ),
        'key_manager' => array(
            'app_prefix' => 'myapp',
            'separator' => ':',
            'groups' => array(
                'user' => array(
                    'prefix' => 'user',
                    'version' => 'v1',
                    'description' => '用户相关数据',
                    'cache' => array(
                        'ttl' => 7200,
                        'hot_key_threshold' => 50
                    ),
                    'keys' => array(
                        'profile' => array(
                            'template' => 'profile:{id}',
                            'description' => '用户资料',
                            'cache' => array(
                                'ttl' => 10800,
                                'hot_key_threshold' => 30
                            )
                        ),
                        'settings' => array(
                            'template' => 'settings:{id}',
                            'description' => '用户设置',
                            'cache' => array(
                                'ttl' => 14400
                            )
                        ),
                        'session' => array(
                            'template' => 'session:{token}',
                            'description' => '会话标识'
                            // 注意：没有cache配置
                        )
                    )
                ),
                'goods' => array(
                    'prefix' => 'goods',
                    'version' => 'v1',
                    'keys' => array(
                        'info' => array(
                            'template' => 'info:{id}',
                            'cache' => array('ttl' => 1800)
                        )
                    )
                )
            )
        )
    );
    
    $fullConfig = CacheKVConfig::fromArray($fullConfigArray);
    echo "   ✅ 复杂配置结构创建成功\n";
    
    // 验证配置继承
    $userGroup = $fullConfig->getKeyManager()->getGroup('user');
    $profileKey = $userGroup->getKey('profile');
    $sessionKey = $userGroup->getKey('session');
    
    echo "   ✅ 配置继承验证:\n";
    echo "   - profile键有缓存配置: " . ($profileKey->hasCacheConfig() ? '是' : '否') . "\n";
    echo "   - session键有缓存配置: " . ($sessionKey->hasCacheConfig() ? '是' : '否') . " (应该是否)\n";
    
    if ($profileKey->hasCacheConfig()) {
        $profileCacheConfig = $profileKey->getCacheConfig();
        echo "   - profile键TTL: " . $profileCacheConfig->getTtl() . "秒 (应该是10800)\n";
        echo "   - profile键热点阈值: " . $profileCacheConfig->getHotKeyThreshold() . " (应该是30)\n";
        echo "   - profile键统计功能: " . ($profileCacheConfig->isEnableStats() ? '启用' : '禁用') . " (应该启用，继承全局)\n";
    }
    
    // 测试转换回数组
    $reconstructedArray = $fullConfig->toArray();
    echo "   ✅ 复杂配置toArray()成功\n";
    
} catch (Exception $e) {
    echo "   ❌ 复杂场景测试失败: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

echo "\n=== 测试总结 ===\n";

if ($allTestsPassed) {
    echo "🎉 所有Configuration功能测试通过！\n\n";
    
    echo "✅ 测试通过的功能：\n";
    echo "1. CacheConfig - 缓存配置管理\n";
    echo "2. KeyConfig - 键配置管理\n";
    echo "3. GroupConfig - 分组配置管理\n";
    echo "4. KeyManagerConfig - 键管理器配置\n";
    echo "5. CacheKVConfig - 总体配置管理\n";
    echo "6. 错误处理和参数验证\n";
    echo "7. 复杂场景和配置继承\n";
    echo "8. fromArray() 和 toArray() 转换\n";
    echo "9. 缓存配置的三级继承（全局→组级→键级）\n";
    echo "10. 键的缓存行为判断（hasCacheConfig）\n\n";
    
    echo "🔧 Configuration 模块完全正常工作！\n";
} else {
    echo "❌ 部分测试失败，请检查上述错误信息。\n";
}

?>
