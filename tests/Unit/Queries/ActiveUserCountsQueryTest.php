<?php

declare(strict_types=1);

use App\Queries\ActiveUserCountsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('lists the daily counts of the range, oldest first', function (): void {
    DB::table('active_user_counts')->insert([
        ['seen_at' => '2026-10-19', 'count' => 30],
        ['seen_at' => '2026-10-17', 'count' => 10],
        ['seen_at' => '2026-10-18', 'count' => 20],
        ['seen_at' => '2026-10-16', 'count' => 5],
    ]);

    expect(resolve(ActiveUserCountsQuery::class)->get(CarbonImmutable::parse('2026-10-17'), CarbonImmutable::parse('2026-10-19')))->toBe([
        ['date' => '2026-10-17', 'count' => 10],
        ['date' => '2026-10-18', 'count' => 20],
        ['date' => '2026-10-19', 'count' => 30],
    ]);
});
