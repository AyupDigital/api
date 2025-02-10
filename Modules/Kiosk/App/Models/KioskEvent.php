<?php

declare(strict_types=1);

namespace Modules\Kiosk\App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackRoute;

class KioskEvent extends BaseModel
{
    use Notifiable;

    protected $fillable = [
        'session_id', 'type', 'device_name', 'data', 'date_time'
    ];

    protected $casts = [
        'data' => 'json',
        'date_time' => 'datetime'
    ];

    /**
     * Route notifications for the Slack channel.
     */
    public function routeNotificationForSlack(Notification $notification): mixed
    {
        return SlackRoute::make(config('kiosk.slack.channel'), config('kiosk.slack.bot_user_oauth_token'));
    }
}