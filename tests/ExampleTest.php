<?php

namespace Asfop\Tests;


use Asfop\Tests\Constants\ExampleConstant;
use PHPUnit\Framework\TestCase;

/**
 * ./vendor/bin/phpcbf --standard=PSR2 --colors src/
 */
class ExampleConstantTest extends TestCase
{
    // 获取常量的值
    public function testGet()
    {
        print_r(11);
        $userHasOne = new UserHasOne();
        print_r($userHasOne->getInfoList([32, 33], ['info', 'im']));
        die;
    }
}