<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\CollectionTaxonomy;
use App\Models\Service;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $collection = Collection::query()->with('children')->find('089e6f18-0985-4557-b766-eae842cdab9e');
//        dd(Collection::query()
//            ->whereIn('slug', ['jae-test-child'])
//            ->with('children')
//            ->get()
//            ->flatMap(function ($collection) {
//                return $collection->children->pluck('name')->prepend($collection->name);
//            })
//            ->unique()
//            ->all());
        $service = Service::query()->find('4dbe9bd1-2f2c-426f-a9f6-00510a46016d');
        $taxonomyIds = $service->serviceTaxonomies()
            ->pluck('taxonomy_id')
            ->toArray();
        $collectionIds = CollectionTaxonomy::query()
            ->whereIn('taxonomy_id', $taxonomyIds)
            ->pluck('collection_id');

//        dd($collectionIds);
//        $collections = ;
        $collections = Collection::query()->whereIn('id', $collectionIds->toArray())
            ->where('type', Collection::TYPE_CATEGORY)
            ->with('children') // Load only direct children
            ->get()
            ->flatMap(function ($collection) use ($collectionIds) {
                // Check if this service belongs to the current collection
                $isDirectlyAssociated = $collectionIds->contains('id', $collection->id);

                // Always include the current collection's name
                $categories = collect([$collection->name]);

                // If the service is NOT directly associated with the parent, do not include its children
                if ($isDirectlyAssociated) {
                    $categories = $categories->merge($collection->children->pluck('name'));
                }

                return $categories;
            })
            ->filter(function ($name) {
                // Ensure no null or empty names
                return !empty($name);
            })
            ->unique() // Remove duplicates
            ->values() // Reset array keys
            ->toArray();

        dd($collections);

    }
}
