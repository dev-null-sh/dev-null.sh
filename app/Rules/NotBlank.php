<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NotBlank implements ValidationRule
{
    /** Run even when the value is empty or whitespace-only. */
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/^[\s\p{Cf}]*$/u', $value) === 1) {
            $fail('The :attribute must not be blank.');
        }
    }
}
