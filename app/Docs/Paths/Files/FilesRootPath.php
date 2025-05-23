<?php

namespace App\Docs\Paths\Files;

use App\Docs\Operations\Files\StoreFileOperation;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\PathItem;

class FilesRootPath extends PathItem
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->route('/files')
            ->operations(
                StoreFileOperation::create()
            );
    }
}
