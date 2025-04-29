<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Service;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DisableStaleServicesTest extends TestCase
{
    public function test_disable_stale_services(): void
    {
        $service = Service::factory()->create([
            'last_modified_at' => now()->subDays(12),
            'status' => Service::STATUS_ACTIVE,
        ]);

        $user = User::factory()->create();
        $user->makeSuperAdmin();

        Passport::actingAs($user);

        $response = $this->json('PUT', '/core/v1/services/disable-stale', [
            'last_modified_at' => now()->subDays(10)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Stale services have been disabled.',
        ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => Service::STATUS_INACTIVE,
        ]);
    }

    public function test_disable_stale_services_with_invalid_date(): void
    {
        $user = User::factory()->create();
        $user->makeSuperAdmin();

        Passport::actingAs($user);

        $response = $this->json('PUT', '/core/v1/services/disable-stale', [
            'last_modified_at' => 'invalid-date',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['last_modified_at']);
    }

    public function test_endpoint_only_accessible_to_super_admins(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user);

        $response = $this->json('PUT', '/core/v1/services/disable-stale', [
            'last_modified_at' => now()->subDays(10)->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
        
        $service = Service::factory()->create();
        $user->makeServiceAdmin($service);
        $response = $this->json('PUT', '/core/v1/services/disable-stale', [
            'last_modified_at' => now()->subDays(10)->format('Y-m-d'),
        ]);
        $response->assertStatus(403);

        $organisation = Organisation::factory()->create();
        $user->makeOrganisationAdmin($organisation);

        $response = $this->json('PUT', '/core/v1/services/disable-stale', [
            'last_modified_at' => now()->subDays(10)->format('Y-m-d'),
        ]);
        $response->assertStatus(403);

        $user->makeSuperAdmin();
        $response = $this->json('PUT', '/core/v1/services/disable-stale', [
            'last_modified_at' => now()->subDays(10)->format('Y-m-d'),
        ]);
        $response->assertStatus(200);
    }
}