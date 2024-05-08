<?php

namespace Asfop\Tests;

use \Asfop\Eloquent\attribute\Drive as BaseDrive;
use Asfop\Tests\attribute\Im;
use Asfop\Tests\attribute\Info;

class Drive extends BaseDrive
{

    public function config(): array
    {
        return [
            Info::getNames() => Info::class,
            Im::getNames() => Im::class
        ];
    }
}
