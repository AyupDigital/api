<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;

final class DateSanity implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(private Carbon $date, private ?string $time = null) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $value
     * @param  mixed  $fail
     */
    public function validate(string $attribute, $value, $fail): void
    {
        if ($this->date->lessThan(Carbon::now())) {
            $fail('The :attribute date and time must be in the future');
        }

        if ($this->date->greaterThanOrEqualTo(Carbon::create(2038))) {
            $fail('The :attribute date and time must be before the year 2038');
        }
    }
}
