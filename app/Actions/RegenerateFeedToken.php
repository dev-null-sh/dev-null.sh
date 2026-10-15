<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Str;

final readonly class RegenerateFeedToken
{
    /**
     * Gives the user a new secret for their RSS feed link, so any old link stops working. The
     * profile itself does not change, so neither does its timestamp.
     */
    public function handle(User $user): string
    {
        $token = Str::random(48);

        User::withoutTimestamps(fn (): bool => $user->forceFill(['feed_token' => $token])->save());

        return $token;
    }
}
