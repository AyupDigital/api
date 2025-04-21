<?php

namespace App\DataTransferObjects;

use Illuminate\Http\Request;

class ServiceLocationRequestObject
{
    public function __construct(
        public ?string $name,
        public string $locationId,
        public ?array $regularOpeningHours,
        public ?array $holidayOpeningHours,
        public ?string $imageFileId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->get('name'),
            locationId: $request->get('location_id'),
            regularOpeningHours: $request->get('regular_opening_hours', []),
            holidayOpeningHours: $request->get('holiday_opening_hours', []),
            imageFileId: $request->get('image_file_id', null),
        );
    }
}
