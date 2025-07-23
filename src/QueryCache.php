<?php

declare(strict_types=1);

namespace Asfop\QueryCache;

use Asfop\QueryCache\Cache\CacheDriver;
use Asfop\QueryCache\Database\DatabaseAdapter;
use Asfop\QueryCache\Key\KeyGenerator;
use Asfop\QueryCache\Key\DefaultGenerator;

/**
 * QueryCache 核心类
 * 负责协调数据库查询、缓存存储和缓存键生成，实现数据的缓存获取和批量处理。
 */
class QueryCache
{
    /**
     * @var DatabaseAdapter 数据库适配器实例
     */
    private DatabaseAdapter $dbAdapter;

    /**
     * @var CacheDriver 缓存驱动实例
     */
    private CacheDriver $cacheDriver;

    /**
     * @var KeyGenerator 缓存键生成器实例
     */
    private KeyGenerator $keyGenerator;

    /**
     * 构造函数
     *
     * @param DatabaseAdapter $dbAdapter 数据库适配器，用于执行实际的数据库查询。
     * @param CacheDriver $cacheDriver 缓存驱动，用于数据的存取和管理。
     * @param KeyGenerator|null $keyGenerator 缓存键生成器，如果为null则使用DefaultGenerator。
     */
    public function __construct(
        DatabaseAdapter $dbAdapter,
        CacheDriver $cacheDriver,
        ?KeyGenerator $keyGenerator = null
    )
    {
        $this->dbAdapter = $dbAdapter;
        $this->cacheDriver = $cacheDriver;
        $this->keyGenerator = $keyGenerator ?: new DefaultGenerator();
    }

    /**
     * 根据ID获取单个记录，并进行缓存。
     * 如果缓存命中，则直接返回缓存数据；否则从数据库获取，并存入缓存。
     *
     * @param string $entityName 实体名称（例如：'user'），用于生成缓存键。
     * @param string $table 主实体所在的表名。
     * @param mixed $id 主实体的ID。
     * @param array $attributes 要获取的属性/关联数组（例如：['info', 'phone']）。
     * @param array $config 每个属性的配置（例如：['info' => ['table' => 'user_info', 'id_column' => 'user_id', 'ttl' => 3600]]）。
     * @return array 获取到的数据，以属性名为键的关联数组。
     */
    public function getById(
        string $entityName,
        string $table,
        $id,
        array $attributes,
        array $config
    ): array
    {
        $results = [];
        foreach ($attributes as $attr) {
            $attrConfig = $config[$attr] ?? [];
            $ttl = $attrConfig['ttl'] ?? 3600; // 默认缓存时间为3600秒
            $attrTable = $attrConfig['table'] ?? $table; // 如果未指定，默认为主表
            $attrIdColumn = $attrConfig['id_column'] ?? 'id'; // 如果未指定，默认为'id'列
            $attrColumns = $attrConfig['columns'] ?? ['*']; // 如果未指定，默认为所有列

            $cacheKey = $this->keyGenerator->generate($entityName, $id, $attr);

            // 尝试从缓存中获取
            if ($this->cacheDriver->has($cacheKey)) {
                $results[$attr] = $this->cacheDriver->get($cacheKey);
            } else {
                // 缓存未命中，从数据库获取
                $data = $this->dbAdapter->find($attrTable, $attrIdColumn, $id, $attrColumns);
                // 将数据存入缓存
                $this->cacheDriver->set($cacheKey, $data, $ttl);
                $results[$attr] = $data;
            }
        }
        return $results;
    }

    /**
     * 根据ID获取多个记录，并进行缓存和批量处理。
     * 该方法会尝试从缓存中批量获取数据，对于缓存未命中的部分，会通过数据库适配器进行批量查询，
     * 然后将结果存入缓存，并返回所有请求的数据。
     *
     * @param string $entityName 实体名称（例如：'user'），用于生成缓存键。
     * @param string $table 主实体所在的表名。
     * @param array $ids 主实体的ID数组。
     * @param array $attributes 要获取的属性/关联数组（例如：['info', 'phone']）。
     * @param array $config 每个属性的配置。
     * @return array 获取到的数据，以主实体ID为第一层键，属性名为第二层键的关联数组。
     */
    public function getByIds(
        string $entityName,
        string $table,
        array $ids,
        array $attributes,
        array $config
    ): array
    {
        $finalResults = []; // 最终返回的结果
        $idsToFetch = []; // 需要从数据库获取的ID列表
        $cachedResults = []; // 已经从缓存中获取的结果

        // 遍历所有请求的ID和属性，尝试从缓存中获取
        foreach ($ids as $id) {
            $finalResults[$id] = []; // 初始化最终结果结构
            foreach ($attributes as $attr) {
                $cacheKey = $this->keyGenerator->generate($entityName, $id, $attr);
                if ($this->cacheDriver->has($cacheKey)) {
                    // 缓存命中，存入cachedResults
                    $cachedResults[$id][$attr] = $this->cacheDriver->get($cacheKey);
                } else {
                    // 缓存未命中，记录需要从数据库获取的ID
                    $idsToFetch[$attr][] = $id;
                }
            }
        }

        // 批量从数据库获取缓存未命中的数据
        foreach ($idsToFetch as $attr => $attrIds) {
            if (empty($attrIds)) continue; // 如果该属性的所有ID都已缓存，则跳过

            $attrConfig = $config[$attr] ?? [];
            $ttl = $attrConfig['ttl'] ?? 3600;
            $attrTable = $attrConfig['table'] ?? $table;
            $attrIdColumn = $attrConfig['id_column'] ?? 'id';
            $attrColumns = $attrConfig['columns'] ?? ['*'];

            // 批量从数据库获取数据
            $fetchedData = $this->dbAdapter->findMany($attrTable, $attrIdColumn, $attrIds, $attrColumns);

            // 将获取到的数据存入缓存，并更新cachedResults
            foreach ($attrIds as $id) {
                $data = $fetchedData[$id] ?? null; // 如果数据库中没有找到，则为null
                $cacheKey = $this->keyGenerator->generate($entityName, $id, $attr);
                $this->cacheDriver->set($cacheKey, $data, $ttl);
                $cachedResults[$id][$attr] = $data;
            }
        }

        // 组合所有结果（缓存命中和数据库获取的）到最终结果结构中
        foreach ($ids as $id) {
            foreach ($attributes as $attr) {
                $finalResults[$id][$attr] = $cachedResults[$id][$attr] ?? null;
            }
        }

        return $finalResults;
    }

    /**
     * 清除指定实体和属性的缓存。
     *
     * @param string $entityName 实体名称。
     * @param mixed $id 实体ID。
     * @param string $attribute 属性名称。
     * @return bool 缓存是否成功清除。
     */
    public function forgetCache(string $entityName, $id, string $attribute): bool
    {
        $cacheKey = $this->keyGenerator->generate($entityName, $id, $attribute);
        return $this->cacheDriver->forget($cacheKey);
    }
}