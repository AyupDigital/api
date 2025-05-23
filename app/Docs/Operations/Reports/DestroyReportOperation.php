<?php

namespace App\Docs\Operations\Reports;

use App\Docs\Responses\ResourceDeletedResponse;
use App\Docs\Tags\ReportsTag;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Operation;

class DestroyReportOperation extends Operation
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->action(static::ACTION_DELETE)
            ->tags(ReportsTag::create())
            ->summary('Delete a specific report')
            ->description('**Permission:** `Super Admin`')
            ->responses(ResourceDeletedResponse::create(null, 'report'));
    }
}
