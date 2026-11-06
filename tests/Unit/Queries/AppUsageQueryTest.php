<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\User;
use App\Queries\AppUsageQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function usedOn(string $day, string ...$apps): void
{
    DB::table('user_active_days')->insert([
        'user_id' => User::factory()->create()->id,
        'seen_at' => $day,
        ...array_fill_keys($apps, true),
    ]);
}

it('counts the people who used each app each day of the range, oldest first, in every app they used', function (): void {
    usedOn('2026-11-02', 'web_app', 'native_mobile_app');
    usedOn('2026-11-02', 'web_app');
    usedOn('2026-11-01', 'web_app');
    usedOn('2026-11-02', 'mac_app', 'other_app');
    usedOn('2026-10-31', 'web_app');

    expect(resolve(AppUsageQuery::class)->get(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-02'))['days'])->toBe([
        ['date' => '2026-11-01', 'web_app' => 1, 'native_mobile_app' => 0, 'mac_app' => 0, 'other_app' => 0],
        ['date' => '2026-11-02', 'web_app' => 2, 'native_mobile_app' => 1, 'mac_app' => 1, 'other_app' => 1],
    ]);
});

it('leaves bots out', function (): void {
    DB::table('user_active_days')->insert([
        'user_id' => User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID])->id,
        'seen_at' => '2026-11-02',
        'other_app' => true,
    ]);

    expect(resolve(AppUsageQuery::class)->get(CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-02'))['days'])->toBe([]);
});

it('says tracking started on the first day any other app than web shows up, even before the range', function (): void {
    usedOn('2026-10-20', 'web_app');
    usedOn('2026-10-25', 'mac_app');
    usedOn('2026-10-28', 'other_app');

    expect(resolve(AppUsageQuery::class)->get(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-02'))['recorded_since'])->toBe('2026-10-25');
});

it('says tracking has not started while only web was seen', function (): void {
    usedOn('2026-10-20', 'web_app');

    expect(resolve(AppUsageQuery::class)->get(CarbonImmutable::parse('2026-10-20'), CarbonImmutable::parse('2026-10-20'))['recorded_since'])->toBeNull();
});
