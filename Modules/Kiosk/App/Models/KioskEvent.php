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
        'type',
        'name',
        'group',
        'value',
        'kiosk_session_id',
        'logged_at',
        'error',
    ];

    protected $casts = [
        'value' => 'json',
        'logged_at' => 'datetime',
    ];

    public function kioskSession()
    {
        return $this->belongsTo(KioskSession::class);
    }

    /**
     * Route notifications for the Slack channel.
     */
    public function routeNotificationForSlack(Notification $notification): mixed
    {
        return SlackRoute::make(config('kiosk.slack.channel'), config('kiosk.slack.bot_user_oauth_token'));
    }
}
