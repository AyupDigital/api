<?php

declare(strict_types=1);

namespace Modules\Kiosk\App\Http\Controllers\KioskNotification;

use App\Models\Service;
use Illuminate\Http\Request;
use Modules\Kiosk\App\Models\KioskNotification;
use Modules\Kiosk\App\Services\ClickSendService;

class EmailController
{
    public function __invoke(Request $request)
    {
        // Validate phone number
        $request->validate([
            'email_address' => 'required|string|email',
            'service_ids' => 'required|array|min:1',
            'device_id' => 'required|string',
        ]);
        // Fetch Services
        $services = Service::query()->whereIn('id', $request->input('service_ids'))->with('organisation')->get();

        // Build & Send SMS
        $message = '
            Hello,<br>
            Thanks for using the '.config('kisok.deploymentName', 'Kiosk').' at '.$request->input('device_id').". As requested, here's information on your shortlisted services:<br>
        ";
        foreach ($services as $key => $value) {
            $message .= '<br>Service '.$key + 1 ." of {$services->count()}:<br>";
            $message .= '---<br>';
            $message .= "{$value->name} - provided by {$value->organisation->name}<br>";
            if ($value->contact_phone) {
                $message .= "Phone: {$value->contact_phone}<br>";
            }
            if ($value->contact_email) {
                $message .= "Email: {$value->contact_email}<br>";
            }
            if ($value->url) {
                $message .= "Website: {$value->url}<br>";
            }
            $message .= 'More info: '.config('kiosk.frontendUrl')."/service/{$value->slug}<br>";
        }

        if (config('kiosk.survey')) {
            $message .= 'Please consider giving us feedback on your experience which will take no longer than 1 minute via: '.config('kiosk.surveyUrl');
        }

        $service = new ClickSendService;
        $success = $service->sendEmail($request->input('email_address'), 'Your Kiosk Results', $message);

        KioskNotification::query()
            ->create([
                'type' => 'email',
                'email' => $request->input('email_address'),
                'service_ids' => $request->input('service_ids'),
                'success' => $success,
            ]);
    }
}
