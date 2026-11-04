<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\Services\LastFmProvider;
use App\Enums\Feature;
use App\Services\Features;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\HttpClientException;

final readonly class LastFmUsername implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! resolve(Features::class)->enabled(Feature::NowPlaying)) {
            $fail("Sharing what you're listening to isn't available yet.");

            return;
        }

        if (! is_string($value) || preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,14}$/', $value) !== 1) {
            $fail('Use your Last.fm username: 2 to 15 letters, numbers, dashes or underscores, starting with a letter.');

            return;
        }

        try {
            $exists = resolve(LastFmProvider::class)->exists($value);
        } catch (HttpClientException) {
            $fail("Last.fm isn't answering. Try again in a moment.");

            return;
        }

        if (! $exists) {
            $fail('There is no Last.fm account with that username.');
        }
    }
}
