<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UkPhoneNumber implements ValidationRule
{
    /**
     * @var string|null
     */
    protected $message;

    /**
     * UkPhoneNumber constructor.
     */
    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Immediately fail if the value is not a string.
        if (! is_string($value)) {
            $fail(__('validation.string'));
        }

        if (preg_match('/^\(?(\+44|0)?[\s-]?(?:\d{2}|\d{3}|\d{4})[\s-]?\d{3}[\s-]?\d{3}$|^\(?(\+44|0)?[\s-]?\d{6}$|^\(?(\+44|0)?[\s-]?\d{10}$/', $value) !== 1) {
            $fail($this->message());
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return $this->message ?? 'The :attribute must be a valid UK phone number.';
    }
}
