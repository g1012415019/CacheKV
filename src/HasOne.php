<?php

namespace Asfop\Eloquent;

use Asfop\Eloquent\attribute\Drive;

class Eloquent
{
    /**
     * 不可以改
     */
    const CACHE_KEY = "%s:_";

    protected $cacheExpire = 86400;

    protected $name;

    /**
     * @var array
     */
    protected $items = [];

    /**
     * @var array
     */
    protected $queryItems = [];

    protected $cacheItems = [];

    /**
     * @var Cache
     */
    private $cache;
    private $drive;

    /**
     * @param Cache $cache 缓存类
     * @param Drive $drive 属性映射配置
     * @param string $name 缓存前缀标识
     */
    public function __construct($cache, $drive, $name = 'has_one')
    {
        $this->cache = $cache;
        $this->drive = $drive;
        $this->name = $name;
    }

    /**
     * @return string
     */
    private function getCacheKey(): string
    {
        return sprintf(self::CACHE_KEY, $this->name);
    }

    /**
     * @param $id
     * @param $key
     * @return string
     */
    private function getUniqueKey($id, $key, $cacheVersion): string
    {
        return $this->getCacheKey() . "{$id}:_" . $key . ":_" . $cacheVersion;
    }

    /**
     * 获取多个信息
     * @param $ids
     * @param array $attrs
     * @return array
     */
    public function getInfoList($ids, array $attrs = []): array
    {

        $this->items = [];
        $this->queryItems = [];
        $this->cacheItems = [];

        //先从缓存中获取数据
        $this->getFromByCache($ids, $attrs);

        //从db中获取数据
        $this->getFromByDB();

        //将db中的数据写入缓存中
        $this->setDataListToCache();

        $this->transforms($ids, $attrs);

        return $this->items;
    }

    /**
     * 获取单个信息
     * @param int $id
     * @param array $attrs
     * @return array
     */
    public function getInfo(int $id, array $attrs = []): array
    {
        $list = $this->getInfoList([$id], $attrs);

        $dataList = [];
        foreach ($attrs as $attr) {
            $dataList[$attr] = $list[$attr][$id];
        }

        return $dataList;
    }

    /**
     * 清除缓存
     * @param int $id
     * @param $attr
     * @return void
     */
    public function forgetCache(int $id, $attr)
    {
        $factory = Factory::analysis($this->drive, $attr);
        $this->cache->forget($this->getUniqueKey($id, $attr, $factory->geCacheVersion()));
    }

    /**
     * @param array $ids
     * @param array $attrs
     * @return void
     */
    private function getFromByCache(array $ids = [], array $attrs = [])
    {
        $keys = [];
        foreach ($ids as $id) {
            foreach ($attrs as $attr) {
                $factory = Factory::analysis($this->drive, $attr);
                $keys[] = $this->getUniqueKey($id, $attr, $factory->geCacheVersion());
            }
        }

        //从缓存中获取数据
        $list = $this->cache->many($keys);

        $cacheDataList = [];
        foreach ($list as $index => $item) {
            //i:f:user:v1:_用户id:_user
            list(, $id, $attr) = explode(":_", $index);
            // attr =>[id1,id2,id3]
            //需要走db
            if (is_null($item) || $item=="" ) {
                $this->queryItems[$attr][] = $id;
                continue;
            }
            //从缓存中读取的
            $cacheDataList[$attr][$id] = $item;
        }

        //处理缓存中的数据
        foreach ($cacheDataList as $attr => $attrItems) {
            foreach ($attrItems as $id => $value) {
                $factory = Factory::analysis($this->drive, $attr);
                $factory->setItems([$id => $value]);
                $value = $factory->transforms()->first()->get();
                $this->items[$attr][$id] = $value;
            }
        }

        if (!empty($cacheDataList)) {
            unset($list);
            unset($cacheDataList);
        }
    }

    /**
     * @return void
     */
    private function getFromByDB()
    {
        //从db中获取数据
        foreach ($this->queryItems as $attr => $ids) {

            $factory = Factory::analysis($this->drive, $attr);
            $values = $factory->setIds($ids)
                ->setItems($factory->getInfoByIds())
                ->get();
            //获取转换后的
            $transformsValues = $factory->transforms()->get();
            foreach ($ids as $id) {
                //存储未转换的数据
                $this->cacheItems[$this->getUniqueKey($id, $attr, $factory->geCacheVersion())] = $values[$id] ?? [];
                $this->items[$attr][$id] = $transformsValues[$id] ?? [];
            }

        }
    }

    /**
     * 将db中的数据写入缓存中
     * @return void
     */
    private function setDataListToCache()
    {
        if (empty($this->cacheItems)) {
            return;
        }
        $this->cache->putMany($this->cacheItems, $this->cacheExpire + mt_rand(1, 10 * 60));
    }

    /**
     * @param $ids
     * @param array $attrs
     * @return void
     */
    private function transforms($ids, array $attrs = [])
    {
        $list = [];
        foreach ($ids as $index => $id) {
            foreach ($attrs as $index => $attr) {
                $list[$id][$attr] = $this->items[$attr][$id];
            }

        }

        $this->items = $list;
        if (!empty($list)) {
            unset($list);
        }
    }
}
