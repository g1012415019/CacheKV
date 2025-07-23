<?php

declare(strict_types=1);

namespace Asfop\QueryCache\Key;

/**
 * 缓存键生成器接口。
 * 定义了如何为缓存数据生成唯一键的方法。
 */
interface KeyGenerator
{
    /**
     * 生成一个唯一的缓存键。
     *
     * @param string $entityName 实体名称（例如：'user'）。
     * @param mixed $identifier 实体的唯一标识符（例如：用户ID）。
     * @param string $relationName 属性/关联的名称（例如：'info'，'phone'）。
     * @return string 生成的缓存键字符串。
     */
    public function generate(string $entityName, $identifier, string $relationName): string;
}