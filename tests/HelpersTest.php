<?php

use PHPUnit\Framework\TestCase;
use Asfop\CacheKV\Key\KeyManager;

class HelpersTest extends TestCase
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
    
    public function testKvKey()
    {
        $key = kv_key('user.profile', ['id' => 123]);
        $this->assertEquals('test:usr:v1:profile:123', $key);
    }
    
    public function testKvKeys()
    {
        $paramsList = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        
        $keys = kv_keys('user.profile', $paramsList);
        
        $expected = [
            'test:usr:v1:profile:1',
            'test:usr:v1:profile:2',
            'test:usr:v1:profile:3',
        ];
        
        $this->assertEquals($expected, $keys);
    }
    
    public function testKvKeysWithEmptyList()
    {
        $keys = kv_keys('user.profile', []);
        $this->assertEmpty($keys);
    }
    
    public function testKvKeysWithInvalidParams()
    {
        $paramsList = [
            ['id' => 1],
            'invalid',  // 非数组参数
            ['id' => 3],
        ];
        
        $keys = kv_keys('user.profile', $paramsList);
        
        // 应该跳过无效参数，只返回有效的键
        $expected = [
            'test:usr:v1:profile:1',
            'test:usr:v1:profile:3',
        ];
        
        $this->assertEquals($expected, $keys);
    }
    
    public function testKvGetKeys()
    {
        $paramsList = [
            ['id' => 1],
            ['id' => 2],
        ];
        
        $keyObjects = kv_get_keys('user.profile', $paramsList);
        
        $this->assertCount(2, $keyObjects);
        $this->assertArrayHasKey('test:usr:v1:profile:1', $keyObjects);
        $this->assertArrayHasKey('test:usr:v1:profile:2', $keyObjects);
        
        // 验证返回的是 CacheKey 对象
        $key1 = $keyObjects['test:usr:v1:profile:1'];
        $this->assertInstanceOf('Asfop\CacheKV\Key\CacheKey', $key1);
        $this->assertEquals('user', $key1->getGroupName());
        $this->assertEquals('profile', $key1->getKeyName());
        $this->assertEquals(['id' => 1], $key1->getParams());
    }
}
