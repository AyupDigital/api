<?php

namespace Modules\Kiosk\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Kiosk\App\Models\KioskSession;
use Modules\Kiosk\App\Notifications\NotifyAdminsOfError;

class KioskEventController
{
  public function store(Request $request)
  {
    if (!$request->has('events')) {
      abort(400);
    }

    // TODO: This should be a DTO.
    $events = collect($request->get('events'))->sortBy('date_time');

    $startEvent = $events->first();
    $lastEvent = $events->last();
    $diffInSeconds = Carbon::parse($lastEvent['dateTime'])->diffInSeconds(Carbon::parse($startEvent['dateTime']));
    $isCompleted = $events->contains('type', '=', 'session_timeout') || $events->contains('type', '=', 'session_end');
    $hasDemographic = $events->contains('demographic', '=', 'demographic');
    $hasSharedShortlist = $events->contains('type', '=', 'notification');
    $hasFeedback = $events->contains('type', '=', 'feedback');
    $deviceId = $request->get('device_id');

    DB::transaction(function () use ($events, $startEvent, $lastEvent, $diffInSeconds, $isCompleted, $hasDemographic, $hasSharedShortlist, $hasFeedback, $deviceId) {
      $session = KioskSession::create([
        'start_at' => $startEvent['dateTime'],
        'end_at' => $lastEvent['dateTime'],
        'duration' => $diffInSeconds,
        'status' => $isCompleted ? 'complete' : 'incomplete',
        'device_id' => $deviceId,
        'has_demographic' => $hasDemographic,
        'has_shared_shortlist' => $hasSharedShortlist,
        'has_feedback' => $hasFeedback,
      ]);

      $events->each(function ($event) use ($session) {
        $event = $session->kioskEvents()->create([
          'kiosk_session_id' => $session->id,
          'type' => $event['type'],
          'name' => $event['name'],
          'group' => $event['group'],
          'value' => $event['value'],
          'logged_at' => $event['date_time']
        ]);

        if ($event['type'] === 'error') {
          Notification::sendNow($event, new NotifyAdminsOfError($event));
        }
      });
    });

    return response(status: 201);
  }
}

