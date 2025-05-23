<?php

namespace App\Docs;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Server as BaseServer;

class Server extends BaseServer
{
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->url(url('/core/v1'));
    }
}
