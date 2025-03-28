<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxDateSupported implements ValidationRule
{
    /**
     * @var Carbon\Carbon
     */
    protected $date;

    /**
     * Create a new rule instance.
     */
    public function __construct(?string $date = null, ?string $time = null)
    {
        if (!$date) {
            $this->date = Carbon::now();
        }
        $this->date = Carbon::parse($date);
        $this->date->setTimeFromTimeString($time ?? '00:00:00');
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param mixed $value
     * @param mixed $fail
     */
    public function validate(string $attribute, $value, $fail): void
    {
        if ($this->date->greaterThanOrEqualTo(Carbon::create(2038))) {
            $fail($this->message());
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The date must be before the year 2038';
    }
}
