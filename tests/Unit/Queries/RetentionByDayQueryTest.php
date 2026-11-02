<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\User;
use App\Queries\RetentionByDayQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(12, 0));
});

function seenOn(User $user, string ...$days): void
{
    DB::table('user_active_days')->insert(array_map(fn (string $day): array => ['user_id' => $user->id, 'seen_at' => $day], $days));
}

it('counts the signups of each day seen again exactly n days later, among all of them, bots left out', function (): void {
    seenOn(User::factory()->create(['created_at' => '2026-10-29 23:00:00']), '2026-10-30');
    seenOn(User::factory()->create(['created_at' => '2026-10-29 08:00:00']), '2026-10-31');
    User::factory()->create(['created_at' => '2026-10-29 09:00:00']);
    seenOn(User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-29 10:00:00']), '2026-10-30');
    seenOn(User::factory()->create(['created_at' => '2026-10-31 10:00:00']), '2026-11-01');

    expect(resolve(RetentionByDayQuery::class)->get(CarbonImmutable::parse('2026-10-29'), CarbonImmutable::parse('2026-11-02'), 1))->toBe([
        ['date' => '2026-10-29', 'eligible' => 3, 'retained' => 1, 'backfilled' => false],
        ['date' => '2026-10-31', 'eligible' => 1, 'retained' => 1, 'backfilled' => false],
    ]);
});

it('leaves out the signup days whose day n is not over, and the ones out of the range', function (): void {
    User::factory()->create(['created_at' => '2026-11-01 10:00:00']);
    User::factory()->create(['created_at' => '2026-10-27 10:00:00']);
    User::factory()->create(['created_at' => '2026-10-31 10:00:00']);

    expect(resolve(RetentionByDayQuery::class)->get(CarbonImmutable::parse('2026-10-28'), CarbonImmutable::parse('2026-11-02'), 1))->toBe([
        ['date' => '2026-10-31', 'eligible' => 1, 'retained' => 0, 'backfilled' => false],
    ]);
});

it('marks the days whose day n came before the active days were recorded as backfilled', function (): void {
    User::factory()->create(['created_at' => '2026-10-28 10:00:00']);
    User::factory()->create(['created_at' => '2026-10-29 10:00:00']);

    expect(collect(resolve(RetentionByDayQuery::class)->get(CarbonImmutable::parse('2026-10-28'), CarbonImmutable::parse('2026-11-02'), 1))->pluck('backfilled', 'date')->all())
        ->toBe(['2026-10-28' => true, '2026-10-29' => false]);
});
