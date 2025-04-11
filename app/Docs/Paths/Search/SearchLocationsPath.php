<?php

namespace App\Docs\Paths\Search;

use App\Docs\Operations\Search\StoreLocationsSearchOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class SearchLocationsPath extends PathItem
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/search/locations')
            ->operations(
                StoreLocationsSearchOperation::create()
            );
    }
}
