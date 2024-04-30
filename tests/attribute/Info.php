<?php

namespace Asfop\Tests\attribute;


use App\Models\MemberModel;
use Asfop\HasOne\attribute\Base;


class Info extends Base
{

    public static function getNames(): string
    {
        return 'info';
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
        $result = MemberModel::whereIn('id', $this->getIds())->get(['id', 'nick', 'channel', 'status', 'os', 'mobile',
            'reg_time', 'end_time', 'svip_end_time', 'appid', 'sex', 'user_sign', 'pic', 'online_time', 'open_screen_num',
            'city', 'notice', 'reg_ip', 'is_bind']);
        return !empty($result) ? array_column($result->toArray(), null, 'id') : [];
    }

    public function transform($data): array
    {
        if (empty($data)) {
            return [];
        }

        $item = [];
        $item['id'] = $data['id'];
        $item['nick'] = $data['nick'];
        $item['mobile'] = $data['mobile'];
        return $item;
    }
}
