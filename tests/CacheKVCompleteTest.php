<?php

use PHPUnit\Framework\TestCase;
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\CacheKVFactory;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\KeyManager;

class CacheTemplates {
    const USER = 'user_profile';
    const PRODUCT = 'product_info';
    const ORDER = 'order_detail';
}

class CacheKVCompleteTest extends TestCase
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
                    CacheTemplates::PRODUCT => 'product:{id}',
                    CacheTemplates::ORDER => 'order:{id}:{status}',
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
        
        // 删除模板缓存
        $this->assertTrue($this->cache->deleteByTemplate(CacheTemplates::USER, ['id' => 123]));
        $this->assertNull($this->cache->getByTemplate(CacheTemplates::USER, ['id' => 123]));
    }
    
    public function testTemplateWithCallback()
    {
        $callbackExecuted = false;
        
        $result = $this->cache->getByTemplate(CacheTemplates::USER, ['id' => 456], function() use (&$callbackExecuted) {
            $callbackExecuted = true;
            return [
                'id' => 456,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com'
            ];
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertEquals(456, $result['id']);
        $this->assertEquals('Jane Smith', $result['name']);
        
        // 第二次调用应该从缓存获取，不执行回调
        $callbackExecuted = false;
        $result2 = $this->cache->getByTemplate(CacheTemplates::USER, ['id' => 456]);
        
        $this->assertFalse($callbackExecuted);
        $this->assertEquals($result, $result2);
    }
    
    public function testMultipleOperations()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        
        // 批量设置
        $this->assertTrue($this->cache->setMultiple($data));
        
        // 批量获取
        $result = $this->cache->getMultiple(array_keys($data));
        $this->assertEquals($data, $result);
        
        // 批量删除
        $this->assertTrue($this->cache->deleteMultiple(array_keys($data)));
        
        // 验证删除
        foreach (array_keys($data) as $key) {
            $this->assertNull($this->cache->get($key));
        }
    }
    
    public function testMultipleWithCallback()
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
    
    public function testKeyGeneration()
    {
        $key = $this->keyManager->make(CacheTemplates::USER, ['id' => 123]);
        $this->assertEquals('test:phpunit:v1:user_profile:123', $key);
        
        $complexKey = $this->keyManager->make(CacheTemplates::ORDER, ['id' => 456, 'status' => 'pending']);
        $this->assertEquals('test:phpunit:v1:order_detail:456:pending', $complexKey);
    }
    
    public function testHelperFunctions()
    {
        $userData = [
            'id' => 789,
            'name' => 'Helper User',
            'email' => 'helper@example.com'
        ];
        
        // 测试 cache_kv_set
        $this->assertTrue(cache_kv_set(CacheTemplates::USER, ['id' => 789], $userData));
        
        // 测试 cache_kv_get
        $result = cache_kv_get(CacheTemplates::USER, ['id' => 789]);
        $this->assertEquals($userData, $result);
        
        // 测试 cache_kv_delete
        $this->assertTrue(cache_kv_delete(CacheTemplates::USER, ['id' => 789]));
        $this->assertNull(cache_kv_get(CacheTemplates::USER, ['id' => 789]));
    }
    
    public function testHelperFunctionWithCallback()
    {
        $callbackExecuted = false;
        
        $result = cache_kv_get(CacheTemplates::USER, ['id' => 999], function() use (&$callbackExecuted) {
            $callbackExecuted = true;
            return [
                'id' => 999,
                'name' => 'Callback User',
                'email' => 'callback@example.com'
            ];
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertEquals(999, $result['id']);
        
        // 第二次调用不应该执行回调
        $callbackExecuted = false;
        $result2 = cache_kv_get(CacheTemplates::USER, ['id' => 999]);
        
        $this->assertFalse($callbackExecuted);
        $this->assertEquals($result, $result2);
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
    
    public function testLargeData()
    {
        // 测试大数据量
        $largeData = str_repeat('x', 10000); // 10KB 数据
        $key = 'large_data_test';
        
        $this->assertTrue($this->cache->set($key, $largeData));
        $this->assertEquals($largeData, $this->cache->get($key));
    }
    
    public function testBatchPerformance()
    {
        $batchSize = 100;
        $data = [];
        
        // 准备批量数据
        for ($i = 0; $i < $batchSize; $i++) {
            $data["batch_key_{$i}"] = "batch_value_{$i}";
        }
        
        $startTime = microtime(true);
        
        // 批量设置
        $this->assertTrue($this->cache->setMultiple($data));
        
        // 批量获取
        $result = $this->cache->getMultiple(array_keys($data));
        
        $endTime = microtime(true);
        
        $this->assertEquals($data, $result);
        
        // 性能应该在合理范围内
        $duration = $endTime - $startTime;
        $this->assertLessThan(1.0, $duration, "批量操作耗时过长: {$duration}秒");
    }
}
