<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\Services\PlaylistProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\HttpClientException;

final readonly class PlaylistUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $tracks = is_string($value) ? resolve(PlaylistProvider::class)->tracks($value) : null;
        } catch (HttpClientException) {
            $fail("Audius isn't answering. Try again in a moment.");

            return;
        }

        if ($tracks === null) {
            $fail('Use a link to a public playlist or album on Audius.');
        }
    }
}
