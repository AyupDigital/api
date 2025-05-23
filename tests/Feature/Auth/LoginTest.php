<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Sms\OtpLoginCode\UserSms;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LoginTest extends TestCase
{
    /**
     * @test
     */
    public function user_can_login200(): void
    {
        Config::set('local.otp_enabled', false);

        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(Response::HTTP_FOUND);
    }

    public function test_otp_sms_sent_to_user(): void
    {
        Config::set('local.otp_enabled', true);

        Queue::fake();

        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        Queue::assertPushedOn(config('queue.queues.notifications', 'default'), UserSms::class);
        Queue::assertPushed(UserSms::class, function (UserSms $sms) {
            $this->assertArrayHasKey('OTP_CODE', $sms->values);

            return true;
        });
    }
}
