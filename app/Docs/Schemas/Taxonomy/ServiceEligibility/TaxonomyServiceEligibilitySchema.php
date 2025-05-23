<?php

namespace App\Docs\Schemas\Taxonomy\ServiceEligibility;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class TaxonomyServiceEligibilitySchema extends Schema
{
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->type(static::TYPE_OBJECT)
            ->properties(
                Schema::string('id')
                    ->format(Schema::FORMAT_UUID),
                Schema::string('parent_id')
                    ->format(Schema::FORMAT_UUID)
                    ->nullable(),
                Schema::string('slug'),
                Schema::string('name'),
                Schema::integer('order'),
                Schema::string('created_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable(),
                Schema::string('updated_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable()
            );
    }
}
