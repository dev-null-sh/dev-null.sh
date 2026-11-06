<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type AppUsageDay array{date: string, web_app: int, native_mobile_app: int, mac_app: int, other_app: int}
 * @phpstan-type AppUsage array{days: list<AppUsageDay>, recorded_since: string|null}
 */
final readonly class AppUsageQuery
{
    /**
     * The people who used each app, for each day of the range, oldest first, bots left out. Someone using two apps in a
     * day counts for both, so a day's apps can add up to more than its active users. Past days count as web whatever was
     * used, so the split only means something from the first day any other app shows up, which is when tracking started.
     *
     * @return AppUsage
     */
    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var list<object{date: string, web_app: int, native_mobile_app: int, mac_app: int, other_app: int}> $rows */
        $rows = DB::select(<<<'SQL'
            select a.seen_at as date,
                count(*) filter (where a.web_app) as web_app,
                count(*) filter (where a.native_mobile_app) as native_mobile_app,
                count(*) filter (where a.mac_app) as mac_app,
                count(*) filter (where a.other_app) as other_app
            from user_active_days a
            join users u on u.id = a.user_id
            where u.github_id <> all(?::text[]) and a.seen_at between ? and ?
            group by 1
            order by 1
            SQL, [
            User::botGitHubIdsArray(),
            $from->toDateString(),
            $to->toDateString(),
        ]);

        /** @var string|null $recordedSince */
        $recordedSince = DB::table('user_active_days')
            ->whereRaw('native_mobile_app or mac_app or other_app')
            ->min('seen_at');

        return [
            'days' => array_map(fn (object $row): array => [
                'date' => $row->date,
                'web_app' => (int) $row->web_app,
                'native_mobile_app' => (int) $row->native_mobile_app,
                'mac_app' => (int) $row->mac_app,
                'other_app' => (int) $row->other_app,
            ], $rows),
            'recorded_since' => $recordedSince,
        ];
    }
}
