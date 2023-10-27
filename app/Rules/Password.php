<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Password implements Rule
{
    const ALLOWED_SPECIAL_CHARACTERS = '!#$%&()*+,-./:;<=>?@[]^_`{|}~';

    /**
     * @var string|null
     */
    protected $message;

    /**
     * Password constructor.
     */
    public function __construct(string $message = null)
    {
        $this->message = $message;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes(string $attribute, $value): bool
    {
        // Immediately fail if the value is not a string.
        if (! is_string($value)) {
            return false;
        }

        $matches = preg_match($this->regex(), $value);

        return $matches > 0;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message ?? 'The :attribute must be at least eight characters long, contain one uppercase letter, one lowercase letter, one number and one special character ('.static::ALLOWED_SPECIAL_CHARACTERS.').';
    }

    /**
     * Returns the regex for the password.
     */
    protected function regex(): string
    {
        return "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[{$this->escapedSpecialCharacters()}])[A-Za-z\d{$this->escapedSpecialCharacters()}]{8,}/";
    }

    /**
     * Returns the special characters escaped for the regex.
     */
    protected function escapedSpecialCharacters(): string
    {
        $characters = mb_str_split(static::ALLOWED_SPECIAL_CHARACTERS);

        return collect($characters)
            ->map(function (string $character) {
                return '\\'.$character;
            })
            ->implode('');
    }
}
