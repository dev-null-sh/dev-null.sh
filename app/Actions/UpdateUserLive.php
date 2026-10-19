<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\LivePlatform;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserLive
{
    public function __construct(private DeleteUserLive $deleteUserLive)
    {
        //
    }

    /**
     * A stream link belongs to one user at a time, so whoever still holds it after their stream ran out lets go of it first.
     */
    public function handle(User $user, LivePlatform $platform, string $url, int $hours): void
    {
        $url = $platform->driver()->stream($url)->url;

        DB::transaction(function () use ($user, $platform, $url, $hours): void {
            User::query()
                ->whereKeyNot($user->id)
                ->where('live_url', $url)
                ->where('live_ends_at', '<=', now())
                ->each($this->deleteUserLive->handle(...));

            $user->update([
                'live_platform' => $platform,
                'live_url' => $url,
                'live_ends_at' => now()->addHours($hours),
            ]);
        });
    }
}
