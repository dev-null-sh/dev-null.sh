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
     * Every guest page shows it, so it's counted once an hour.
     */
    public function get(): int
    {
        return Cache::remember('developers-count', 3600, function (): int {
            $count = User::query()->whereNull('banned_at')->count();
            $step = $count >= 1000 ? 1000 : 100;

            return intdiv($count, $step) * $step;
        });
    }
}
