<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_user_personal_information_is_redacted_on_soft_delete(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'redacted@redacted.com',
            'phone' => 'redacted',
        ]);
    }

    public function test_user_personal_information_is_not_redacted_on_force_delete(): void
    {
        $user = User::factory()->create();

        $user->forceDelete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
