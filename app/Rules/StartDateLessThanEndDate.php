<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;

final class StartDateLessThanEndDate implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(private Carbon $startDate, private Carbon $endDate, private ?string $startTime = null, private ?string $endTime = null)
    {
        if ($startTime) {
            $this->startDate = Carbon::parse($startDate)->setTimeFromTimeString($startTime);
        }

        if ($endTime) {
            $this->endDate = Carbon::parse($endDate)->setTimeFromTimeString($endTime);
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
        if (! $this->endDate->greaterThanOrEqualTo($this->startDate)) {
            $fail($this->message());
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
