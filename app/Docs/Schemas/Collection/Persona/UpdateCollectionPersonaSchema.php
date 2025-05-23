<?php

namespace App\Docs\Schemas\Collection\Persona;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class UpdateCollectionPersonaSchema extends Schema
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException
     */
    public static function create(?string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->type(static::TYPE_OBJECT)
            ->required(
                'name',
                'intro',
                'subtitle',
                'order',
                'enabled',
                'sideboxes',
                'category_taxonomies'
            )
            ->properties(
                Schema::string('name'),
                Schema::string('intro'),
                Schema::string('subtitle'),
                Schema::integer('order'),
                Schema::boolean('enabled'),
                Schema::boolean('homepage'),
                Schema::array('sideboxes')
                    ->maxItems(3)
                    ->items(
                        Schema::object()
                            ->required('title', 'content')
                            ->properties(
                                Schema::string('title'),
                                Schema::string('content')
                            )
                    ),
                Schema::array('category_taxonomies')
                    ->items(
                        Schema::string()
                            ->format(Schema::FORMAT_UUID)
                    ),
                Schema::string('image_file_id')
                    ->format(Schema::FORMAT_UUID)
                    ->description('The ID of the file uploaded')
                    ->nullable()
            );
    }
}
