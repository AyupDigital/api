<?php

namespace App\Docs\Parameters;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;

class FilterParameter extends Parameter
{
    public static function create(?string $objectId = null, string $field = ''): BaseObject
    {
        return parent::create($objectId)
            ->in(static::IN_QUERY)
            ->name("filter[{$field}]");
    }
}
