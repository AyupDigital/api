<?php

namespace App\Rules;

use Carbon\Carbon;
use ErrorException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

class DateSanity implements ValidationRule
{
    /**
     * @var Carbon\Carbon
     */
    protected $start;

    /**
     * @var Carbon\Carbon
     */
    protected $end;

    /**
     * Create a new rule instance.
     */
    public function __construct(\Illuminate\Foundation\Http\FormRequest $request)
    {
        try {
            if (isset($request->organisation_event)) {
                $this->start = Carbon::parse($request->get('start_date', $request->organisation_event->start_date));
                $this->end = Carbon::parse($request->get('end_date', $request->organisation_event->end_date));
                $this->start->setTimeFromTimeString($request->get('start_time', $request->organisation_event->start_time));
                $this->end->setTimeFromTimeString($request->get('end_time', $request->organisation_event->end_time));
            } else {
                $this->start = Carbon::parse($request->get('start_date', 'now'));
                $this->end = Carbon::parse($request->get('end_date', 'now'));
                $this->start->setTimeFromTimeString($request->get('start_time', '00:00:00'));
                $this->end->setTimeFromTimeString($request->get('end_time', '00:00:00'));
            }
        } catch (ErrorException $e) {
            Log::info($e);
        }
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $value
     * @param  mixed  $fail
     */
    public function validate(string $attribute, $value, $fail): void
    {
        if (! $this->end->greaterThanOrEqualTo($this->start)) {
            $fail($this->message());
        }

        if ($this->end->greaterThanOrEqualTo(Carbon::create(2038))) {
            $fail('The end date and time must be before the year 2038');
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The end date and time should be later than the start date and time';
    }
}
