<?php

namespace Asfop\Eloquent;


use Asfop\Eloquent\attribute\Drive;
use Asfop\Eloquent\contract\AttrInterface;
use InvalidArgumentException;

class Factory
{
    /**
     * @param Drive $drive 属性映射配置
     * @param string $attr
     * @return AttrInterface
     */
    public static function analysis($drive, $attr)
    {
        /**
         * @var AttrInterface $class
         */
        $class = $drive->config()[$attr] ?? null;

        if (is_null($class)) {
            throw new InvalidArgumentException(
                "Authentication user provider [{$attr}] is not defined."
            );
        }
        return new $class;
    }
}
