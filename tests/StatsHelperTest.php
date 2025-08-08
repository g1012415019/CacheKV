<?php

use PHPUnit\Framework\TestCase;
use Asfop\CacheKV\Stats\StatsHelper;
use Asfop\CacheKV\Stats\KeyStats;
use Asfop\CacheKV\Key\KeyManager;

class StatsHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // 重置所有相关组件
        KeyManager::reset();
        KeyStats::reset();
        
        // 注入测试配置
        KeyManager::injectGlobalConfig([
            'key_manager' => [
                'app_prefix' => 'test',
                'separator' => ':',
                'groups' => [
                    'user' => [
                        'prefix' => 'usr',
                        'version' => 'v1',
                        'cache' => [
                            'enable_stats' => true
                        ],
                        'keys' => [
                            'profile' => [
                                'template' => 'profile:{id}',
                                'cache' => [
                                    'enable_stats' => true
                                ]
                            ],
                            'settings' => [
                                'template' => 'settings:{id}',
                                'cache' => [
                                    'enable_stats' => false
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }
    
    protected function tearDown(): void
    {
        KeyManager::reset();
        KeyStats::reset();
    }
    
    public function testRecordHitIfEnabledWithStatsEnabled()
    {
        $keyManager = KeyManager::getInstance();
        $cacheKey = $keyManager->createKey('user', 'profile', ['id' => 123]);
        
        // 模拟 KeyStats 的行为（实际测试中可能需要 mock）
        KeyStats::enable();
        
        // 这个测试主要验证方法调用不会出错
        // 在实际环境中，需要 Redis 连接来完整测试统计功能
        StatsHelper::recordHitIfEnabled($cacheKey, 'test:usr:v1:profile:123');
        
        $this->assertTrue(true); // 如果没有异常，测试通过
    }
    
    public function testRecordHitIfEnabledWithStatsDisabled()
    {
        $keyManager = KeyManager::getInstance();
        $cacheKey = $keyManager->createKey('user', 'settings', ['id' => 123]);
        
        KeyStats::enable();
        
        // settings 键配置了 enable_stats => false
        StatsHelper::recordHitIfEnabled($cacheKey, 'test:usr:v1:settings:123');
        
        $this->assertTrue(true); // 如果没有异常，测试通过
    }
    
    public function testRecordMissIfEnabled()
    {
        $keyManager = KeyManager::getInstance();
        $cacheKey = $keyManager->createKey('user', 'profile', ['id' => 123]);
        
        KeyStats::enable();
        
        StatsHelper::recordMissIfEnabled($cacheKey, 'test:usr:v1:profile:123');
        
        $this->assertTrue(true);
    }
    
    public function testRecordSetIfEnabled()
    {
        $keyManager = KeyManager::getInstance();
        $cacheKey = $keyManager->createKey('user', 'profile', ['id' => 123]);
        
        KeyStats::enable();
        
        StatsHelper::recordSetIfEnabled($cacheKey, 'test:usr:v1:profile:123');
        
        $this->assertTrue(true);
    }
    
    public function testRecordDeleteIfEnabled()
    {
        $keyManager = KeyManager::getInstance();
        $cacheKey = $keyManager->createKey('user', 'profile', ['id' => 123]);
        
        KeyStats::enable();
        
        StatsHelper::recordDeleteIfEnabled($cacheKey, 'test:usr:v1:profile:123');
        
        $this->assertTrue(true);
    }
    
    public function testWithInvalidCacheKey()
    {
        // 测试当 CacheKey 对象有问题时，StatsHelper 不会崩溃
        $mockCacheKey = $this->createMock('Asfop\CacheKV\Key\CacheKey');
        $mockCacheKey->method('getCacheConfig')
                     ->willThrowException(new \Exception('Config error'));
        
        // 这些调用应该不会抛出异常
        StatsHelper::recordHitIfEnabled($mockCacheKey, 'test:key');
        StatsHelper::recordMissIfEnabled($mockCacheKey, 'test:key');
        StatsHelper::recordSetIfEnabled($mockCacheKey, 'test:key');
        StatsHelper::recordDeleteIfEnabled($mockCacheKey, 'test:key');
        
        $this->assertTrue(true);
    }
}
