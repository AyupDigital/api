<?php

namespace App\Listeners\Notifications;

use App\Emails\UserCreated\NotifyUserEmail;
use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

class UserCreated
{
    /**
     * Handle the event.
     */
    public function handle(EndpointHit $event): void
    {
        // Only handle specific endpoint events.
        if ($event->isntFor(User::class, Audit::ACTION_CREATE)) {
            return;
        }

        $this->notifyUser($event->getModel());
    }

    protected function notifyUser(User $user)
    {
        $user->sendEmail(new NotifyUserEmail($user->email, [
            'NAME' => $user->first_name
        ]));
    }
}
