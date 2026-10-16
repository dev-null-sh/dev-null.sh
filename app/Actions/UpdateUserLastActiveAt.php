<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\Presence;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final readonly class UpdateUserLastActiveAt
{
    public function __construct(private Presence $presence)
    {
        //
    }

    /**
     * Marks the user as active now, at most once per refresh interval. The timestamp is written without touching
     * `updated_at`, which the user's link preview image is keyed on.
     */
    public function handle(User $user): void
    {
        if (! $user->exists || ! $this->presence->needsRefresh($this->lastActivityAt($user))) {
            return;
        }

        User::withoutTimestamps(fn (): bool => $user->forceFill([
            'last_activity_at' => now(),
        ])->saveQuietly());
    }

    /**
     * The authenticated instance a request carries may be a partial model without the attribute loaded, and strict
     * mode guards the attribute accessor, so the original value is read instead.
     */
    private function lastActivityAt(User $user): ?CarbonInterface
    {
        /** @var string|null $lastActivityAt */
        $lastActivityAt = $user->getRawOriginal('last_activity_at');

        return $lastActivityAt === null ? null : Carbon::parse($lastActivityAt);
    }
}
