<?php

namespace Modules\Kiosk\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Facades\Notification;
use Modules\Kiosk\App\Models\KioskEvent;
use Modules\Kiosk\App\Notifications\NotifyAdminsOfError;

class KioskEventController
{
    public function store(Request $request) {
        if (!$request->has('events')) {
            abort(400);
        }

        $events = $request->get('events');
        $session = uuid();

        foreach ($events as $event) {
            $kioskEvent = KioskEvent::query()->create(
                [
                    'session_id' => $session,
                    'device_name' => $event['deviceId'],
                    'type' => $event['type'],
                    'data' => $event['payload'],
                    'date_time' => $event['dateTime']
                ]
            );

            if ($event['type'] === 'error') {
                Notification::sendNow($kioskEvent, new NotifyAdminsOfError($kioskEvent));
            }
        }

        return response(status: 201);
    }
}