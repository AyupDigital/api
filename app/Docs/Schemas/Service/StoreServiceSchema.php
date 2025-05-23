<?php

namespace App\Docs\Schemas\Service;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class StoreServiceSchema extends UpdateServiceSchema
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(?string $objectId = null): BaseObject
    {
        $instance = parent::create($objectId);

        $instance = $instance
            ->required('organisation_id', ...$instance->required)
            ->properties(
                Schema::string('organisation_id')
                    ->format(Schema::FORMAT_UUID),
                ...$instance->properties
            );

        return $instance;
    }
}
