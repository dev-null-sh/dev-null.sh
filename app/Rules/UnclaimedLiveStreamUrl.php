<?php

declare(strict_types=1);

namespace App\Rules;

use App\Dto\LiveStream;
use App\Enums\LivePlatform;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class UnclaimedLiveStreamUrl implements ValidationRule
{
    public function __construct(private User $user)
    {
        //
    }

    /**
     * Links are compared as they are stored, so another link to the same stream is caught too.
     * A stream that ran out but was not cleared yet is free again, as it no longer shows as live.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $stream = is_string($value) ? LivePlatform::for($value)?->driver()->stream($value) : null;

        $claimed = $stream instanceof LiveStream && User::query()
            ->whereKeyNot($this->user->id)
            ->where('live_url', $stream->url)
            ->where('live_ends_at', '>', now())
            ->exists();

        if ($claimed) {
            $fail('Someone else is already live with this stream.');
        }
    }
}
