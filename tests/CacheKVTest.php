<?php

use PHPUnit\Framework\TestCase;
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\CacheTemplates;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

class CacheKVTest extends TestCase
{
    private $cache;
    private $keyManager;
    
    protected function setUp(): void
    {
        // 设置测试配置
        CacheKVFactory::setDefaultConfig([
            'default' => 'array',
            'stores' => [
                'array' => [
                    'driver' => new ArrayDriver(),
                    'ttl' => 3600
                ]
            ],
            'key_manager' => [
                'app_prefix' => 'test',
                'env_prefix' => 'phpunit',
                'version' => 'v1',
                'templates' => [
                    CacheTemplates::USER => 'user:{id}',
                    CacheTemplates::POST => 'post:{id}',
                    CacheTemplates::SESSION => 'session:{id}',
                ]
            ]
        ]);
        
        $this->cache = CacheKVFactory::store();
        $this->keyManager = CacheKVFactory::getKeyManager();
        
        // 清空缓存
        $this->cache->flush();
    }
    
    protected function tearDown(): void
    {
        $this->cache->flush();
    }
    
    // ========== 基础缓存操作测试 ==========
    
    public function testBasicSetAndGet()
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->assertTrue($this->cache->set($key, $value));
        $this->assertEquals($value, $this->cache->get($key));
        $this->assertTrue($this->cache->has($key));
    }
    
    public function testGetNonExistentKey()
    {
        $this->assertNull($this->cache->get('non_existent_key'));
        $this->assertFalse($this->cache->has('non_existent_key'));
    }
    
    public function testDelete()
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));
        
        $this->assertTrue($this->cache->delete($key));
        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }
    
    public function testForgetAlias()
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->forget($key));
        $this->assertFalse($this->cache->has($key));
    }
    
    // ========== 自动回填缓存测试 ==========
    
    public function testGetWithCallback()
    {
        $key = 'callback_test';
        $expectedValue = 'callback_result';
        $callbackExecuted = false;
        
        $result = $this->cache->get($key, function() use (&$callbackExecuted, $expectedValue) {
            $callbackExecuted = true;
            return $expectedValue;
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertEquals($expectedValue, $result);
        
        // 第二次调用应该从缓存获取，不执行回调
        $callbackExecuted = false;
        $result2 = $this->cache->get($key);
        
        $this->assertFalse($callbackExecuted);
        $this->assertEquals($expectedValue, $result2);
    }
    
    public function testGetWithCallbackReturningNull()
    {
        $key = 'null_callback_test';
        $callbackExecuted = false;
        
        $result = $this->cache->get($key, function() use (&$callbackExecuted) {
            $callbackExecuted = true;
            return null;
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertNull($result);
        
        // 验证 null 值也被缓存了
        $this->assertTrue($this->cache->has($key));
    }
    
    // ========== 模板操作测试 ==========
    
    public function testTemplateOperations()
    {
        $userData = [
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];
        
        // 设置模板缓存
        $this->assertTrue($this->cache->setByTemplate(CacheTemplates::USER, ['id' => 123], $userData));
        
        // 获取模板缓存
        $result = $this->cache->getByTemplate(CacheTemplates::USER, ['id' => 123]);
        $this->assertEquals($userData, $result);
        
        // 检查模板缓存是否存在
        $this->assertTrue($this->cache->hasByTemplate(CacheTemplates::USER, ['id' => 123]));
        
        // 删除模板缓存
        $this->assertTrue($this->cache->deleteByTemplate(CacheTemplates::USER, ['id' => 123]));
        $this->assertNull($this->cache->getByTemplate(CacheTemplates::USER, ['id' => 123]));
        $this->assertFalse($this->cache->hasByTemplate(CacheTemplates::USER, ['id' => 123]));
    }
    
    public function testTemplateWithCallback()
    {
        $callbackExecuted = false;
        $userData = [
            'id' => 456,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com'
        ];
        
        $result = $this->cache->getByTemplate(CacheTemplates::USER, ['id' => 456], function() use (&$callbackExecuted, $userData) {
            $callbackExecuted = true;
            return $userData;
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertEquals($userData, $result);
        
        // 第二次调用应该从缓存获取
        $callbackExecuted = false;
        $result2 = $this->cache->getByTemplate(CacheTemplates::USER, ['id' => 456]);
        
        $this->assertFalse($callbackExecuted);
        $this->assertEquals($userData, $result2);
    }
    
    public function testForgetByTemplate()
    {
        $this->cache->setByTemplate(CacheTemplates::USER, ['id' => 789], ['name' => 'Test User']);
        $this->assertTrue($this->cache->hasByTemplate(CacheTemplates::USER, ['id' => 789]));
        
        $this->assertTrue($this->cache->forgetByTemplate(CacheTemplates::USER, ['id' => 789]));
        $this->assertFalse($this->cache->hasByTemplate(CacheTemplates::USER, ['id' => 789]));
    }
    
    // ========== 批量操作测试 ==========
    
    public function testSetMultiple()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        
        $this->assertTrue($this->cache->setMultiple($data));
        
        foreach ($data as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $this->cache->get($key));
        }
    }
    
    public function testGetMultiple()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        
        // 预设一些缓存
        $this->cache->setMultiple($data);
        
        $keys = array_keys($data);
        $result = $this->cache->getMultiple($keys);
        
        $this->assertEquals($data, $result);
    }
    
    public function testGetMultipleWithCallback()
    {
        $keys = ['user:1', 'user:2', 'user:3'];
        $callbackExecuted = false;
        
        // 预设一个缓存
        $this->cache->set('user:1', ['id' => 1, 'name' => 'User 1']);
        
        $result = $this->cache->getMultiple($keys, function($missingKeys) use (&$callbackExecuted) {
            $callbackExecuted = true;
            $this->assertEquals(['user:2', 'user:3'], $missingKeys);
            
            return [
                'user:2' => ['id' => 2, 'name' => 'User 2'],
                'user:3' => ['id' => 3, 'name' => 'User 3'],
            ];
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertCount(3, $result);
        $this->assertEquals(['id' => 1, 'name' => 'User 1'], $result['user:1']);
        $this->assertEquals(['id' => 2, 'name' => 'User 2'], $result['user:2']);
        $this->assertEquals(['id' => 3, 'name' => 'User 3'], $result['user:3']);
    }
    
    public function testDeleteMultiple()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        
        $this->cache->setMultiple($data);
        $this->assertTrue($this->cache->deleteMultiple(array_keys($data)));
        
        foreach (array_keys($data) as $key) {
            $this->assertNull($this->cache->get($key));
        }
    }
    
    // ========== 标签管理测试 ==========
    
    public function testSetWithTag()
    {
        $key = 'tagged_key';
        $value = 'tagged_value';
        $tags = ['tag1', 'tag2'];
        
        $this->assertTrue($this->cache->setWithTag($key, $value, $tags));
        $this->assertEquals($value, $this->cache->get($key));
    }
    
    public function testSetByTemplateWithTag()
    {
        $userData = ['id' => 123, 'name' => 'Tagged User'];
        $tags = ['users', 'active_users'];
        
        $this->assertTrue($this->cache->setByTemplateWithTag(
            CacheTemplates::USER, 
            ['id' => 123], 
            $userData, 
            $tags
        ));
        
        $result = $this->cache->getByTemplate(CacheTemplates::USER, ['id' => 123]);
        $this->assertEquals($userData, $result);
    }
    
    public function testClearTag()
    {
        // 设置多个带标签的缓存项
        $this->cache->setWithTag('user:1', ['name' => 'User 1'], ['users']);
        $this->cache->setWithTag('user:2', ['name' => 'User 2'], ['users']);
        $this->cache->setWithTag('admin:1', ['name' => 'Admin 1'], ['admins']);
        
        // 验证缓存存在
        $this->assertTrue($this->cache->has('user:1'));
        $this->assertTrue($this->cache->has('user:2'));
        $this->assertTrue($this->cache->has('admin:1'));
        
        // 清除 users 标签
        $this->cache->clearTag('users');
        
        // 验证 users 标签的缓存被清除，admins 标签的缓存仍然存在
        $this->assertFalse($this->cache->has('user:1'));
        $this->assertFalse($this->cache->has('user:2'));
        $this->assertTrue($this->cache->has('admin:1'));
    }
    
    // ========== 键管理测试 ==========
    
    public function testKeyGeneration()
    {
        $key = $this->keyManager->make(CacheTemplates::USER, ['id' => 123]);
        $this->assertEquals('test:phpunit:v1:user:123', $key);
        
        $sessionKey = $this->keyManager->make(CacheTemplates::SESSION, ['id' => 'abc123']);
        $this->assertEquals('test:phpunit:v1:session:abc123', $sessionKey);
    }
    
    public function testMakeKey()
    {
        $key = $this->cache->makeKey(CacheTemplates::USER, ['id' => 456]);
        $this->assertEquals('test:phpunit:v1:user:456', $key);
        
        $keyWithoutPrefix = $this->cache->makeKey(CacheTemplates::USER, ['id' => 456], false);
        $this->assertEquals('user:456', $keyWithoutPrefix);
    }
    
    // ========== TTL 测试 ==========
    
    public function testDefaultTtl()
    {
        $this->assertEquals(3600, $this->cache->getDefaultTtl());
        
        $this->cache->setDefaultTtl(7200);
        $this->assertEquals(7200, $this->cache->getDefaultTtl());
    }
    
    public function testCustomTtl()
    {
        $key = 'ttl_test';
        $value = 'ttl_value';
        
        // 设置自定义 TTL
        $this->assertTrue($this->cache->set($key, $value, 1800));
        $this->assertEquals($value, $this->cache->get($key));
    }
    
    // ========== 工具方法测试 ==========
    
    public function testKeys()
    {
        $this->cache->set('test:key1', 'value1');
        $this->cache->set('test:key2', 'value2');
        $this->cache->set('other:key3', 'value3');
        
        $keys = $this->cache->keys('test:*');
        $this->assertContains('test:key1', $keys);
        $this->assertContains('test:key2', $keys);
        $this->assertNotContains('other:key3', $keys);
    }
    
    public function testFlush()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        
        $this->assertTrue($this->cache->flush());
        
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }
    
    public function testGetStats()
    {
        $stats = $this->cache->getStats();
        $this->assertIsArray($stats);
    }
    
    // ========== 异常处理测试 ==========
    
    public function testTemplateWithoutKeyManager()
    {
        $driver = new ArrayDriver();
        $cache = new CacheKV($driver, 3600);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('KeyManager not set');
        
        $cache->getByTemplate(CacheTemplates::USER, ['id' => 123]);
    }
    
    public function testInvalidTemplate()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template \'invalid_template\' not found');
        
        $this->keyManager->make('invalid_template', ['id' => 123]);
    }
    
    // ========== 边界条件测试 ==========
    
    public function testEmptyKey()
    {
        $this->assertFalse($this->cache->set('', 'value'));
        $this->assertFalse($this->cache->delete(''));
        $this->assertFalse($this->cache->has(''));
    }
    
    public function testEmptyValues()
    {
        $this->assertFalse($this->cache->setMultiple([]));
        $this->assertEquals([], $this->cache->getMultiple([]));
        $this->assertFalse($this->cache->deleteMultiple([]));
    }
    
    public function testComplexDataTypes()
    {
        $complexData = [
            'string' => 'test string',
            'integer' => 123,
            'float' => 45.67,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => [
                'level1' => [
                    'level2' => 'deep value'
                ]
            ],
            'object' => (object) ['prop' => 'value']
        ];
        
        $this->cache->set('complex_data', $complexData);
        $result = $this->cache->get('complex_data');
        
        $this->assertEquals($complexData, $result);
    }
}
