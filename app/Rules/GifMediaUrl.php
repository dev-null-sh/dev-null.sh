<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\GifProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Only https URLs on the provider's media hosts pointing at a .gif rendition. Query strings stay verbatim and
 * dot-segments are rejected, so the host boundary holds after browsers normalize the URL.
 */
final readonly class GifMediaUrl implements ValidationRule
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
                $this->provider->mediaHostPatterns(),
                fn (string $pattern): bool => preg_match($pattern, $host) === 1,
            ) !== []
            && ! isset($parts['fragment'])
            && ! isset($parts['port'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && preg_match('#^/[A-Za-z0-9._/-]+\.gif$#', $parts['path'] ?? '') === 1
            && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $parts['path']) !== 1;

        if (! $isValid) {
            $fail(__('The GIF could not be attached.'));
        }
    }
}
