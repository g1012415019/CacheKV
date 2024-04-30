<?php

namespace Asfop\Tests;

use Asfop\HasOne\HasOne;

class UserHasOne
{
    const KEY = "user:v1";

    /**
     * 获取多个信息
     * @param $ids
     * @param array $attrs
     * @return array
     */
    public function getInfoList($ids, array $attrs = []): array
    {
        $cache = new \Asfop\HasOne\Cache(\Illuminate\Support\Facades\Redis::connection());
        $drive = new Drive();
        $hasOne = new HasOne($cache, $drive, self::KEY);
        return $hasOne->getInfoList($ids, $attrs);
    }

    /**
     * 获取单个信息
     * @param int $id
     * @param array $attrs
     * @return array
     */
    public function getInfo(int $id, array $attrs = []): array
    {
        $cache = new \Asfop\HasOne\Cache(\Illuminate\Support\Facades\Redis::connection());
        $drive = new Drive();
        $hasOne = new HasOne($cache, $drive, self::KEY);
        return $hasOne->getInfoList([$id], $attrs);
    }

    /**
     * 获取单个信息
     * @param int $id
     * @param array $attrs
     * @return array
     */
    public function forgetCache(int $id, string $attr)
    {
        $cache = new \Asfop\HasOne\Cache(\Illuminate\Support\Facades\Redis::connection());
        $drive = new Drive();
        $hasOne = new HasOne($cache, $drive, self::KEY);
        $hasOne->forgetCache($id, $attr);
    }
}
