<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\User;
use App\Queries\ActiveUserLifecycleQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
});

function visitedOn(User $user, string ...$days): void
{
    DB::table('user_active_days')->insert(array_map(fn (string $day): array => ['user_id' => $user->id, 'seen_at' => $day], $days));
}

function lifecycleOf(string $from, string $to): array
{
    return collect(resolve(ActiveUserLifecycleQuery::class)->get(CarbonImmutable::parse($from), CarbonImmutable::parse($to)))
        ->keyBy('date')
        ->map(fn (array $day): array => [$day['new'], $day['regular'], $day['back'], $day['lost']])
        ->all();
}

it('splits the people seen each day into new, regular, as seen or signed up in the week before, and back after a week away, bots left out', function (): void {
    visitedOn(User::factory()->create(['created_at' => '2026-11-15 10:00:00']), '2026-11-15');
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-01', '2026-11-08', '2026-11-15');
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-01', '2026-11-07', '2026-11-15');
    visitedOn(User::factory()->create(['created_at' => '2026-11-14 10:00:00']), '2026-11-15');
    visitedOn(User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-11-01 10:00:00']), '2026-11-01', '2026-11-15');

    expect(lifecycleOf('2026-11-15', '2026-11-15'))->toBe(['2026-11-15' => [1, 2, 1, 0]]);
});

it('counts as lost the people seen a week before and not since, on the day it is sure, so not today', function (): void {
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-01', '2026-11-10');
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-10', '2026-11-17');
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-10', '2026-11-18');
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-13');

    expect(lifecycleOf('2026-11-16', '2026-11-25'))->toBe([
        '2026-11-16' => [0, 0, 0, 0],
        '2026-11-17' => [0, 1, 0, 2],
        '2026-11-18' => [0, 0, 1, 0],
        '2026-11-19' => [0, 0, 0, 0],
        '2026-11-20' => [0, 0, 0, null],
    ]);
});

it('marks the days that look back on days before the active days were recorded as backfilled', function (): void {
    expect(collect(resolve(ActiveUserLifecycleQuery::class)->get(CarbonImmutable::parse('2026-11-05'), CarbonImmutable::parse('2026-11-06')))->pluck('backfilled', 'date')->all())
        ->toBe(['2026-11-05' => true, '2026-11-06' => false]);
});

it('has no days when the range starts after today', function (): void {
    expect(lifecycleOf('2026-11-21', '2026-11-25'))->toBe([]);
});
