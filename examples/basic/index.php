<?php

require_once __DIR__ . '/vendor/autoload.php';

use Asfop\CacheKV\CacheKV;
use Asfop\CacheKV\Cache\CacheManager;
use Asfop\CacheKV\CacheKVServiceProvider;

// Define a closure to create a Redis instance
CacheKV::setRedisFactory(function () {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    // You can add more Redis configuration here, like authentication
    // $redis->auth('password');
    return $redis;
});

// Register the service provider to initialize the cache
CacheKVServiceProvider::register();

// Now you can use the Redis driver without manual configuration
$cache = CacheManager::resolve('redis');

$cache->set('my_key', 'my_value', 60);

echo $cache->get('my_key');
