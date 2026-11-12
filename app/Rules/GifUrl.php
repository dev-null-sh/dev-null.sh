<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\GifProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Only https URLs on the provider's media hosts pointing at a .gif rendition, or its page hosts when $page is true.
 * Query strings stay verbatim and dot-segments are rejected, so the host boundary holds after browsers normalize it.
 */
final readonly class GifUrl implements ValidationRule
{
    public function __construct(
        private GifProvider $provider,
        private bool $page = false,
    ) {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        $patterns = $this->page
            ? $this->provider->pageHostPatterns()
            : $this->provider->mediaHostPatterns();

        $isValid = is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && array_filter(
                $patterns,
                fn (string $pattern): bool => preg_match($pattern, $host) === 1,
            ) !== []
            && ! isset($parts['port'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ($this->page || (
                ! isset($parts['fragment'])
                && preg_match('#^/[A-Za-z0-9._/-]+\.gif$#', $parts['path'] ?? '') === 1
                && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $parts['path']) !== 1
            ));

        if (! $isValid) {
            $fail(__('The GIF could not be attached.'));
        }
    }
}
