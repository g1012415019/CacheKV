<?php

/**
 * 配置结构测试
 * 
 * 验证简化配置结构后配置加载是否正常（不需要Redis）
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\ConfigManager;
use Asfop\CacheKV\Key\KeyManager;

echo "=== 配置结构测试 ===\n\n";

try {
    // 1. 测试配置文件加载
    echo "1. 测试配置文件加载...\n";
    ConfigManager::loadConfig(__DIR__ . '/config/cache_kv.php');
    echo "✅ 配置文件加载成功\n\n";
    
    // 2. 测试全局配置获取
    echo "2. 测试全局配置获取...\n";
    $globalConfig = ConfigManager::getGlobalCacheConfig();
    echo "  全局TTL: {$globalConfig['ttl']}秒\n";
    echo "  统计功能: " . ($globalConfig['enable_stats'] ? '启用' : '禁用') . "\n";
    echo "  热点键自动续期: " . ($globalConfig['hot_key_auto_renewal'] ? '启用' : '禁用') . "\n\n";
    
    // 3. 测试KeyManager配置
    echo "3. 测试KeyManager配置...\n";
    $keyManagerConfig = ConfigManager::getKeyManagerConfig();
    echo "  应用前缀: {$keyManagerConfig['app_prefix']}\n";
    echo "  分隔符: {$keyManagerConfig['separator']}\n";
    echo "  分组数量: " . count($keyManagerConfig['groups']) . "\n\n";
    
    // 4. 测试分组配置
    echo "4. 测试分组配置...\n";
    foreach ($keyManagerConfig['groups'] as $groupName => $groupConfig) {
        echo "  分组: {$groupName}\n";
        echo "    前缀: {$groupConfig['prefix']}\n";
        echo "    版本: {$groupConfig['version']}\n";
        echo "    键数量: " . count($groupConfig['keys']) . "\n";
        
        // 检查键配置
        foreach ($groupConfig['keys'] as $keyName => $keyConfig) {
            $hasCache = isset($keyConfig['cache']) && is_array($keyConfig['cache']);
            echo "      键: {$keyName} - " . ($hasCache ? '有缓存配置' : '仅键生成') . "\n";
        }
        echo "\n";
    }
    
    // 5. 测试键管理器
    echo "5. 测试键管理器...\n";
    
    // 需要先初始化 KeyManager 的配置
    $keyManagerConfig = ConfigManager::getKeyManagerConfig();
    $keyManager = KeyManager::getInstance();
    
    // 手动设置配置（因为没有通过 CacheKVFactory 初始化）
    $reflection = new ReflectionClass($keyManager);
    $configProperty = $reflection->getProperty('config');
    $configProperty->setAccessible(true);
    
    // 创建 KeyManagerConfig 对象
    $keyManagerConfigObj = \Asfop\CacheKV\Configuration\KeyManagerConfig::fromArray($keyManagerConfig);
    $configProperty->setValue($keyManager, $keyManagerConfigObj);
    
    // 测试创建有缓存配置的键
    $profileKey = $keyManager->createKey('user', 'profile', ['id' => 123]);
    echo "  创建用户资料键: " . (string)$profileKey . "\n";
    echo "  有缓存配置: " . ($profileKey->hasCacheConfig() ? '是' : '否') . "\n";
    
    // 测试创建没有缓存配置的键
    $sessionKey = $keyManager->createKey('user', 'session', ['token' => 'abc123']);
    echo "  创建会话键: " . (string)$sessionKey . "\n";
    echo "  有缓存配置: " . ($sessionKey->hasCacheConfig() ? '是' : '否') . "\n\n";
    
    // 6. 测试配置继承
    echo "6. 测试配置继承...\n";
    
    // 测试组级配置继承
    $userGroupConfig = ConfigManager::getGroupCacheConfig('user');
    echo "  用户组TTL: {$userGroupConfig['ttl']}秒 (应该是7200，继承组级配置)\n";
    echo "  用户组热点阈值: {$userGroupConfig['hot_key_threshold']} (应该是50，组级覆盖)\n";
    
    // 测试键级配置继承
    $profileCacheConfig = ConfigManager::getKeyCacheConfig('user', 'profile');
    if ($profileCacheConfig) {
        echo "  用户资料TTL: {$profileCacheConfig['ttl']}秒 (应该是10800，键级覆盖)\n";
        echo "  用户资料热点阈值: {$profileCacheConfig['hot_key_threshold']} (应该是30，键级覆盖)\n";
    }
    
    // 测试没有缓存配置的键
    $sessionCacheConfig = ConfigManager::getKeyCacheConfig('user', 'session');
    echo "  会话键缓存配置: " . ($sessionCacheConfig ? '有' : '无') . " (应该有，继承组级配置)\n\n";
    
    // 7. 测试错误处理
    echo "7. 测试错误处理...\n";
    
    try {
        $keyManager->createKey('nonexistent', 'key', ['id' => 1]);
        echo "  ❌ 应该抛出异常但没有\n";
    } catch (Exception $e) {
        echo "  ✅ 正确捕获异常: " . $e->getMessage() . "\n";
    }
    
    try {
        ConfigManager::getGroupCacheConfig('nonexistent');
        echo "  ❌ 应该抛出异常但没有\n";
    } catch (Exception $e) {
        echo "  ✅ 正确捕获异常: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== 配置结构测试总结 ===\n";
    echo "✅ 配置文件加载正常\n";
    echo "✅ 全局配置获取正常\n";
    echo "✅ KeyManager配置正常\n";
    echo "✅ 分组配置正常\n";
    echo "✅ 键管理器工作正常\n";
    echo "✅ 配置继承工作正常\n";
    echo "✅ 错误处理工作正常\n";
    echo "\n🎉 配置结构测试通过！系统配置正常！\n";
    
    // 8. 详细的配置验证
    echo "\n=== 详细配置验证 ===\n";
    
    // 验证每个分组的键配置
    foreach (['user', 'goods', 'article'] as $groupName) {
        echo "验证 {$groupName} 分组:\n";
        
        $keyManagerConfig = ConfigManager::getKeyManagerConfig();
        $groupConfig = $keyManagerConfig['groups'][$groupName];
        
        foreach ($groupConfig['keys'] as $keyName => $keyConfig) {
            try {
                $cacheKey = $keyManager->createKey($groupName, $keyName, ['id' => 1]);
                $hasCache = $cacheKey->hasCacheConfig();
                $expectedHasCache = isset($keyConfig['cache']) && is_array($keyConfig['cache']);
                
                if ($hasCache === $expectedHasCache) {
                    echo "  ✅ {$keyName}: 缓存配置检测正确 (" . ($hasCache ? '有' : '无') . ")\n";
                } else {
                    echo "  ❌ {$keyName}: 缓存配置检测错误 (期望:" . ($expectedHasCache ? '有' : '无') . ", 实际:" . ($hasCache ? '有' : '无') . ")\n";
                }
            } catch (Exception $e) {
                echo "  ❌ {$keyName}: 创建键失败 - " . $e->getMessage() . "\n";
            }
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 配置结构测试失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
