<?php

/**
 * 键管理 vs 缓存逻辑分离示例
 * 
 * 演示键管理和缓存逻辑的分离设计
 */

require_once '../vendor/autoload.php';

use Asfop\CacheKV\Core\ConfigManager;
use Asfop\CacheKV\Key\KeyManager;

echo "=== 键管理 vs 缓存逻辑分离示例 ===\n\n";

try {
    // 加载配置
    ConfigManager::loadConfig(__DIR__ . '/config/cache_kv.php');
    
    // 初始化KeyManager
    $keyManagerConfig = ConfigManager::getKeyManagerConfig();
    $keyManager = KeyManager::getInstance();
    
    $reflection = new ReflectionClass($keyManager);
    $configProperty = $reflection->getProperty('config');
    $configProperty->setAccessible(true);
    $keyManagerConfigObj = \Asfop\CacheKV\Configuration\KeyManagerConfig::fromArray($keyManagerConfig);
    $configProperty->setValue($keyManager, $keyManagerConfigObj);
    
    echo "=== 1. 有明确缓存配置的键 ===\n";
    
    $profileKey = $keyManager->createKey('user', 'profile', ['id' => 123]);
    $profileConfig = ConfigManager::getKeyCacheConfig('user', 'profile');
    
    echo "键: " . (string)$profileKey . "\n";
    echo "用途: 用户资料缓存\n";
    echo "键管理: ✅ 正常创建和管理\n";
    echo "CacheKV缓存逻辑: ✅ 应用 (hasCacheConfig = " . ($profileKey->hasCacheConfig() ? 'true' : 'false') . ")\n";
    echo "配置获取: ✅ 返回键级配置 (TTL = {$profileConfig['ttl']}秒)\n";
    echo "行为: cache_kv_get() 会自动回填缓存、统计、热点续期等\n\n";
    
    echo "=== 2. 没有明确缓存配置的键 ===\n";
    
    $sessionKey = $keyManager->createKey('user', 'session', ['token' => 'abc123def456']);
    $sessionConfig = ConfigManager::getKeyCacheConfig('user', 'session');
    
    echo "键: " . (string)$sessionKey . "\n";
    echo "用途: 会话标识、分布式锁、计数器等\n";
    echo "键管理: ✅ 正常创建和管理\n";
    echo "CacheKV缓存逻辑: ❌ 不应用 (hasCacheConfig = " . ($sessionKey->hasCacheConfig() ? 'true' : 'false') . ")\n";
    echo "配置获取: ✅ 返回继承配置 (TTL = {$sessionConfig['ttl']}秒)\n";
    echo "行为: 可用于其他Redis操作，但不会触发cache_kv_get()的自动逻辑\n\n";
    
    echo "=== 3. 实际使用场景对比 ===\n\n";
    
    echo "场景1: 用户资料缓存 (有明确缓存配置)\n";
    echo "```php\n";
    echo "// 这会触发CacheKV的完整缓存逻辑\n";
    echo "\$user = cache_kv_get('user.profile', ['id' => 123], function() {\n";
    echo "    return getUserFromDatabase(123);\n";
    echo "});\n";
    echo "// 自动处理：缓存未命中检测、数据回填、统计记录、热点续期\n";
    echo "```\n\n";
    
    echo "场景2: 会话管理 (没有明确缓存配置)\n";
    echo "```php\n";
    echo "// 仅用于键生成，不触发CacheKV缓存逻辑\n";
    echo "\$sessionKey = cache_kv_make_key('user.session', ['token' => 'abc123']);\n";
    echo "\$redis->set((string)\$sessionKey, \$sessionData, \$sessionConfig['ttl']);\n";
    echo "// 手动Redis操作，使用继承的配置，但不会有自动回填等逻辑\n";
    echo "```\n\n";
    
    echo "场景3: 分布式锁 (没有明确缓存配置)\n";
    echo "```php\n";
    echo "\$lockKey = cache_kv_make_key('user.lock', ['id' => 123, 'action' => 'update']);\n";
    echo "\$lockConfig = ConfigManager::getKeyCacheConfig('user', 'lock');\n";
    echo "\$acquired = \$redis->set((string)\$lockKey, 'locked', 'NX', 'EX', 30);\n";
    echo "// 使用统一的键管理，但完全自定义Redis操作\n";
    echo "```\n\n";
    
    echo "=== 4. 设计优势 ===\n\n";
    
    echo "✅ 职责分离:\n";
    echo "   - 键管理: 统一的键生成、命名规范、版本管理\n";
    echo "   - 缓存逻辑: 自动回填、统计、热点续期等高级功能\n\n";
    
    echo "✅ 灵活性:\n";
    echo "   - 有缓存配置的键: 享受CacheKV的全部自动化功能\n";
    echo "   - 无缓存配置的键: 仅键管理，可自定义Redis操作\n\n";
    
    echo "✅ 配置继承:\n";
    echo "   - 所有键都能获取配置信息供其他用途使用\n";
    echo "   - 保持配置的一致性和可管理性\n\n";
    
    echo "✅ 向后兼容:\n";
    echo "   - API使用方式不变\n";
    echo "   - 现有代码无需修改\n\n";
    
    echo "=== 5. 配置示例 ===\n\n";
    
    echo "```php\n";
    echo "'keys' => array(\n";
    echo "    'profile' => array(\n";
    echo "        'template' => 'profile:{id}',\n";
    echo "        'cache' => array('ttl' => 10800),  // 有cache配置 -> CacheKV缓存逻辑\n";
    echo "    ),\n";
    echo "    'session' => array(\n";
    echo "        'template' => 'session:{token}',   // 无cache配置 -> 仅键管理\n";
    echo "    ),\n";
    echo "),\n";
    echo "```\n\n";
    
    echo "🎉 这种设计既保持了键管理的统一性，又提供了缓存逻辑的灵活性！\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
