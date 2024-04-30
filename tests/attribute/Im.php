<?php

namespace Asfop\Tests\attribute;


use App\Models\MemberImModel;
use App\Models\MemberModel;
use Asfop\HasOne\attribute\Base;


class Im extends Base
{

    protected $cacheVersion = "v6";

    public static function getNames(): string
    {
        return 'im';
    }

    /**
     * @inheritDoc
     */
    public function getField(): array
    {
        return ["*"];
    }

    /**
     * @inheritDoc
     */
    public function getInfoByIds(): array
    {
        $result = MemberImModel::whereIn('uid', $this->getIds())->get(['*']);
        return !empty($result) ? array_column($result->toArray(), null, 'uid') : [];
    }

    public function transform($data): array
    {
        if (empty($data)) {
            return [];
        }
        return [
            "id" => $data["id"],
            "im_uuid" => $data["im_uuid"]
        ];
    }
}
