<?php

namespace Tests\Unit\Actions;

use App\Actions\StoreServiceLocationAction;
use App\DataTransferObjects\ServiceLocationRequestObject;
use App\Models\Location;
use App\Models\Service;
use Tests\TestCase;

class StoreServiceLocationActionTest extends TestCase
{
    public function test_store_service_location_action_stores_service_location(): void {
        // Arrange
        $service = Service::factory()->create();
        $location = Location::factory()->create();
        $requestObject = new ServiceLocationRequestObject(
            name: 'Test Location',
            locationId: $location->id,
            regularOpeningHours: [
                [
                    'frequency' => 'weekly',
                    'weekday' => 1,
                    'opens_at' => '09:00:00',
                    'closes_at' => '17:00:00',
                ],
            ],
            holidayOpeningHours: null,
            imageFileId: null,
        );

        // Act
        $serviceLocation = (new StoreServiceLocationAction())->handle($service, $requestObject);

        // Assert
        $this->assertDatabaseHas('service_locations', [
            'name' => 'Test Location',
            'location_id' => $location->id,
        ]);
        $this->assertDatabaseHas('regular_opening_hours', [
            'service_location_id' => $serviceLocation->id,
            'frequency' => 'weekly',
            'weekday' => 1,
            'opens_at' => '09:00',
            'closes_at' => '17:00',
        ]);
    }
}
