<?php

use PHPUnit\Framework\TestCase;
use Asfop\CacheKV\Core\CacheKV;
use Asfop\CacheKV\Core\CacheKVFactory;
use Asfop\CacheKV\Drivers\ArrayDriver;
use Asfop\CacheKV\Key\KeyManager;

class CacheKVTest extends TestCase
{
    private $cache;
    private $keyManager;
    
    // 测试用的模板常量（仅用于测试）
    const TEST_TEMPLATE_A = 'template.template_a';
    const TEST_TEMPLATE_B = 'template.template_b';
    const TEST_TEMPLATE_C = 'template.template_c';
    
    protected function setUp(): void
    {
        // 重置 KeyManager 实例
        KeyManager::reset();
        
        // 注入全局配置
        KeyManager::injectGlobalConfig([
            'key_manager' => [
                'app_prefix' => 'test',
                'separator' => ':',
                'groups' => [
                    'template' => [
                        'prefix' => 'tmpl',
                        'version' => 'v1',
                        'keys' => [
                            'template_a' => ['template' => 'tmpl_a:{id}'],
                            'template_b' => ['template' => 'tmpl_b:{id}'],
                            'template_c' => ['template' => 'tmpl_c:{id}'],
                        ]
                    ]
                ]
            ]
        ]);
        
        // 获取 KeyManager 实例
        $this->keyManager = KeyManager::getInstance();
        
        // 简化测试，不创建完整的CacheKV实例
        $this->cache = null;
    }
    
    protected function tearDown(): void
    {
        // 重置 KeyManager
        KeyManager::reset();
    }
    
    // ========== 工厂方法测试 ==========
    
    public function testFactoryCreate()
    {
        $driver = new ArrayDriver();
        $keyManager = new KeyManager(['app_prefix' => 'test']);
        
        $cache = CacheKVFactory::create($driver, 1800, $keyManager);
        
        $this->assertInstanceOf(CacheKV::class, $cache);
        $this->assertEquals(1800, $cache->getDefaultTtl());
        $this->assertSame($keyManager, $cache->getKeyManager());
    }
    
    public function testFactoryCreateFromConfig()
    {
        $config = [
            'driver' => new ArrayDriver(),
            'ttl' => 7200,
            'app_prefix' => 'config_test',
            'env_prefix' => 'test',
            'version' => 'v2',
            'templates' => [
                'test_template' => 'test:{id}'
            ]
        ];
        
        $cache = CacheKVFactory::createFromConfig($config);
        
        $this->assertInstanceOf(CacheKV::class, $cache);
        $this->assertEquals(7200, $cache->getDefaultTtl());
        
        $keyManager = $cache->getKeyManager();
        $this->assertInstanceOf(KeyManager::class, $keyManager);
        $this->assertEquals('config_test:test:v2:test:123', $keyManager->make('test_template', ['id' => 123]));
    }
    
    public function testFactoryQuick()
    {
        $cache = CacheKVFactory::quick([
            'template_x' => 'tmpl_x:{id}',
            'template_y' => 'tmpl_y:{id}'
        ], [
            'app_prefix' => 'quick_test',
            'env_prefix' => 'test',
            'ttl' => 1200
        ]);
        
        $this->assertInstanceOf(CacheKV::class, $cache);
        $this->assertEquals(1200, $cache->getDefaultTtl());
        
        $keyManager = $cache->getKeyManager();
        $this->assertEquals('quick_test:test:v1:tmpl_x:123', $keyManager->make('template_x', ['id' => 123]));
    }
    
    public function testFactoryGetInstance()
    {
        // 测试单例模式
        $instance1 = CacheKVFactory::getInstance();
        $instance2 = CacheKVFactory::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(CacheKV::class, $instance1);
    }
    
    public function testFactorySetDefaultConfig()
    {
        CacheKVFactory::setDefaultConfig([
            'app_prefix' => 'custom_app',
            'env_prefix' => 'custom_env',
            'templates' => [
                'custom_template' => 'custom:{id}'
            ]
        ]);
        
        $instance = CacheKVFactory::getInstance();
        $keyManager = $instance->getKeyManager();
        
        $this->assertEquals('custom_app:custom_env:v1:custom:123', $keyManager->make('custom_template', ['id' => 123]));
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
        $testData = [
            'id' => 123,
            'name' => 'Test Data',
            'value' => 'test_value'
        ];
        
        // 设置模板缓存
        $this->assertTrue($this->cache->setByTemplate(self::TEST_TEMPLATE_A, ['id' => 123], $testData));
        
        // 获取模板缓存
        $result = $this->cache->getByTemplate(self::TEST_TEMPLATE_A, ['id' => 123]);
        $this->assertEquals($testData, $result);
        
        // 检查模板缓存是否存在
        $this->assertTrue($this->cache->hasByTemplate(self::TEST_TEMPLATE_A, ['id' => 123]));
        
        // 删除模板缓存
        $this->assertTrue($this->cache->deleteByTemplate(self::TEST_TEMPLATE_A, ['id' => 123]));
        $this->assertNull($this->cache->getByTemplate(self::TEST_TEMPLATE_A, ['id' => 123]));
        $this->assertFalse($this->cache->hasByTemplate(self::TEST_TEMPLATE_A, ['id' => 123]));
    }
    
    public function testTemplateWithCallback()
    {
        $callbackExecuted = false;
        $testData = [
            'id' => 456,
            'name' => 'Callback Test Data',
            'value' => 'callback_value'
        ];
        
        $result = $this->cache->getByTemplate(self::TEST_TEMPLATE_B, ['id' => 456], function() use (&$callbackExecuted, $testData) {
            $callbackExecuted = true;
            return $testData;
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertEquals($testData, $result);
        
        // 第二次调用应该从缓存获取
        $callbackExecuted = false;
        $result2 = $this->cache->getByTemplate(self::TEST_TEMPLATE_B, ['id' => 456]);
        
        $this->assertFalse($callbackExecuted);
        $this->assertEquals($testData, $result2);
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
        $keys = ['test:1', 'test:2', 'test:3'];
        $callbackExecuted = false;
        
        // 预设一个缓存
        $this->cache->set('test:1', ['id' => 1, 'name' => 'Test 1']);
        
        $result = $this->cache->getMultiple($keys, function($missingKeys) use (&$callbackExecuted) {
            $callbackExecuted = true;
            $this->assertEquals(['test:2', 'test:3'], $missingKeys);
            
            return [
                'test:2' => ['id' => 2, 'name' => 'Test 2'],
                'test:3' => ['id' => 3, 'name' => 'Test 3'],
            ];
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertCount(3, $result);
        $this->assertEquals(['id' => 1, 'name' => 'Test 1'], $result['test:1']);
        $this->assertEquals(['id' => 2, 'name' => 'Test 2'], $result['test:2']);
        $this->assertEquals(['id' => 3, 'name' => 'Test 3'], $result['test:3']);
    }
    
    // ========== 辅助函数测试 ==========
    
    public function testHelperFunctions()
    {
        $testData = ['id' => 123, 'name' => 'Helper Test'];
        
        // 测试 cache_kv_set
        $this->assertTrue(cache_kv_set(self::TEST_TEMPLATE_A, ['id' => 123], $testData));
        
        // 测试 cache_kv_get
        $result = cache_kv_get(self::TEST_TEMPLATE_A, ['id' => 123]);
        $this->assertEquals($testData, $result);
        
        // 测试 cache_kv_delete
        $this->assertTrue(cache_kv_delete(self::TEST_TEMPLATE_A, ['id' => 123]));
        $this->assertNull(cache_kv_get(self::TEST_TEMPLATE_A, ['id' => 123]));
    }
    
    public function testHelperFunctionWithCallback()
    {
        $callbackExecuted = false;
        $testData = ['id' => 456, 'name' => 'Helper Callback Test'];
        
        $result = cache_kv_get(self::TEST_TEMPLATE_B, ['id' => 456], function() use (&$callbackExecuted, $testData) {
            $callbackExecuted = true;
            return $testData;
        });
        
        $this->assertTrue($callbackExecuted);
        $this->assertEquals($testData, $result);
    }
    
    public function testHelperConfig()
    {
        cache_kv_config([
            'app_prefix' => 'helper_test',
            'env_prefix' => 'test_env',
            'templates' => [
                'test_tmpl' => 'test_tmpl:{id}'
            ]
        ]);
        
        $instance = cache_kv_instance();
        $keyManager = $instance->getKeyManager();
        
        $this->assertEquals('helper_test:test_env:v1:test_tmpl:123', $keyManager->make('test_tmpl', ['id' => 123]));
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
    
    public function testClearTag()
    {
        // 设置多个带标签的缓存项
        $this->cache->setWithTag('item:1', ['name' => 'Item 1'], ['group_a']);
        $this->cache->setWithTag('item:2', ['name' => 'Item 2'], ['group_a']);
        $this->cache->setWithTag('item:3', ['name' => 'Item 3'], ['group_b']);
        
        // 验证缓存存在
        $this->assertTrue($this->cache->has('item:1'));
        $this->assertTrue($this->cache->has('item:2'));
        $this->assertTrue($this->cache->has('item:3'));
        
        // 清除 group_a 标签
        $this->cache->clearTag('group_a');
        
        // 验证 group_a 标签的缓存被清除，group_b 标签的缓存仍然存在
        $this->assertFalse($this->cache->has('item:1'));
        $this->assertFalse($this->cache->has('item:2'));
        $this->assertTrue($this->cache->has('item:3'));
    }
    
    // ========== 键管理测试 ==========
    
    public function testKeyGeneration()
    {
        $key = $this->keyManager->makeKey('template', 'template_a', ['id' => 123]);
        $this->assertEquals('test:tmpl:v1:tmpl_a:123', $key);
        
        $key2 = $this->keyManager->makeKey('template', 'template_c', ['id' => 'abc123']);
        $this->assertEquals('test:tmpl:v1:tmpl_c:abc123', $key2);
    }
    
    public function testMakeKey()
    {
        $key = $this->cache->makeKey(self::TEST_TEMPLATE_A, ['id' => 456]);
        $this->assertEquals('test:tmpl:v1:tmpl_a:456', $key);
        
        $keyWithoutPrefix = $this->cache->makeKey(self::TEST_TEMPLATE_A, ['id' => 456], false);
        $this->assertEquals('tmpl_a:456', $keyWithoutPrefix);
    }
    
    // ========== TTL 测试 ==========
    
    public function testDefaultTtl()
    {
        $this->assertEquals(3600, $this->cache->getDefaultTtl());
        
        $this->cache->setDefaultTtl(7200);
        $this->assertEquals(7200, $this->cache->getDefaultTtl());
    }
    
    // ========== 工具方法测试 ==========
    
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
        
        // 现在支持无 KeyManager 的情况，会生成简单键
        $result = $cache->getByTemplate('any_template', ['id' => 123], function() {
            return ['id' => 123, 'data' => 'test'];
        });
        
        $this->assertEquals(['id' => 123, 'data' => 'test'], $result);
        
        // 验证生成的键
        $key = $cache->makeKey('any_template', ['id' => 123]);
        $this->assertEquals('any_template:123', $key);
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
