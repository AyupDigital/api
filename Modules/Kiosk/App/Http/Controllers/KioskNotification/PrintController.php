<?php

declare(strict_types=1);

namespace Modules\Kiosk\App\Http\Controllers\KioskNotification;

use App\Models\Service;
// use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Pdf;
use Dompdf\CanvasFactory;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Kiosk\App\Helpers\OpeningTimesHelper;
use Modules\Kiosk\App\Models\KioskNotification;
use Modules\Kiosk\App\Services\ClickSendService;

class PrintController
{
    public function __invoke(Request $request)
    {
        //TODO: Remove all the janky stuff modifying DOMPDF canvas... if you run into any sizing issues when printing, it's always... always the print driver.
        // Fetch Services
        $baseUrl = config('kiosk.apiBaseUrl');
        $services = Http::get($baseUrl . '/core/v1/services', ['filter[id]' => $request->input('service_ids'), 'include' => 'organisation'])->json()['data'];

        $locations = Http::get($baseUrl . '/core/v1/service-locations', ['filter[service_id]' => $request->input('service_ids'), 'include' => 'location'])->json()['data'];
        $locations = collect($locations)->map(function ($location) {
            if ($location['regular_opening_hours'] && sizeof($location['regular_opening_hours']) > 0) {
                $hours = [];
                foreach ($location['regular_opening_hours'] as $day => $times) {
                    $hours[$day] = OpeningTimesHelper::formattedOpeningTimes($times);
                }
                $location['formatted_opening_hours'] = $hours;
            }
            return $location;
        });

        $services = collect($services)->map(function ($service) use ($locations) {
            $service['locations'] = collect($locations)->where('service_id', $service['id']);
            return $service;
        });

        $domPdf = new Dompdf();
        $domPdf->setCanvas(CanvasFactory::get_instance($domPdf, [0, 0, 580, 5000], 'portrait'));
        $domPdf->setPaper([0, 0, 580, 5000], 'portrait');
        $initialPdf = new Pdf($domPdf, app('config'), app('files'), app('view'));

        $initialPdf->loadView('kiosk::print', compact('services'));


        $bodyHeight = 0;

        $initialPdf->setCallbacks([
            'myCallbacks' => [
                'event' => 'end_frame',
                'f' => function ($frame) use (&$bodyHeight) {
                    $node = $frame->get_node();
                    if (strtolower($node->nodeName) === "body") {
                        $padding_box = $frame->get_padding_box();
                        $bodyHeight += $padding_box['h'];
                    }
                }
            ]
        ]);

        $initialPdf->render();

        unset($initialPdf);
        unset($domPdf);

        $domPdf = new Dompdf();
        $domPdf->setCanvas(CanvasFactory::get_instance($domPdf, [0, 0, 580, $bodyHeight + 120], 'portrait'));
        $domPdf->setPaper([0, 0, 580, $bodyHeight + 80], 'portrait');
        $pdf = new Pdf($domPdf, app('config'), app('files'), app('view'));

        $pdf->loadView('kiosk::print', compact('services'));
        $pdf->save(storage_path('app/print.pdf'));
        $output = $pdf->output();

        $success = Http::withBasicAuth(config('kiosk.printnode.api_key'), '')->post(
            'https://api.printnode.com/printjobs',
            [
                'printerId' => $request->input('printer_id'),
                // 'printerId' => 74092902,
                'contentType' => 'pdf_base64',
                'content' => base64_encode($output),
            ]
        );

        KioskNotification::query()
            ->create([
                'type' => 'print',
                'service_ids' => $request->input('service_ids'),
                'device_id' => $request->input('device_id'),
                'data' => [
                    'printer_id' => $request->input('printer_id'),
                    'service_ids' => $request->input('service_ids'),
                ],
                'success' => $success->ok(),
            ]);
    }
}
