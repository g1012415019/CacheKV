<?php

declare(strict_types=1);

namespace Asfop\QueryCache\Tests;

use Asfop\QueryCache\Cache\CacheManager;
use Asfop\QueryCache\Cache\Drivers\ArrayDriver;
use Asfop\QueryCache\Database\DatabaseAdapter;
use Asfop\QueryCache\QueryCache;
use PDO;
use PDOException;

// Define a test PdoAdapter implementation directly in the test file
class TestPdoAdapter implements DatabaseAdapter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(string $table, string $idColumn, $id, array $columns = ['*']): ?array
    {
        $cols = implode(',', $columns);
        $stmt = $this->pdo->prepare("SELECT {$cols} FROM `{$table}` WHERE `{$idColumn}` = :id LIMIT 1");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findMany(string $table, string $idColumn, array $ids, array $columns = ['*']): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $cols = implode(',', $columns);

        $stmt = $this->pdo->prepare("SELECT {$cols} FROM `{$table}` WHERE `{$idColumn}` IN ({$placeholders})");
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id);
        }
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $indexedResults = [];
        foreach ($results as $row) {
            $indexedResults[$row[$idColumn]] = $row;
        }

        return $indexedResults;
    }
}

class QueryCacheTest extends TestCase
{
    private QueryCache $queryCache;
    private ArrayDriver $cacheDriver;
    private TestPdoAdapter $dbAdapter;

    private array $attributeConfig = [
        'user' => [
            'table' => 'users',
            'id_column' => 'id',
            'columns' => ['id', 'name'],
            'ttl' => 3600,
        ],
        'info' => [
            'table' => 'user_info',
            'id_column' => 'user_id',
            'columns' => ['user_id', 'email', 'bio'],
            'ttl' => 3600,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbAdapter = new TestPdoAdapter($this->pdo);
        $this->cacheDriver = new ArrayDriver();
        $this->queryCache = new QueryCache($this->dbAdapter, $this->cacheDriver);

        // Clear CacheManager's static instances before each test
        CacheManager::clearResolvedInstances();
    }

    public function testGetByIdFetchesFromDatabaseAndCaches()
    {
        $userId = 1;
        $cacheKeyUser = 'query_cache:user:1:user';
        $cacheKeyInfo = 'query_cache:user:1:info';

        // Ensure cache is empty initially
        $this->assertFalse($this->cacheDriver->has($cacheKeyUser));
        $this->assertFalse($this->cacheDriver->has($cacheKeyInfo));

        $data = $this->queryCache->getById(
            'user',
            'users',
            $userId,
            ['user', 'info'],
            $this->attributeConfig
        );

        $this->assertIsArray($data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('info', $data);
        $this->assertEquals('Alice', $data['user']['name']);
        $this->assertEquals('alice@example.com', $data['info']['email']);

        // Check if data is now in cache
        $this->assertTrue($this->cacheDriver->has($cacheKeyUser));
        $this->assertTrue($this->cacheDriver->has($cacheKeyInfo));

        // Fetch again, should be from cache
        $dataFromCache = $this->queryCache->getById(
            'user',
            'users',
            $userId,
            ['user', 'info'],
            $this->attributeConfig
        );
        $this->assertEquals($data, $dataFromCache);
    }

    public function testGetByIdsFetchesFromDatabaseAndCaches()
    {
        $userIds = [1, 2];
        $cacheKeyUser1 = 'query_cache:user:1:user';
        $cacheKeyInfo1 = 'query_cache:user:1:info';
        $cacheKeyUser2 = 'query_cache:user:2:user';
        $cacheKeyInfo2 = 'query_cache:user:2:info';

        // Ensure cache is empty initially
        $this->assertFalse($this->cacheDriver->has($cacheKeyUser1));
        $this->assertFalse($this->cacheDriver->has($cacheKeyInfo1));
        $this->assertFalse($this->cacheDriver->has($cacheKeyUser2));
        $this->assertFalse($this->cacheDriver->has($cacheKeyInfo2));

        $data = $this->queryCache->getByIds(
            'user',
            'users',
            $userIds,
            ['user', 'info'],
            $this->attributeConfig
        );

        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertArrayHasKey(1, $data);
        $this->assertArrayHasKey(2, $data);

        $this->assertEquals('Alice', $data[1]['user']['name']);
        $this->assertEquals('bob@example.com', $data[2]['info']['email']);

        // Check if data is now in cache
        $this->assertTrue($this->cacheDriver->has($cacheKeyUser1));
        $this->assertTrue($this->cacheDriver->has($cacheKeyInfo1));
        $this->assertTrue($this->cacheDriver->has($cacheKeyUser2));
        $this->assertTrue($this->cacheDriver->has($cacheKeyInfo2));

        // Fetch again, should be from cache
        $dataFromCache = $this->queryCache->getByIds(
            'user',
            'users',
            $userIds,
            ['user', 'info'],
            $this->attributeConfig
        );
        $this->assertEquals($data, $dataFromCache);
    }

    public function testForgetCacheRemovesItem()
    {
        $userId = 1;
        $attribute = 'info';
        $cacheKey = 'query_cache:user:1:info';

        // First, put something in cache
        $this->queryCache->getById('user', 'users', $userId, [$attribute], $this->attributeConfig);
        $this->assertTrue($this->cacheDriver->has($cacheKey));

        // Now, forget it
        $this->queryCache->forgetCache('user', $userId, $attribute);
        $this->assertFalse($this->cacheDriver->has($cacheKey));
    }

    public function testGetByIdsHandlesPartialCacheHits()
    {
        $userIds = [1, 2, 3];
        $cacheKeyUser1 = 'query_cache:user:1:user';
        $cacheKeyInfo1 = 'query_cache:user:1:info';
        $cacheKeyUser2 = 'query_cache:user:2:user';
        $cacheKeyInfo2 = 'query_cache:user:2:info';
        $cacheKeyUser3 = 'query_cache:user:3:user';
        $cacheKeyInfo3 = 'query_cache:user:3:info';

        // Cache user 1's data
        $this->queryCache->getById('user', 'users', 1, ['user', 'info'], $this->attributeConfig);
        $this->assertTrue($this->cacheDriver->has($cacheKeyUser1));
        $this->assertTrue($this->cacheDriver->has($cacheKeyInfo1));

        // Cache user 2's user data only
        $this->queryCache->getById('user', 'users', 2, ['user'], $this->attributeConfig);
        $this->assertTrue($this->cacheDriver->has($cacheKeyUser2));
        $this->assertFalse($this->cacheDriver->has($cacheKeyInfo2));

        // Fetch all three users, info for user 2 and all for user 3 should be fetched from DB
        $data = $this->queryCache->getByIds(
            'user',
            'users',
            $userIds,
            ['user', 'info'],
            $this->attributeConfig
        );

        // All should now be in cache
        $this->assertTrue($this->cacheDriver->has($cacheKeyUser1));
        $this->assertTrue($this->cacheDriver->has($cacheKeyInfo1));
        $this->assertTrue($this->cacheDriver->has($cacheKeyUser2));
        $this->assertTrue($this->cacheDriver->has($cacheKeyInfo2));
        $this->assertTrue($this->cacheDriver->has($cacheKeyUser3));
        $this->assertTrue($this->cacheDriver->has($cacheKeyInfo3));

        $this->assertEquals('Alice', $data[1]['user']['name']);
        $this->assertEquals('bob@example.com', $data[2]['info']['email']);
        $this->assertEquals('Charlie', $data[3]['user']['name']);
    }
}