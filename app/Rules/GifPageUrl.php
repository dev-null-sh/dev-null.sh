<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\GifProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Only https page URLs on the provider's own site. */
final readonly class GifPageUrl implements ValidationRule
{
    public function __construct(private GifProvider $provider)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        $isValid = is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && array_filter(
                $this->provider->pageHostPatterns(),
                fn (string $pattern): bool => preg_match($pattern, $host) === 1,
            ) !== []
            && ! isset($parts['port'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);

        if (! $isValid) {
            $fail(__('The GIF could not be attached.'));
        }
    }
}
