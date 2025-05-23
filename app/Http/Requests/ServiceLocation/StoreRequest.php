<?php

namespace App\Http\Requests\ServiceLocation;

use App\Models\File;
use App\Models\RegularOpeningHour;
use App\Rules\FileIsMimeType;
use App\Rules\FileIsPendingAssignment;
use App\Rules\IsServiceAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->user()->isServiceAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'exists:services,id', new IsServiceAdmin($this->user())],
            'location_id' => ['required', 'exists:locations,id'],
            'name' => ['present', 'nullable', 'string', 'min:1', 'max:255'],

            'regular_opening_hours' => ['present', 'array'],
            'regular_opening_hours.*' => ['array'],
            'regular_opening_hours.*.frequency' => ['required_with:regular_opening_hours.*', Rule::in([
                RegularOpeningHour::FREQUENCY_WEEKLY,
                RegularOpeningHour::FREQUENCY_MONTHLY,
                RegularOpeningHour::FREQUENCY_FORTNIGHTLY,
                RegularOpeningHour::FREQUENCY_NTH_OCCURRENCE_OF_MONTH,
            ])],
            'regular_opening_hours.*.weekday' => ['required_if:regular_opening_hours.*.frequency,'.RegularOpeningHour::FREQUENCY_WEEKLY, 'nullable', 'integer', 'min:1', 'max:7'],
            'regular_opening_hours.*.day_of_month' => ['required_if:regular_opening_hours.*.frequency,'.RegularOpeningHour::FREQUENCY_MONTHLY, 'nullable', 'integer', 'min:1', 'max:31'],
            'regular_opening_hours.*.occurrence_of_month' => ['required_if:regular_opening_hours.*.frequency,'.RegularOpeningHour::FREQUENCY_NTH_OCCURRENCE_OF_MONTH, 'nullable', 'integer', 'min:1', 'max: 5'],
            'regular_opening_hours.*.starts_at' => ['required_if:regular_opening_hours.*.frequency,'.RegularOpeningHour::FREQUENCY_FORTNIGHTLY, 'nullable', 'date_format:Y-m-d'],
            'regular_opening_hours.*.opens_at' => ['required_with:regular_opening_hours.*', 'date_format:H:i:s'],
            'regular_opening_hours.*.closes_at' => ['required_with:regular_opening_hours.*', 'date_format:H:i:s'],

            'holiday_opening_hours' => ['present', 'array'],
            'holiday_opening_hours.*' => ['array'],
            'holiday_opening_hours.*.is_closed' => ['required_with:holiday_opening_hours.*', 'boolean'],
            'holiday_opening_hours.*.starts_at' => ['required_with:holiday_opening_hours.*', 'date_format:Y-m-d'],
            'holiday_opening_hours.*.ends_at' => ['required_with:holiday_opening_hours.*', 'date_format:Y-m-d'],
            'holiday_opening_hours.*.opens_at' => ['required_with:holiday_opening_hours.*', 'date_format:H:i:s'],
            'holiday_opening_hours.*.closes_at' => ['required_with:holiday_opening_hours.*', 'date_format:H:i:s'],

            'image_file_id' => [
                'nullable',
                'exists:files,id',
                new FileIsMimeType(File::MIME_TYPE_PNG, File::MIME_TYPE_SVG, File::MIME_TYPE_JPG, File::MIME_TYPE_JPEG),
                new FileIsPendingAssignment,
            ],
        ];
    }
}
