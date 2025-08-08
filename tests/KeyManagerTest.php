<?php

use PHPUnit\Framework\TestCase;
use Asfop\CacheKV\Key\KeyManager;
use Asfop\CacheKV\Exception\CacheException;

class KeyManagerTest extends TestCase
{
    protected function setUp(): void
    {
        // 重置 KeyManager 实例
        KeyManager::reset();
        
        // 注入测试配置
        KeyManager::injectGlobalConfig([
            'key_manager' => [
                'app_prefix' => 'test',
                'separator' => ':',
                'groups' => [
                    'user' => [
                        'prefix' => 'usr',
                        'version' => 'v1',
                        'keys' => [
                            'profile' => ['template' => 'profile:{id}'],
                            'settings' => ['template' => 'settings:{id}:{type}'],
                        ]
                    ],
                    'product' => [
                        'prefix' => 'prod',
                        'version' => 'v2',
                        'keys' => [
                            'info' => ['template' => 'info:{id}'],
                            'price' => ['template' => 'price:{id}:{currency}'],
                        ]
                    ]
                ]
            ]
        ]);
    }
    
    protected function tearDown(): void
    {
        KeyManager::reset();
    }
    
    public function testSingletonPattern()
    {
        $instance1 = KeyManager::getInstance();
        $instance2 = KeyManager::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }
    
    public function testMakeKey()
    {
        $keyManager = KeyManager::getInstance();
        
        // 测试基本键生成
        $key = $keyManager->makeKey('user', 'profile', ['id' => 123]);
        $this->assertEquals('test:usr:v1:profile:123', $key);
        
        // 测试多参数键生成
        $key = $keyManager->makeKey('user', 'settings', ['id' => 456, 'type' => 'email']);
        $this->assertEquals('test:usr:v1:settings:456:email', $key);
        
        // 测试不同分组
        $key = $keyManager->makeKey('product', 'info', ['id' => 789]);
        $this->assertEquals('test:prod:v2:info:789', $key);
    }
    
    public function testMakeKeyWithInvalidGroup()
    {
        $keyManager = KeyManager::getInstance();
        
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage("Group 'invalid' not found");
        
        $keyManager->makeKey('invalid', 'profile', ['id' => 123]);
    }
    
    public function testMakeKeyWithInvalidKey()
    {
        $keyManager = KeyManager::getInstance();
        
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage("Key 'invalid' not found in group 'user'");
        
        $keyManager->makeKey('user', 'invalid', ['id' => 123]);
    }
    
    public function testMakeKeyWithMissingParams()
    {
        $keyManager = KeyManager::getInstance();
        
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage("Missing parameter for placeholder: {id}");
        
        $keyManager->makeKey('user', 'profile', []);
    }
    
    public function testCreateKey()
    {
        $keyManager = KeyManager::getInstance();
        
        $cacheKey = $keyManager->createKey('user', 'profile', ['id' => 123]);
        
        $this->assertEquals('user', $cacheKey->getGroupName());
        $this->assertEquals('profile', $cacheKey->getKeyName());
        $this->assertEquals(['id' => 123], $cacheKey->getParams());
        $this->assertEquals('test:usr:v1:profile:123', (string)$cacheKey);
    }
    
    public function testCreateKeyFromTemplate()
    {
        $keyManager = KeyManager::getInstance();
        
        $cacheKey = $keyManager->createKeyFromTemplate('user.profile', ['id' => 123]);
        
        $this->assertEquals('user', $cacheKey->getGroupName());
        $this->assertEquals('profile', $cacheKey->getKeyName());
        $this->assertEquals(['id' => 123], $cacheKey->getParams());
        $this->assertEquals('test:usr:v1:profile:123', (string)$cacheKey);
    }
    
    public function testCreateKeyFromTemplateWithInvalidFormat()
    {
        $keyManager = KeyManager::getInstance();
        
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage("Invalid template format: 'invalid'. Expected 'group.key'");
        
        $keyManager->createKeyFromTemplate('invalid', ['id' => 123]);
    }
    
    public function testGetKeys()
    {
        $keyManager = KeyManager::getInstance();
        
        $paramsList = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        
        $keys = $keyManager->getKeys('user.profile', $paramsList);
        
        $this->assertCount(3, $keys);
        $this->assertArrayHasKey('test:usr:v1:profile:1', $keys);
        $this->assertArrayHasKey('test:usr:v1:profile:2', $keys);
        $this->assertArrayHasKey('test:usr:v1:profile:3', $keys);
        
        // 验证键对象
        $key1 = $keys['test:usr:v1:profile:1'];
        $this->assertEquals('user', $key1->getGroupName());
        $this->assertEquals('profile', $key1->getKeyName());
        $this->assertEquals(['id' => 1], $key1->getParams());
    }
    
    public function testGetKeysWithEmptyList()
    {
        $keyManager = KeyManager::getInstance();
        
        $keys = $keyManager->getKeys('user.profile', []);
        
        $this->assertEmpty($keys);
    }
    
    public function testGetKeysWithInvalidParams()
    {
        $keyManager = KeyManager::getInstance();
        
        $paramsList = [
            ['id' => 1],
            'invalid',  // 非数组参数
            ['id' => 3],
        ];
        
        $keys = $keyManager->getKeys('user.profile', $paramsList);
        
        // 应该跳过无效参数，只返回有效的键
        $this->assertCount(2, $keys);
        $this->assertArrayHasKey('test:usr:v1:profile:1', $keys);
        $this->assertArrayHasKey('test:usr:v1:profile:3', $keys);
    }
    
    public function testCreateKeyCollection()
    {
        $keyManager = KeyManager::getInstance();
        
        $paramsList = [
            ['id' => 1],
            ['id' => 2],
        ];
        
        $collection = $keyManager->createKeyCollection('user.profile', $paramsList);
        
        $this->assertInstanceOf('Asfop\CacheKV\Key\CacheKeyCollection', $collection);
        
        $keys = $collection->getKeys();
        $this->assertCount(2, $keys);
    }
    
    public function testCreateKeyCollectionWithEmptyList()
    {
        $keyManager = KeyManager::getInstance();
        
        $collection = $keyManager->createKeyCollection('user.profile', []);
        
        $this->assertInstanceOf('Asfop\CacheKV\Key\CacheKeyCollection', $collection);
        
        $keys = $collection->getKeys();
        $this->assertEmpty($keys);
    }
    
    public function testGetAllKeysConfig()
    {
        $keyManager = KeyManager::getInstance();
        
        $config = $keyManager->getAllKeysConfig();
        
        $this->assertInstanceOf('Asfop\CacheKV\Configuration\CacheKVConfig', $config);
        
        // 验证配置内容
        $configArray = $config->toArray();
        $this->assertArrayHasKey('key_manager', $configArray);
        $this->assertArrayHasKey('cache', $configArray);
        
        $keyManagerConfig = $configArray['key_manager'];
        $this->assertEquals('test', $keyManagerConfig['app_prefix']);
        $this->assertEquals(':', $keyManagerConfig['separator']);
        $this->assertArrayHasKey('groups', $keyManagerConfig);
    }
    
    public function testGroupBuilder()
    {
        $keyManager = KeyManager::getInstance();
        
        $groupBuilder = $keyManager->group('user');
        
        $this->assertInstanceOf('Asfop\CacheKV\Key\GroupKeyBuilder', $groupBuilder);
    }
    
    public function testConfigurationInjection()
    {
        // 测试配置注入后实例重置
        $instance1 = KeyManager::getInstance();
        
        KeyManager::injectGlobalConfig([
            'key_manager' => [
                'app_prefix' => 'new_test',
                'separator' => '|',
                'groups' => []
            ]
        ]);
        
        $instance2 = KeyManager::getInstance();
        
        // 实例应该被重置
        $this->assertNotSame($instance1, $instance2);
    }
}
