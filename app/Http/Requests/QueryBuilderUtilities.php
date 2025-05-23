<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;

trait QueryBuilderUtilities
{
    /**
     * Check if the input contains the specified string.
     */
    public function contains(string $key, string $value): bool
    {
        return Str::contains($this->$key, $value);
    }

    /**
     * Remove the specified string from the input.
     */
    public function strip(string $key, string $value): self
    {
        if (! $this->has($key)) {
            return $this;
        }

        $parts = explode(',', $this->$key);

        $parts = collect($parts)->reject(function (string $part) use ($value) {
            return $part === $value;
        });

        return $this->merge([$key => $parts]);
    }
}
