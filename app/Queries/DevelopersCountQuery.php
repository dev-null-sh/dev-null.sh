<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class DevelopersCountQuery
{
    /**
     * How many developers are on /dev/null, for guests who'd otherwise judge by the stars on a few commits. Rounded down,
     * to the thousand from a thousand and to the hundred below, so "12,000+" stays true until the next round number.
     * Banned people and the bots aren't developers here. Every guest page shows it, so it's counted once an hour.
     */
    public function get(): int
    {
        return (int) Cache::remember('developers-count', 3600, function (): int {
            $count = User::query()->whereNull('banned_at')->whereNotIn('github_id', User::BOT_GITHUB_IDS)->count();
            $step = $count >= 1000 ? 1000 : 100;

            return intdiv($count, $step) * $step;
        });
    }
}
