<?php

declare(strict_types=1);

namespace Modules\Kiosk\App\Http\Controllers\KioskNotification;

use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Modules\Kiosk\App\Models\KioskNotification;
use Modules\Kiosk\App\Services\ClickSendService;

class LetterController
{
    public function __invoke(Request $request) {
        // Fetch Services
        $services = Service::query()->whereIn('id', $request->input('service_ids'))->with('organisation')->get();
        $location = 'Test Location';

        $pdf = Pdf::loadView('kiosk::letter', compact('services', 'location'));
        $output = $pdf->output();

        $service = new ClickSendService();
        $fileUrl = $service->storeUpload($output);

        if (!$fileUrl) {
            // TODO: throw big error.
            return response('Issue with file upload', 500);
        }

        $success = $service->sendLetter(
            url: $fileUrl,
            addressName: $request->input('name'),
            addressLine1: $request->input('address_line_1'),
            addressLine2: $request->input('address_line_2') ?? "",
            postcode: $request->input('postcode'),
            city: $request->input('city'),
        );

        KioskNotification::query()
            ->create([
                'type' => 'sms',
                'address' => [
                    'name' => $request->input('name'),
                    'line1' => $request->input('address_line_1'),
                    'line2' => $request->input('address_line_2') ?? "",
                    'postcode' => $request->input('postcode'),
                    'city' => $request->input('city'),
                ],
                'service_ids' => $request->input('service_ids'),
                'success' => $success,
            ]);
    }
}