<?php

declare(strict_types=1);

namespace Asfop\QueryCache\Database;

/**
 * 数据库适配器接口。
 * 定义了与底层数据库进行交互的方法，使得QueryCache核心逻辑与具体数据库实现解耦。
 */
interface DatabaseAdapter
{
    /**
     * 根据ID从指定表中获取单个记录。
     *
     * @param string $table 表名。
     * @param string $idColumn ID列的名称。
     * @param mixed $id ID值。
     * @param array $columns 要选择的列，默认为所有列（'*'）。
     * @return array|null 获取到的记录（关联数组），如果未找到则返回null。
     */
    public function find(string $table, string $idColumn, $id, array $columns = ['*']): ?array;

    /**
     * 根据ID数组从指定表中获取多个记录。
     * 返回的结果应是一个关联数组，其中键是ID，值是对应的记录。
     *
     * @param string $table 表名。
     * @param string $idColumn ID列的名称。
     * @param array $ids ID值数组。
     * @param array $columns 要选择的列，默认为所有列（'*'）。
     * @return array 关联数组，键是ID，值是记录。
     */
    public function findMany(string $table, string $idColumn, array $ids, array $columns = ['*']): array;
}