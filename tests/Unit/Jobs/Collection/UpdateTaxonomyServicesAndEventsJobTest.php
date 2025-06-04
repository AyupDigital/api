<?php

namespace Tests\Unit\Jobs\Collection;

use App\Jobs\Collection\UpdateTaxonomyServicesAndEventsJob;
use App\Models\Service;
use App\Models\Taxonomy;
use App\Models\OrganisationEvent;
use Tests\TestCase;
use Tests\UsesElasticsearch;

class UpdateTaxonomyServicesAndEventsJobTest extends TestCase implements UsesElasticsearch
{
    public function test_it_makes_services_and_events_searchable(): void
    {// Create test taxonomies
        $taxonomy1 = Taxonomy::factory()->create();
        $taxonomy2 = Taxonomy::factory()->create();

        $service1 = Service::factory()->create();
        $service1->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy1->id]);
        
        $service2 = Service::factory()->create();
        $service2->serviceTaxonomies()->create(['taxonomy_id' => $taxonomy2->id]);

        $event1 = OrganisationEvent::factory()->create();
        $event1->syncTaxonomyRelationships(collect([$taxonomy1]));
        
        $event2 = OrganisationEvent::factory()->create();
        $event2->syncTaxonomyRelationships(collect([$taxonomy2]));

        Service::query()->unsearchable();
        OrganisationEvent::query()->unsearchable();

        $job = new UpdateTaxonomyServicesAndEventsJob([$taxonomy1->id, $taxonomy2->id]);
        $job->handle();

        $searchResponse = Service::search('')->raw();
        $this->assertEquals(2, $searchResponse->total());
        
        $searchResponse = OrganisationEvent::search('')->raw();
        $this->assertEquals(2, $searchResponse->total());
    }
} 