<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\LivePlatform;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class LiveStreamUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! LivePlatform::for($value) instanceof LivePlatform) {
            $fail('Use a link to your stream on YouTube or Twitch.');
        }
    }
}
