<?php

namespace App\Docs\Schemas\Collection\Category;

use App\Docs\Schemas\Taxonomy\Category\TaxonomyCategorySchema;
use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class CollectionCategorySchema extends Schema
{
    public static function create(string $objectId = null): BaseObject
    {
        return parent::create($objectId)
            ->type(static::TYPE_OBJECT)
            ->properties(
                Schema::string('id')
                    ->format(Schema::FORMAT_UUID),
                Schema::string('slug'),
                Schema::string('name'),
                Schema::string('intro'),
                Schema::integer('order'),
                Schema::boolean('enabled'),
                Schema::boolean('homepage'),
                Schema::array('sideboxes')
                    ->maxItems(3)
                    ->items(
                        Schema::object()->properties(
                            Schema::string('title'),
                            Schema::string('content')
                        )
                    ),
                Schema::object('image')
                    ->properties(
                        Schema::string('id'),
                        Schema::string('mime_type'),
                        Schema::string('alt_text'),
                        Schema::string('url')
                    )
                    ->nullable(),
                CollectionCategoryListItemSchema::create('parent'),
                Schema::array('children')
                    ->items(CollectionCategoryListItemSchema::create()),
                Schema::array('category_taxonomies')
                    ->items(TaxonomyCategorySchema::create()),
                Schema::string('created_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable(),
                Schema::string('updated_at')
                    ->format(Schema::FORMAT_DATE_TIME)
                    ->nullable()
            );
    }
}
