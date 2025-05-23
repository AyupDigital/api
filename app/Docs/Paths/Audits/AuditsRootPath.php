<?php

namespace App\Docs\Paths\Audits;

use App\Docs\Operations\Audits\IndexAuditOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class AuditsRootPath extends PathItem
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/audits')
            ->operations(
                IndexAuditOperation::create()
            );
    }
}
