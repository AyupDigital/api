<?php

namespace Tests\Unit\Actions;

use App\Actions\SetStaleServicesToInactiveAction;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SetStaleServicesToInactiveActionTest extends TestCase
{
    public function test_action_marks_stale_service_as_inactive(): void
    {
        $service = Service::factory()->create([
            'last_modified_at' => now()->subDays(10)->subYear(),
            'status' => Service::STATUS_ACTIVE,
        ]);

        (new SetStaleServicesToInactiveAction())->handle(CarbonImmutable::now()->subYear());

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => Service::STATUS_INACTIVE,
        ]);
    }
}