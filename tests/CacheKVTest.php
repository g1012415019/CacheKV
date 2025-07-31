<?php

namespace Asfop\CacheKV\Tests;

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\CacheKV;

class CacheKVTest extends TestCase
{
    /**
     * @var CacheKV
     */
    private $cacheKV;

    protected function setUp(): void
    {
        // Clear CacheManager's static instances before each test
        CacheManager::clearResolvedInstances();

        // Use ArrayDriver for testing (simpler and doesn't require Redis)
        $arrayDriver = new ArrayDriver();
        $this->cacheKV = new CacheKV($arrayDriver, 3600);
    }

    public function testCacheSetAndGet()
    {
        // Test basic set and get
        $key = 'test_key';
        $value = 'test_value';
        
        $result = $this->cacheKV->set($key, $value);
        $this->assertTrue($result);
        
        $retrieved = $this->cacheKV->get($key);
        $this->assertEquals($value, $retrieved);
    }

    public function testCacheGetWithCallback()
    {
        $key = 'callback_key';
        $expectedValue = 'callback_value';
        
        $retrieved = $this->cacheKV->get($key, function() use ($expectedValue) {
            return $expectedValue;
        });
        
        $this->assertEquals($expectedValue, $retrieved);
        
        // Second call should return cached value without calling callback
        $retrieved2 = $this->cacheKV->get($key, function() {
            return 'should_not_be_called';
        });
        
        $this->assertEquals($expectedValue, $retrieved2);
    }

    public function testCacheHas()
    {
        $key = 'has_key';
        $value = 'has_value';
        
        $this->assertFalse($this->cacheKV->has($key));
        
        $this->cacheKV->set($key, $value);
        $this->assertTrue($this->cacheKV->has($key));
    }

    public function testCacheForget()
    {
        $key = 'forget_key';
        $value = 'forget_value';
        
        $this->cacheKV->set($key, $value);
        $this->assertTrue($this->cacheKV->has($key));
        
        $result = $this->cacheKV->forget($key);
        $this->assertTrue($result);
        $this->assertFalse($this->cacheKV->has($key));
    }

    public function testCacheSetWithTag()
    {
        $key = 'tag_key';
        $value = 'tag_value';
        $tags = array('tag1', 'tag2');
        
        $result = $this->cacheKV->setWithTag($key, $value, $tags);
        $this->assertTrue($result);
        
        $retrieved = $this->cacheKV->get($key);
        $this->assertEquals($value, $retrieved);
    }

    public function testCacheClearTag()
    {
        $key1 = 'tag_key1';
        $key2 = 'tag_key2';
        $value1 = 'tag_value1';
        $value2 = 'tag_value2';
        $tag = 'common_tag';
        
        $this->cacheKV->setWithTag($key1, $value1, array($tag));
        $this->cacheKV->setWithTag($key2, $value2, array($tag));
        
        $this->assertTrue($this->cacheKV->has($key1));
        $this->assertTrue($this->cacheKV->has($key2));
        
        $result = $this->cacheKV->clearTag($tag);
        $this->assertTrue($result);
        
        $this->assertFalse($this->cacheKV->has($key1));
        $this->assertFalse($this->cacheKV->has($key2));
    }

    public function testCacheGetMultiple()
    {
        $keys = array('multi1', 'multi2', 'multi3');
        $expectedData = array(
            'multi1' => 'value1',
            'multi2' => 'value2',
            'multi3' => 'value3'
        );
        
        $results = $this->cacheKV->getMultiple($keys, function($missingKeys) use ($expectedData) {
            $data = array();
            foreach ($missingKeys as $key) {
                if (isset($expectedData[$key])) {
                    $data[$key] = $expectedData[$key];
                }
            }
            return $data;
        });
        
        $this->assertEquals($expectedData, $results);
        
        // Second call should get from cache
        $results2 = $this->cacheKV->getMultiple($keys, function($missingKeys) {
            // This should not be called
            return array();
        });
        
        $this->assertEquals($expectedData, $results2);
    }

    public function testCacheStats()
    {
        $stats = $this->cacheKV->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
    }
}
