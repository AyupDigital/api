<?php

namespace Tests\Feature\Search;

use App\Models\Location;
use Tests\TestCase;

class LocationsTest extends TestCase
{
    public function beforeEach(): void
    {
        Location::factory(20)->create();
    }

    public function test_search_locations()
    {
        $response = $this->post('/core/v1/search/locations?query=London');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'address_line_1',
                        'address_line_2',
                        'city',
                        'postcode',
                        'country',
                    ],
                ],
            ]);
    }
    public function test_search_locations_with_pagination()
    {
        $response = $this->postJson('/core/v1/search/locations?query=London&page=1&per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'address_line_1',
                        'address_line_2',
                        'city',
                        'postcode',
                        'country',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }
    public function test_search_locations_with_invalid_query()
    {
        $response = $this->postJson('/core/v1/search/locations');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }
    public function test_search_locations_with_invalid_page()
    {
        $response = $this->postJson('/core/v1/search/locations?query=London&page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }
    public function test_search_locations_with_invalid_per_page()
    {
        $response = $this->postJson('/core/v1/search/locations?query=London&per_page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
    public function test_search_locations_with_no_results()
    {
        $response = $this->postJson('/core/v1/search/locations?query=NonExistentLocation');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    }
}
