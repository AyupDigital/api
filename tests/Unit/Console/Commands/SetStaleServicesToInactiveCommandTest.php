<?php

namespace Tests\Unit\Console\Commands;

use App\Models\Service;
use Tests\TestCase;

class SetStaleServicesToInactiveCommandTest extends TestCase
{
    public function test_command_sets_stale_services_to_inactive(): void
    {
        $service = Service::factory()->create([
            'last_modified_at' => now()->subDays(10)->subYear(),
            'status' => Service::STATUS_ACTIVE,
        ]);
        
        $this->artisan('app:set-stale-services-to-inactive')
            ->expectsOutput('Stale services have been disabled.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => Service::STATUS_INACTIVE,
        ]);
    }
}