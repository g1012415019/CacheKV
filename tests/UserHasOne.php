<?php

namespace Asfop\Tests;

use Asfop\Eloquent\Eloquent;

class UserEloquent
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
        $cache = new \Asfop\Eloquent\Cache(\Illuminate\Support\Facades\Redis::connection());
        $drive = new Drive();
        $Eloquent = new Eloquent($cache, $drive, self::KEY);
        return $Eloquent->getInfoList($ids, $attrs);
    }

    /**
     * 获取单个信息
     * @param int $id
     * @param array $attrs
     * @return array
     */
    public function getInfo(int $id, array $attrs = []): array
    {
        $cache = new \Asfop\Eloquent\Cache(\Illuminate\Support\Facades\Redis::connection());
        $drive = new Drive();
        $Eloquent = new Eloquent($cache, $drive, self::KEY);
        return $Eloquent->getInfoList([$id], $attrs);
    }

    /**
     * 获取单个信息
     * @param int $id
     * @param array $attrs
     * @return array
     */
    public function forgetCache(int $id, string $attr)
    {
        $cache = new \Asfop\Eloquent\Cache(\Illuminate\Support\Facades\Redis::connection());
        $drive = new Drive();
        $Eloquent = new Eloquent($cache, $drive, self::KEY);
        $Eloquent->forgetCache($id, $attr);
    }
}
