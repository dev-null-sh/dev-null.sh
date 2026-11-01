<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\User;
use App\Queries\BackAndLostQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
});

function visitedOn(User $user, string ...$days): void
{
    DB::table('user_active_days')->insert(array_map(fn (string $day): array => ['user_id' => $user->id, 'seen_at' => $day], $days));
}

function backAndLostOf(string $from, string $to): array
{
    return collect(resolve(BackAndLostQuery::class)->get(CarbonImmutable::parse($from), CarbonImmutable::parse($to)))
        ->keyBy('date')
        ->map(fn (array $day): array => [$day['back'], $day['lost'], $day['lost_complete']])
        ->all();
}

it('counts the people back after a week away, not the ones who signed up or were seen in the week before, bots left out', function (): void {
    visitedOn(User::factory()->create(['created_at' => '2026-11-15 10:00:00']), '2026-11-15');
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-01', '2026-11-08', '2026-11-15');
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-01', '2026-11-07', '2026-11-15');
    visitedOn(User::factory()->create(['created_at' => '2026-11-14 10:00:00']), '2026-11-15');
    visitedOn(User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-11-01 10:00:00']), '2026-11-01', '2026-11-15');

    expect(backAndLostOf('2026-11-15', '2026-11-15'))->toBe(['2026-11-15' => [1, 0, false]]);
});

it('counts the people lost on their first day of a week away, complete once the week is over, and not today', function (): void {
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-05', '2026-11-13');
    visitedOn(User::factory()->create(['created_at' => '2026-11-01 10:00:00']), '2026-11-05', '2026-11-12');

    expect(collect(backAndLostOf('2026-11-06', '2026-11-25'))->only(['2026-11-06', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-20'])->all())->toBe([
        '2026-11-06' => [0, 1, true],
        '2026-11-12' => [0, 0, true],
        '2026-11-13' => [1, 1, true],
        '2026-11-14' => [0, 1, false],
        '2026-11-20' => [0, null, false],
    ]);
});

it('marks the days that look back on days before the active days were recorded as backfilled', function (): void {
    expect(collect(resolve(BackAndLostQuery::class)->get(CarbonImmutable::parse('2026-11-05'), CarbonImmutable::parse('2026-11-06')))->pluck('backfilled', 'date')->all())
        ->toBe(['2026-11-05' => true, '2026-11-06' => false]);
});

it('has no days when the range starts after today', function (): void {
    expect(backAndLostOf('2026-11-21', '2026-11-25'))->toBe([]);
});
