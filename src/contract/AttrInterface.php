<?php

namespace Asfop\HasOne\contract;

use Asfop\HasOne\Cache;

interface AttrInterface
{
    public static function getNames(): string;

    /**
     * 缓存版本
     * @return string
     */
    public function geCacheVersion(): string;

    /**
     * 需要查询的字段
     * @return array
     */
    public function getField(): array;

    /**
     * 更加多个id查询数据
     */
    public function getInfoByIds(): array;

    /**
     * 获取第一条数据
     */
    public function first(): AttrInterface;

    /**
     * 转换数据
     */
    public function transform($data);

    /**
     * 批量转换数据
     * @return AttrInterface
     */
    public function transforms(): AttrInterface;

    /**
     * 获取全部的数据
     * @return array
     */
    public function get();
}
