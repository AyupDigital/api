<?php

namespace App\Actions;

use App\DataTransferObjects\ServiceLocationRequestObject;
use App\Models\File;
use App\Models\Service;
use App\Models\ServiceLocation;

class StoreServiceLocationAction
{
    public function handle(
        Service $service,
        ServiceLocationRequestObject $requestObject,
    ): ServiceLocation {
        $serviceLocation = $service->serviceLocations()->create([
            'name' => $requestObject->name,
            'location_id' => $requestObject->locationId,
            'image_file_id' => $requestObject->imageFileId,
        ]);

        if ($requestObject->regularOpeningHours) {
            foreach ($requestObject->regularOpeningHours as $openingHour) {
                $serviceLocation->regularOpeningHours()->create([
                    'frequency' => $openingHour['frequency'],
                    'weekday' => $openingHour['weekday'] ?? null,
                    'day_of_month' => $openingHour['day_of_month'] ?? null,
                    'occurrence_of_month' => $openingHour['occurrence_of_month'] ?? null,
                    'starts_at' => $openingHour['starts_at'] ?? null,
                    'opens_at' => $openingHour['opens_at'],
                    'closes_at' => $openingHour['closes_at'],
                ]);
            }
        }

        if ($requestObject->holidayOpeningHours) {
            foreach ($requestObject->holidayOpeningHours as $openingHour) {
                $serviceLocation->holidayOpeningHours()->create([
                    'is_closed' => $openingHour['is_closed'],
                    'starts_at' => $openingHour['starts_at'],
                    'ends_at' => $openingHour['ends_at'],
                    'opens_at' => $openingHour['opens_at'],
                    'closes_at' => $openingHour['closes_at'],
                ]);
            }
        }

        if ($requestObject->imageFileId) {
            /** @var File $file */
            $file = File::findOrFail($requestObject->imageFileId)->assigned();

            foreach (config('local.cached_image_dimensions') as $maxDimension) {
                $file->resizedVersion($maxDimension);
            }
        }

        return $serviceLocation;
    }
}
