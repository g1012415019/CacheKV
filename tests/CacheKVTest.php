<?php

declare(strict_types=1);

namespace Asfop\CacheKV\Tests;

use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\Cache\Drivers\ArrayDriver;
use Asfop\CacheKV\Cache\Drivers\RedisDriver;
use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Tests\TestDoubles\DummyRedis;

class CacheKVTest extends TestCase
{
    private CacheKV $cacheKV;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear CacheManager's static instances before each test
        CacheManager::clearResolvedInstances();

        // Initialize CacheKV with RedisDriver for tests
        RedisDriver::setRedisFactory(function () {
            return new class {
                private $data = [];
                public function get($key)
                {
                    return $this->data[$key] ?? null;
                }
                public function set($key, $value, $ttl)
                {
                    $this->data[$key] = $value;
                    return true;
                }
                public function del(...$keys)
                {
                    foreach ($keys as $key) {
                        unset($this->data[$key]);
                    } return count($keys);
                }
                public function expire($key, $ttl)
                {
                    return true;
                }
                public function exists($key)
                {
                    return isset($this->data[$key]);
                }
                public function mget(array $keys)
                {
                    $results = [];
                    foreach ($keys as $key) {
                        $results[] = $this->data[$key] ?? null;
                    } return $results;
                }
                public function mset(array $pairs)
                {
                    foreach ($pairs as $key => $value) {
                        $this->data[$key] = $value;
                    } return true;
                }
            };
        });
        $redisDriver = new RedisDriver();
        $this->cacheKV = new CacheKV($redisDriver, 3600);
    }

    public function testDataCacheSetAndGet()
    {
    }
}
