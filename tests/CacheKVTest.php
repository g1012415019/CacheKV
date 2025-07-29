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

        // Initialize DataCache with ArrayDriver for most tests
        $arrayDriver = new ArrayDriver();
        $this->cacheKV = new CacheKV($arrayDriver, 3600);
    }

    public function testDataCacheSetAndGet()
    {
       print_r([1111]);
    }
}