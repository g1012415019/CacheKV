<?php

declare(strict_types=1);

namespace Asfop\CacheKV\Tests;

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\DataCache;
use Asfop\CacheKV\Tests\TestDoubles\DummyRedis;

class DataCacheTest extends TestCase
{
    private DataCache $dataCache;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear CacheManager's static instances before each test
        CacheManager::clearResolvedInstances();

        // Initialize DataCache with ArrayDriver for most tests
        $arrayDriver = new ArrayDriver();
        $this->dataCache = new DataCache($arrayDriver, 3600);
    }

    public function testDataCacheSetAndGet()
    {
        $key = 'test_key';
        $value = ['data' => 'some value'];
        $ttl = 60;

        $this->dataCache->set($key, $value, $ttl);
        $this->assertTrue($this->dataCache->has($key));
        $this->assertEquals($value, $this->dataCache->get($key));
    }

    public function testDataCacheForget()
    {
        $key = 'test_key_to_forget';
        $this->dataCache->set($key, 'value', 60);
        $this->assertTrue($this->dataCache->has($key));

        $this->dataCache->forget($key);
        $this->assertFalse($this->dataCache->has($key));
    }

    public function testDataCacheSetWithTagAndClearTag()
    {
        $cacheKey = 'test_item';
        $tag = 'my_tag';
        $value = ['data' => 'some value'];

        $this->dataCache->setWithTag($cacheKey, $value, $tag, 60);
        $this->assertTrue($this->dataCache->has($cacheKey));

        $this->dataCache->clearTag($tag);
        $this->assertFalse($this->dataCache->has($cacheKey));
    }

    public function testDataCacheGetMultiple()
    {
        $keys = ['key1', 'key2', 'key3'];
        $this->dataCache->set('key1', 'value1', 60);

        $fetched = $this->dataCache->getMultiple($keys, function ($missingKeys) {
            $this->assertEquals(['key2', 'key3'], $missingKeys);
            return ['key2' => 'value2_from_db', 'key3' => 'value3_from_db'];
        }, 60);

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2_from_db',
            'key3' => 'value3_from_db',
        ], $fetched);

        // Ensure newly fetched items are cached
        $this->assertTrue($this->dataCache->has('key2'));
        $this->assertTrue($this->dataCache->has('key3'));
    }

    public function testDataCacheGetStats()
    {
        // Reset stats for a clean test
        $arrayDriver = new ArrayDriver();
        $dataCache = new DataCache($arrayDriver, 3600);

        $dataCache->get('non_existent_key'); // Miss
        $dataCache->set('existing_key', 'value', 60);
        $dataCache->get('existing_key'); // Hit
        $dataCache->get('existing_key'); // Hit
        $dataCache->get('another_non_existent'); // Miss

        $stats = $dataCache->getStats();

        $this->assertEquals(2, $stats['hits']);
        $this->assertEquals(2, $stats['misses']);
        $this->assertEquals(0.5, $stats['hit_rate']);
    }

    public function testRedisDriverIntegration()
    {
        // Skip if Redis extension is not loaded or DummyRedis is not available
        if (!class_exists('Redis') || !class_exists('Asfop\DataCache\Tests\TestDoubles\DummyRedis')) {
            $this->markTestSkipped('Redis extension or DummyRedis not available.');
        }

        // Use DummyRedis for testing without a real Redis server
        $redis = new DummyRedis();
        $redisDriver = new RedisDriver($redis);
        $dataCache = new DataCache($redisDriver, 60);

        // Test set and get
        $dataCache->set('user:1', ['name' => 'John'], 300);
        $this->assertEquals(['name' => 'John'], $dataCache->get('user:1'));

        // Test setWithTag and clearTag
        $dataCache->setWithTag('post:1', ['title' => 'Post 1'], 'posts', 300);
        $dataCache->setWithTag('post:2', ['title' => 'Post 2'], 'posts', 300);
        $this->assertTrue($dataCache->has('post:1'));
        $this->assertTrue($dataCache->has('post:2'));

        $dataCache->clearTag('posts');
        $this->assertFalse($dataCache->has('post:1'));
        $this->assertFalse($dataCache->has('post:2'));

        // Test getMultiple
        $dataCache->set('product:1', ['name' => 'Product A'], 300);
        $dataCache->set('product:2', ['name' => 'Product B'], 300);

        $fetchedProducts = $this->dataCache->getMultiple(['product:1', 'product:2', 'product:3'], function ($missingKeys) {
            $this->assertEquals(['product:3'], $missingKeys);
            return ['product:3' => ['name' => 'Product C from DB']];
        }, 300);

        $this->assertEquals([
            'product:1' => ['name' => 'Product A'],
            'product:2' => ['name' => 'Product B'],
            'product:3' => ['name' => 'Product C from DB'],
        ], $fetchedProducts);

        // Test stats (DummyRedis doesn't track hits/misses, so this will be 0/0)
        $stats = $this->dataCache->getStats();
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
    }

    public function testSlidingExpiration()
    {
        $arrayDriver = new ArrayDriver();
        $slidingCache = new DataCache($arrayDriver, 5); // Set defaultTtl to 5 seconds for this test

        $key = 'sliding_key';
        $value = ['data' => 'sliding value'];
        $initialTtl = 2; // This is the TTL for the initial set

        // Set item with initial TTL
        $slidingCache->set($key, $value, $initialTtl);
        $this->assertTrue($slidingCache->has($key));

        // Wait for 1 second (less than initial TTL)
        sleep(1);

        // Get the item, which should renew its expiration to 5 seconds (defaultTtl)
        $retrievedValue = $slidingCache->get($key);
        $this->assertEquals($value, $retrievedValue);

        // Wait for 3 seconds (total 1+3=4 seconds from initial set).
        // Renewed TTL was 5 seconds. So, item should still exist.
        sleep(3);
        $this->assertTrue($slidingCache->has($key));

        // Wait for another 2 seconds (total 4+2=6 seconds from initial set).
        // Renewed TTL was 5 seconds. So, item should now be expired.
        sleep(2);
        $this->assertFalse($slidingCache->has($key));
    }
}