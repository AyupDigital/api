<?php

namespace Tests\Unit\Listeners\Notifications;

use App\Emails\ServiceCreated\NotifyGlobalAdminEmail;
use App\Events\EndpointHit;
use App\Listeners\Notifications\ServiceCreated;
use App\Models\Organisation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServiceCreatedTest extends TestCase
{
    public function test_email_sent_to_global_admin_email(): void
    {
        Queue::fake();

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($organisation);

        $request = Request::create('')->setUserResolver(function () use ($user) {
            return $user;
        });

        $service = Service::factory()->create(['organisation_id' => $organisation->id]);

        $event = EndpointHit::onCreate($request, "Created service [{$service->id}]", $service);
        $listener = new ServiceCreated;
        $listener->handle($event);

        Queue::assertPushedOn(config('queue.queues.notifications', 'default'), NotifyGlobalAdminEmail::class);
        Queue::assertPushed(
            NotifyGlobalAdminEmail::class,
            function (NotifyGlobalAdminEmail $email) use ($service, $user) {
                $this->assertEquals(config('local.global_admin.email'), $email->to);
                $this->assertEquals(
                    config('gov_uk_notify.notifications_template_ids.service_created.notify_global_admin.email'),
                    $email->templateId
                );
                $this->assertEquals('emails.service.created.notify_global_admin.subject', $email->getSubject());
                $this->assertEquals('emails.service.created.notify_global_admin.content', $email->getContent());

                $this->assertEquals($service->name, $email->values['SERVICE_NAME']);
                $this->assertEquals($user->full_name, $email->values['ORGANISATION_ADMIN_NAME']);
                $this->assertEquals($service->intro, $email->values['SERVICE_INTRO']);
                $this->assertEquals($service->organisation->name, $email->values['ORGANISATION_NAME']);
                $this->assertEquals($user->email, $email->values['ORGANISATION_ADMIN_EMAIL']);
                $this->assertEquals(backend_uri("/services/{$service->id}"), $email->values['SERVICE_URL']);

                return true;
            }
        );
    }

    public function test_email_not_sent_to_global_admin_email_when_created_by_global_admin(): void
    {
        Queue::fake();

        $organisation = Organisation::factory()->create();
        $user = User::factory()->create()->makeGlobalAdmin();

        $request = Request::create('')->setUserResolver(function () use ($user) {
            return $user;
        });

        $service = Service::factory()->create(['organisation_id' => $organisation->id]);

        $event = EndpointHit::onCreate($request, "Created service [{$service->id}]", $service);
        $listener = new ServiceCreated;
        $listener->handle($event);

        Queue::assertNotPushed(NotifyGlobalAdminEmail::class);
    }
}
