<?php

declare(strict_types=1);

namespace Modules\Kiosk\App\Http\Controllers\KioskNotification;

use App\Models\Service;
use Illuminate\Http\Request;
use Modules\Kiosk\App\Models\KioskNotification;
use Modules\Kiosk\App\Services\ClickSendService;

class SmsController
{
    public function __invoke(Request $request)
    {
        // Validate phone number
        $request->validate([
            'phone_number' => 'required|string|min:10|max:15',
            'service_ids' => 'required|array|min:1',
        ]);
        // Fetch Services
        $services = Service::query()->whereIn('id', $request->input('service_ids'))->with('organisation')->get();

        // Build & Send SMS
        $message = '
            Thanks for using the '.config('kisok.deploymentName', 'Kiosk')." You will receive your shortlisted services in the following text messages:\n
        ";
        $smsService = new ClicksendService;
        $isSuccessful = [];
        $isSuccessful['intro'] = $smsService->sendSms($request->input('phone_number'), $message);

        foreach ($services as $value) {
            $message = "{$value->name} - provided by {$value->organisation->name}\n";
            if ($value->contact_phone) {
                $message .= "Phone: {$value->contact_phone}\n";
            }
            if ($value->contact_email) {
                $message .= "Email: {$value->contact_email}\n";
            }
            if ($value->url) {
                $message .= "Website: {$value->url}\n";
            }
            $message .= 'More info: '.config('kiosk.frontendUrl')."/services/{$value->slug}\n";

            $isSuccessful[$value->id] = $smsService->sendSms($request->input('phone_number'), $message);
        }

        if (config('kiosk.survey')) {
            $message = 'Share your feedback: '.config('kiosk.surveyUrl');
            $isSuccessful['survey'] = $smsService->sendSms($request->input('phone_number'), $message);
        }

        KioskNotification::query()
            ->create([
                'type' => 'sms',
                'phone' => $request->input('phone_number'),
                'service_ids' => $request->input('service_ids'),
                'success' => (collect($isSuccessful)->value(false) ? false : true),
            ]);

        // TODO: Handle Errors

        return response(status: 201);
    }
}
