<?php

namespace App\Docs\Schemas\Location;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class LocationSchema extends Schema
{
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->type(static::TYPE_OBJECT)
            ->properties(
                Schema::string('id')
                    ->format(Schema::FORMAT_UUID),
                Schema::boolean('has_image'),
                Schema::string('address_line_1'),
                Schema::string('address_line_2')
                    ->nullable(),
                Schema::string('address_line_3')
                    ->nullable(),
                Schema::string('city'),
                Schema::string('county'),
                Schema::string('postcode'),
                Schema::string('country'),
                Schema::number('lat')
                    ->format(Schema::FORMAT_FLOAT),
                Schema::number('lon')
                    ->format(Schema::FORMAT_FLOAT),
                Schema::object('image')
                    ->properties(
                        Schema::string('id'),
                        Schema::string('mime_type'),
                        Schema::string('alt_text'),
                        Schema::string('url')
                    )
                    ->nullable(),
                Schema::string('accessibility_info')
                    ->nullable(),
                Schema::boolean('has_wheelchair_access'),
                Schema::boolean('has_induction_loop'),
                Schema::boolean('has_accessible_toilet'),
                Schema::string('created_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable(),
                Schema::string('updated_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable()
            );
    }
}
