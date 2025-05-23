<?php

namespace App\Models;

use App\Emails\Email;
use App\Models\Mutators\NotificationMutators;
use App\Models\Relationships\NotificationRelationships;
use App\Models\Scopes\NotificationScopes;
use App\Notifications\Notifiable;
use App\Sms\Sms;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;

class Notification extends Model
{
    use NotificationMutators;
    use NotificationRelationships;
    use NotificationScopes;

    const CHANNEL_EMAIL = 'email';

    const CHANNEL_SMS = 'sms';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function sendEmail(Email $email, ?Notifiable $notifiable = null)
    {
        DB::transaction(function () use ($email, $notifiable) {
            $query = $notifiable ? $notifiable->notifications() : static::query();

            // Log a notification for the email in the database.
            $notification = $query->create([
                'channel' => static::CHANNEL_EMAIL,
                'recipient' => $email->to,
                'message' => 'Pending to be sent. Content will be filled once sent.',
            ]);

            // Add the email as a job on the queue to be sent.
            $email->notification = $notification;

            app(Dispatcher::class)->dispatch($email);
        });
    }

    public static function sendSms(Sms $sms, ?Notifiable $notifiable = null)
    {
        DB::transaction(function () use ($sms, $notifiable) {
            $query = $notifiable ? $notifiable->notifications() : static::query();

            // Log a notification for the SMS in the database.
            $notification = $query->create([
                'channel' => static::CHANNEL_SMS,
                'recipient' => $sms->to,
                'message' => 'Pending to be sent. Content will be filled once sent.',
            ]);

            // Add the SMS as a job on the queue to be sent.
            $sms->notification = $notification;
            app(Dispatcher::class)->dispatch($sms);
        });
    }
}
