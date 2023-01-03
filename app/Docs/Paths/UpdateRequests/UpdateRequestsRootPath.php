<?php

namespace App\Docs\Paths\UpdateRequests;

use App\Docs\Operations\UpdateRequests\IndexUpdateRequestOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class UpdateRequestsRootPath extends PathItem
{
    /**
     * @param  string|null  $objectId
     * @return static
     *
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/update-requests')
            ->operations(
                IndexUpdateRequestOperation::create()
            );
    }
}
