<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UsersTest extends TestCase
{
    /*
     * ==================================================
     * List all the users.
     * ==================================================
     */

    /**
     * @test
     */
    public function guest_cannot_list_them(): void
    {
        $response = $this->json('GET', '/core/v1/users');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_can_list_them(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $response = $this->json('GET', '/core/v1/users', ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_listed(): void
    {
        $this->fakeEvents();

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $this->json('GET', '/core/v1/users');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getUser()->id === $user->id);
        });
    }

    /**
     * @test
     */
    public function service_worker_can_filter_by_highest_role_name(): void
    {
        $service = Service::factory()->create();
        $serviceAdmin = User::factory()->create()->makeServiceAdmin($service);
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $serviceAdminRoleName = Role::serviceAdmin()->name;

        $response = $this->json('GET', "/core/v1/users?filter[highest_role]={$serviceAdminRoleName}");

        $response->assertStatus(Response::HTTP_OK);
        $data = $this->getResponseContent($response);

        $this->assertEquals(1, count($data['data']));
        $this->assertEquals($serviceAdmin->id, $data['data'][0]['id']);
    }

    /**
     * @test
     */
    public function service_worker_can_sort_by_highest_role(): void
    {
        $service = Service::factory()->create();
        $serviceAdmin = User::factory()->create()->makeServiceAdmin($service);
        $serviceWorker = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($serviceWorker);

        $response = $this->json('GET', '/core/v1/users?sort=-highest_role');

        $response->assertStatus(Response::HTTP_OK);
        $data = $this->getResponseContent($response);

        $this->assertEquals($serviceAdmin->id, $data['data'][1]['id']);
        $this->assertEquals($serviceWorker->id, $data['data'][0]['id']);
    }

    /**
     * @test
     */
    public function service_worker_can_filter_by_at_organisation(): void
    {
        $organisation = Organisation::factory()->create();
        $organisationAdmin = User::factory()->create()->makeOrganisationAdmin($organisation);
        $user = User::factory()->create()->makeServiceWorker(
            Service::factory()->create()
        );
        Passport::actingAs($user);

        $response = $this->json('GET', "/core/v1/users?filter[at_organisation]={$organisation->id}");

        $response->assertStatus(Response::HTTP_OK);
        $data = $this->getResponseContent($response);

        $this->assertEquals(1, count($data['data']));
        $this->assertEquals($organisationAdmin->id, $data['data'][0]['id']);
    }

    /**
     * @test
     */
    public function service_worker_can_filter_by_at_service(): void
    {
        $service = Service::factory()->create();
        $serviceAdmin = User::factory()->create()->makeServiceAdmin($service);
        $user = User::factory()->create()->makeServiceWorker(
            Service::factory()->create()
        );
        Passport::actingAs($user);

        $response = $this->json('GET', "/core/v1/users?filter[at_service]={$service->id}");

        $response->assertStatus(Response::HTTP_OK);
        $data = $this->getResponseContent($response);

        $this->assertEquals(1, count($data['data']));
        $this->assertEquals($serviceAdmin->id, $data['data'][0]['id']);
    }

    /**
     * @test
     */
    public function service_worker_can_filter_by_at_organisation_and_includes_service_workers(): void
    {
        $service = Service::factory()->create();
        $serviceWorker = User::factory()->create()->makeServiceWorker($service);
        $user = User::factory()->create()->makeServiceWorker(
            Service::factory()->create()
        );
        Passport::actingAs($user);

        $response = $this->json('GET', "/core/v1/users?filter[at_organisation]={$service->organisation_id}");

        $response->assertStatus(Response::HTTP_OK);
        $data = $this->getResponseContent($response);

        $this->assertEquals(1, count($data['data']));
        $this->assertEquals($serviceWorker->id, $data['data'][0]['id']);
    }

    /**
     * @test
     */
    public function service_worker_can_filter_by_at_organisation_and_excludes_global_admins(): void
    {
        $organisation = Organisation::factory()->create();
        $organisationAdmin = User::factory()->create()->makeOrganisationAdmin($organisation);
        // This user shouldn't show up in the results.
        User::factory()->create()->makeGlobalAdmin();
        $user = User::factory()->create()->makeServiceWorker(
            Service::factory()->create()
        );
        Passport::actingAs($user);

        $response = $this->json('GET', "/core/v1/users?filter[at_organisation]={$organisation->id}");

        $response->assertStatus(Response::HTTP_OK);
        $data = $this->getResponseContent($response);

        $this->assertEquals(1, count($data['data']));
        $this->assertEquals($organisationAdmin->id, $data['data'][0]['id']);
    }

    /*
     * ==================================================
     * Create a user.
     * ==================================================
     */

    /*
     * Guest Invoked.
     */
    /**
     * @test
     */
    public function guest_cannot_create_one(): void
    {
        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_SUPER_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /*
     * Service Worker Invoked.
     */
    /**
     * @test
     */
    public function service_worker_cannot_create_one(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_SUPER_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /*
     * Service Admin Invoked.
     */
    /**
     * @test
     */
    public function service_admin_cannot_create_service_worker_for_another_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => Service::factory()->create()->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function service_admin_can_create_service_worker_for_their_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertEquals(1, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function service_admin_cannot_create_service_admin_for_another_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => Service::factory()->create()->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function service_admin_can_create_service_admin_for_their_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertTrue($createdUser->isServiceAdmin($service));
        $this->assertEquals(2, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function service_admin_cannot_create_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => $service->organisation->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /*
     * Organisation Admin Invoked.
     */
    /**
     * @test
     */
    public function organisation_admin_cannot_create_service_worker_for_another_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => Service::factory()->create()->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_service_worker_for_their_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertEquals(1, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_create_service_admin_for_another_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => Service::factory()->create()->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_service_admin_for_their_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertTrue($createdUser->isServiceAdmin($service));
        $this->assertEquals(2, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_create_organisation_admin_for_another_organisation(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => Organisation::factory()->create()->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function organisation_admin_can_create_organisation_admin_for_their_organisation(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => $service->organisation->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertTrue($createdUser->isServiceAdmin($service));
        $this->assertTrue($createdUser->isOrganisationAdmin($service->organisation));
        $this->assertEquals(3, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_create_content_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_CONTENT_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_create_global_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_GLOBAL_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /*
     * Content Admin Invoked.
     */
    /**
     * @test
     */
    public function content_admin_cannot_create_service_worker(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_create_service_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_create_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => $service->organisation->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_create_content_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_CONTENT_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_create_global_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_GLOBAL_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_create_super_admin(): void
    {
        $user = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_SUPER_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /*
     * Global Admin Invoked.
     */
    /**
     * @test
     */
    public function global_admin_cannot_create_service_worker(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_create_service_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_create_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => $service->organisation->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_create_content_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_CONTENT_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_create_global_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_GLOBAL_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_create_super_admin(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_SUPER_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /*
     * Super Admin Invoked.
     */

    /**
     * @test
     */
    public function super_admin_can_create_service_worker(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertEquals(1, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function super_admin_can_create_service_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertTrue($createdUser->isServiceAdmin($service));
        $this->assertEquals(2, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function super_admin_can_create_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => $service->organisation->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertTrue($createdUser->isServiceAdmin($service));
        $this->assertTrue($createdUser->isOrganisationAdmin($service->organisation));
        $this->assertEquals(3, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function super_admin_can_create_content_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_CONTENT_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertFalse($createdUser->isServiceWorker($service));
        $this->assertFalse($createdUser->isServiceAdmin($service));
        $this->assertFalse($createdUser->isOrganisationAdmin($service->organisation));
        $this->assertTrue($createdUser->isContentAdmin());
        $this->assertFalse($createdUser->isGlobalAdmin());
        $this->assertEquals(1, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function super_admin_can_create_global_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_GLOBAL_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertTrue($createdUser->isServiceAdmin($service));
        $this->assertTrue($createdUser->isOrganisationAdmin($service->organisation));
        $this->assertFalse($createdUser->isContentAdmin());
        $this->assertTrue($createdUser->isGlobalAdmin());
        $this->assertEquals(4, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function super_admin_can_create_super_admin(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_SUPER_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_CREATED);
        $createdUserId = json_decode($response->getContent(), true)['data']['id'];
        $createdUser = User::findOrFail($createdUserId);
        $this->assertTrue($createdUser->isServiceWorker($service));
        $this->assertTrue($createdUser->isServiceAdmin($service));
        $this->assertTrue($createdUser->isOrganisationAdmin($service->organisation));
        $this->assertTrue($createdUser->isContentAdmin());
        $this->assertTrue($createdUser->isGlobalAdmin());
        $this->assertTrue($createdUser->isSuperAdmin());
        $this->assertEquals(6, $createdUser->roles()->count());
    }

    /**
     * @test
     */
    public function super_admin_can_create_super_admin_with_soft_deleted_users_email(): void
    {
        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $deletedUser = User::factory()->create(['email' => 'test@example.com'])->makeSuperAdmin();
        $deletedUser->delete();

        $response = $this->json('POST', '/core/v1/users', [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => 'test@example.com',
            'phone' => random_uk_mobile_phone(),
            'password' => 'Pa$$w0rd',
            'roles' => [
                ['role' => Role::NAME_SUPER_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas(table(User::class), [
            'id' => $deletedUser->id,
            'email' => 'test@example.com',
        ]);
        $this->assertDatabaseMissing(table(User::class), [
            'id' => $deletedUser->id,
            'email' => 'test@example.com',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas(table(User::class), [
            'email' => 'test@example.com',
            'deleted_at' => null,
        ]);
    }

    /*
     * Audit.
     */

    /**
     * @test
     */
    public function audit_created_when_created(): void
    {
        $this->fakeEvents();

        $user = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/users', $this->getCreateUserPayload([
            ['role' => Role::NAME_SUPER_ADMIN],
        ]));

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user, $response) {
            return ($event->getAction() === Audit::ACTION_CREATE) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $this->getResponseContent($response)['data']['id']);
        });
    }

    /*
     * ==================================================
     * Get a specific user.
     * ==================================================
     */

    /**
     * @test
     */
    public function guest_cannot_view_one(): void
    {
        Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        $response = $this->json('GET', "/core/v1/users/{$user->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_can_view_self(): void
    {
        $service = Service::factory()->create();
        $user1 = User::factory()->create()->makeServiceWorker($service);
        $user2 = User::factory()->create()->makeServiceWorker($service);
        $user3 = User::factory()->create()->makeServiceAdmin($service);
        $user4 = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $user5 = User::factory()->create()->makeContentAdmin();
        $user6 = User::factory()->create()->makeGlobalAdmin();
        $user7 = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user1);

        $response = $this->json('GET', "/core/v1/users/{$user1->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $user1->id,
            'first_name' => $user1->first_name,
            'last_name' => $user1->last_name,
            'email' => $user1->email,
            'phone' => $user1->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response = $this->json('GET', "/core/v1/users/{$user2->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user3->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user5->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user6->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user7->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_can_view_their_service_users(): void
    {
        $service = Service::factory()->create();
        $user1 = User::factory()->create()->makeServiceAdmin($service);
        $user2 = User::factory()->create()->makeServiceWorker($service);
        $user3 = User::factory()->create()->makeServiceAdmin($service);
        $user4 = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $user5 = User::factory()->create()->makeContentAdmin();
        $user6 = User::factory()->create()->makeGlobalAdmin();
        $user7 = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user1);

        $response = $this->json('GET', "/core/v1/users/{$user1->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user2->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user3->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user5->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user6->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user7->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_can_view_their_organisation_users(): void
    {
        $service = Service::factory()->create();
        $user1 = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $user2 = User::factory()->create()->makeServiceWorker($service);
        $user3 = User::factory()->create()->makeServiceAdmin($service);
        $user4 = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $user5 = User::factory()->create()->makeContentAdmin();
        $user6 = User::factory()->create()->makeGlobalAdmin();
        $user7 = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user1);

        $response = $this->json('GET', "/core/v1/users/{$user1->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user2->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user3->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user5->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user6->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user7->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_can_view_self(): void
    {
        $service = Service::factory()->create();
        $user1 = User::factory()->create()->makeContentAdmin();
        $user2 = User::factory()->create()->makeServiceWorker($service);
        $user3 = User::factory()->create()->makeServiceAdmin($service);
        $user4 = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $user5 = User::factory()->create()->makeContentAdmin();
        $user6 = User::factory()->create()->makeGlobalAdmin();
        $user7 = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user1);

        $response = $this->json('GET', "/core/v1/users/{$user1->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user2->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user3->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user5->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user6->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user7->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_can_view_self(): void
    {
        $service = Service::factory()->create();
        $user1 = User::factory()->create()->makeGlobalAdmin();
        $user2 = User::factory()->create()->makeServiceWorker($service);
        $user3 = User::factory()->create()->makeServiceAdmin($service);
        $user4 = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $user5 = User::factory()->create()->makeContentAdmin();
        $user6 = User::factory()->create()->makeGlobalAdmin();
        $user7 = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user1);

        $response = $this->json('GET', "/core/v1/users/{$user1->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user2->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user3->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user5->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user6->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $response = $this->json('GET', "/core/v1/users/{$user7->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_view_all_users(): void
    {
        $service = Service::factory()->create();
        $user1 = User::factory()->create()->makeSuperAdmin();
        $user2 = User::factory()->create()->makeServiceWorker($service);
        $user3 = User::factory()->create()->makeServiceAdmin($service);
        $user4 = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $user5 = User::factory()->create()->makeContentAdmin();
        $user6 = User::factory()->create()->makeGlobalAdmin();
        $user7 = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($user1);

        $response = $this->json('GET', "/core/v1/users/{$user1->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user2->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user3->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user4->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user5->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user6->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->json('GET', "/core/v1/users/{$user7->id}", ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function audit_created_when_viewed(): void
    {
        $this->fakeEvents();

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $this->json('GET', "/core/v1/users/{$user->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $user->id);
        });
    }

    /*
     * ==================================================
     * Get the logged in user.
     * ==================================================
     */

    /**
     * @test
     */
    public function guest_cannot_view_logged_in_user(): void
    {
        Service::factory()->create();

        $response = $this->json('GET', '/core/v1/users/users');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_can_view_logged_in_user(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $response = $this->json('GET', '/core/v1/users/user', ['include' => 'user-roles']);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function audit_created_when_logged_in_user_viewed(): void
    {
        $this->fakeEvents();

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($user);

        $this->json('GET', '/core/v1/users/user');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($user) {
            return ($event->getAction() === Audit::ACTION_READ) &&
                ($event->getUser()->id === $user->id) &&
                ($event->getModel()->id === $user->id);
        });
    }

    /*
     * ==================================================
     * Update a specific user.
     * ==================================================
     */

    /*
     * Guest Invoked.
     */
    /**
     * @test
     */
    public function guest_cannot_update_one(): void
    {
        Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", $this->getCreateUserPayload([
            ['role' => Role::NAME_SERVICE_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /*
     * Service Worker Invoked.
     */
    /**
     * @test
     */
    public function service_worker_cannot_update_one(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($invoker);

        $user = User::factory()->create()->makeSuperAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", $this->getCreateUserPayload([
            ['role' => Role::NAME_SUPER_ADMIN],
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_worker_can_update_self(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($invoker);

        $response = $this->json('PUT', "/core/v1/users/{$invoker->id}", $this->getCreateUserPayload([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]));

        $response->assertStatus(Response::HTTP_OK);
    }

    /*
     * Service Admin Invoked.
     */
    /**
     * @test
     */
    public function service_admin_can_update_service_worker(): void
    {
        $invoker = User::factory()->create()->makeServiceAdmin(Service::factory()->create());
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function service_admin_can_update_service_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($invoker);

        $subject = User::factory()->create()->makeServiceAdmin($service);

        $response = $this->json('PUT', "/core/v1/users/{$subject->id}", [
            'first_name' => $subject->first_name,
            'last_name' => $subject->last_name,
            'email' => $subject->email,
            'phone' => $subject->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $subject->first_name,
            'last_name' => $subject->last_name,
            'email' => $subject->email,
            'phone' => $subject->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function service_admin_cannot_update_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($invoker);

        $subject = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        $response = $this->json('PUT', "/core/v1/users/{$subject->id}", [
            'first_name' => $subject->first_name,
            'last_name' => $subject->last_name,
            'email' => $subject->email,
            'phone' => $subject->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /*
     * Organisation Admin Invoked.
     */
    /**
     * @test
     */
    public function organisation_admin_can_update_service_worker(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $user = User::factory()->create()->makeServiceWorker($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function organisation_admin_can_update_service_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $user = User::factory()->create()->makeServiceAdmin($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function organisation_admin_can_update_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => $service->id,
            ],
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => $service->organisation->id,
            ],
        ]);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_update_content_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $user = User::factory()->create()->makeContentAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_CONTENT_ADMIN,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_update_global_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $user = User::factory()->create()->makeGlobalAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
                [
                    'role' => Role::NAME_GLOBAL_ADMIN,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /*
     * Content Admin Invoked.
     */

    /**
     * @test
     */
    public function content_admin_cannot_update_service_worker(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_update_service_admin(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_update_organisation_admin(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_update_content_admin(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeContentAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                ['role' => Role::NAME_CONTENT_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_update_global_admin(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
                ['role' => Role::NAME_GLOBAL_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_update_super_admin(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
                ['role' => Role::NAME_GLOBAL_ADMIN],
                ['role' => Role::NAME_SUPER_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /*
     * Global Admin Invoked.
     */

    /**
     * @test
     */
    public function global_admin_cannot_update_service_worker(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_service_admin(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_organisation_admin(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_content_admin(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeContentAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                ['role' => Role::NAME_CONTENT_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_global_admin(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
                ['role' => Role::NAME_GLOBAL_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_and_organisation_permissions_are_applied_when_updating_to_global_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $service1 = Service::factory()->create();
        $service2 = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service1->organisation);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                ['role' => Role::NAME_GLOBAL_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            ['role' => Role::NAME_GLOBAL_ADMIN],
        ]);

        $this->assertTrue($user->isServiceWorker($service1));
        $this->assertTrue($user->isServiceAdmin($service1));
        $this->assertTrue($user->isOrganisationAdmin($service1->organisation));
        $this->assertTrue($user->isServiceWorker($service2));
        $this->assertTrue($user->isServiceAdmin($service2));
        $this->assertTrue($user->isOrganisationAdmin($service2->organisation));
        $this->assertEquals(7, $user->roles()->count());
    }

    /**
     * @test
     */
    public function global_admin_cannot_update_super_admin(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
                ['role' => Role::NAME_GLOBAL_ADMIN],
                ['role' => Role::NAME_SUPER_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /*
     * Super Admin Invoked.
     */

    /**
     * @test
     */
    public function super_admin_can_update_service_worker(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_update_service_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_update_organisation_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => $service->id,
            ],
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => $service->organisation->id,
            ],
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_update_content_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeContentAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                ['role' => Role::NAME_CONTENT_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        $response->assertJsonFragment([
            ['role' => Role::NAME_CONTENT_ADMIN],
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_update_global_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
                ['role' => Role::NAME_GLOBAL_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_SERVICE_WORKER,
                'service_id' => $service->id,
            ],
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_SERVICE_ADMIN,
                'service_id' => $service->id,
            ],
        ]);
        $response->assertJsonFragment([
            [
                'role' => Role::NAME_ORGANISATION_ADMIN,
                'organisation_id' => $service->organisation->id,
            ],
        ]);
        $response->assertJsonFragment([
            ['role' => Role::NAME_GLOBAL_ADMIN],
        ]);
    }

    /**
     * @test
     */
    public function super_admin_can_update_super_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeSuperAdmin();

        $response = $this->json('PUT', "/core/v1/users/{$user->id}", [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
                ['role' => Role::NAME_GLOBAL_ADMIN],
                ['role' => Role::NAME_SUPER_ADMIN],
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
        $response->assertJsonFragment([
            'role' => Role::NAME_SERVICE_WORKER,
            'service_id' => $service->id,
        ]);
        $response->assertJsonFragment([
            'role' => Role::NAME_SERVICE_ADMIN,
            'service_id' => $service->id,
        ]);
        $response->assertJsonFragment([
            'role' => Role::NAME_ORGANISATION_ADMIN,
            'organisation_id' => $service->organisation->id,
        ]);
        $response->assertJsonFragment([
            ['role' => Role::NAME_GLOBAL_ADMIN],
        ]);
        $response->assertJsonFragment([
            ['role' => Role::NAME_SUPER_ADMIN],
        ]);
    }

    /*
     * Audit.
     */

    /**
     * @test
     */
    public function audit_created_when_updated(): void
    {
        $this->fakeEvents();

        $invoker = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $service = Service::factory()->create();
        $subject = User::factory()->create()->makeSuperAdmin();

        $this->json('PUT', "/core/v1/users/{$subject->id}", [
            'first_name' => $subject->first_name,
            'last_name' => $subject->last_name,
            'email' => $subject->email,
            'phone' => $subject->phone,
            'password' => 'Pa$$w0rd',
            'roles' => [
                [
                    'role' => Role::NAME_SERVICE_WORKER,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_SERVICE_ADMIN,
                    'service_id' => $service->id,
                ],
                [
                    'role' => Role::NAME_ORGANISATION_ADMIN,
                    'organisation_id' => $service->organisation->id,
                ],
                ['role' => Role::NAME_GLOBAL_ADMIN],
                ['role' => Role::NAME_SUPER_ADMIN],
            ],
        ]);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($invoker, $subject) {
            return ($event->getAction() === Audit::ACTION_UPDATE) &&
                ($event->getUser()->id === $invoker->id) &&
                ($event->getModel()->id === $subject->id);
        });
    }

    /*
     * ==================================================
     * Delete a specific user.
     * ==================================================
     */

    /**
     * @test
     */
    public function guest_cannot_delete_service_worker(): void
    {
        $service = Service::factory()->create();
        $subject = User::factory()->create()->makeServiceWorker($service);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_service_worker(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceWorker($service);
        $subject = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_can_delete_service_worker(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceAdmin($service);
        $subject = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted((new User)->getTable(), ['id' => $subject->id]);
    }

    /**
     * @test
     */
    public function content_admin_cannot_delete_service_worker(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeContentAdmin();
        $subject = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_service_worker(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeGlobalAdmin();
        $subject = User::factory()->create()->makeServiceWorker($service);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function guest_cannot_delete_service_admin(): void
    {
        $service = Service::factory()->create();
        $subject = User::factory()->create()->makeServiceAdmin($service);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_service_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceWorker($service);
        $subject = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_can_delete_service_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceAdmin($service);
        $subject = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted((new User)->getTable(), ['id' => $subject->id]);
    }

    /**
     * @test
     */
    public function content_admin_cannot_delete_service_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeContentAdmin();
        $subject = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_service_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeGlobalAdmin();
        $subject = User::factory()->create()->makeServiceAdmin($service);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function guest_cannot_delete_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $subject = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceWorker($service);
        $subject = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_delete_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceAdmin($service);
        $subject = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_can_delete_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $subject = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted((new User)->getTable(), ['id' => $subject->id]);
    }

    /**
     * @test
     */
    public function content_admin_cannot_delete_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeContentAdmin();
        $subject = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_organisation_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeGlobalAdmin();
        $subject = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function guest_cannot_delete_content_admin(): void
    {
        $subject = User::factory()->create()->makeContentAdmin();

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_content_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceWorker($service);
        $subject = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_delete_content_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceAdmin($service);
        $subject = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_delete_content_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $subject = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_delete_content_admin(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        $subject = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_content_admin(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        $subject = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_delete_content_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        $subject = User::factory()->create()->makeContentAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted((new User)->getTable(), ['id' => $subject->id]);
    }

    /**
     * @test
     */
    public function guest_cannot_delete_global_admin(): void
    {
        $subject = User::factory()->create()->makeGlobalAdmin();

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_global_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceWorker($service);
        $subject = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_delete_global_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceAdmin($service);
        $subject = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_delete_global_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $subject = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_delete_global_admin(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        $subject = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_global_admin(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        $subject = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_delete_global_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        $subject = User::factory()->create()->makeGlobalAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted((new User)->getTable(), ['id' => $subject->id]);
    }

    /**
     * @test
     */
    public function guest_cannot_delete_super_admin(): void
    {
        $subject = User::factory()->create()->makeGlobalAdmin();

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function service_worker_cannot_delete_super_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceWorker($service);
        $subject = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function service_admin_cannot_delete_super_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeServiceAdmin($service);
        $subject = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function organisation_admin_cannot_delete_super_admin(): void
    {
        $service = Service::factory()->create();
        $invoker = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $subject = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function content_admin_cannot_delete_super_admin(): void
    {
        $invoker = User::factory()->create()->makeContentAdmin();
        $subject = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function global_admin_cannot_delete_super_admin(): void
    {
        $invoker = User::factory()->create()->makeGlobalAdmin();
        $subject = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function super_admin_can_delete_super_admin(): void
    {
        $invoker = User::factory()->create()->makeSuperAdmin();
        $subject = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $response = $this->json('DELETE', "/core/v1/users/{$subject->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted((new User)->getTable(), ['id' => $subject->id]);
    }

    /**
     * @test
     */
    public function audit_created_when_deleted(): void
    {
        $this->fakeEvents();

        $invoker = User::factory()->create()->makeSuperAdmin();
        $subject = User::factory()->create()->makeSuperAdmin();
        Passport::actingAs($invoker);

        $this->json('DELETE', "/core/v1/users/{$subject->id}");

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) use ($invoker, $subject) {
            return ($event->getAction() === Audit::ACTION_DELETE) &&
                ($event->getUser()->id === $invoker->id) &&
                ($event->getModel()->id === $subject->id);
        });
    }

    /*
     * ==================================================
     * Helpers.
     * ==================================================
     */

    protected function getCreateUserPayload(array $roles): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
            'phone' => random_uk_mobile_phone(),
            'password' => 'Pa$$w0rd',
            'roles' => $roles,
        ];
    }

    protected function getUpdateUserPayload(array $roles): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
            'phone' => random_uk_mobile_phone(),
            'roles' => $roles,
        ];
    }
}
