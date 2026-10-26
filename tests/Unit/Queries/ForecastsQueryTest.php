<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\User;
use App\Queries\ForecastsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
});

/**
 * One signup a day from the last day of September through today, and a bot that signed up in between.
 */
function signupEveryDay(): void
{
    User::factory()
        ->count(21)
        ->state(new Sequence(fn (Sequence $sequence): array => ['created_at' => CarbonImmutable::parse('2026-09-30 08:00:00')->addDays($sequence->index)]))
        ->create();
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-05 08:00:00']);
}

it('forecasts every metric two weeks out from today, flat while there is nothing to go on', function (): void {
    $forecasts = resolve(ForecastsQuery::class)->get();

    expect(array_column($forecasts, 'metric'))->toBe(['active_users', 'signups', 'commits', 'replies'])
        ->and($forecasts[2])->toMatchArray(['method' => 'flat', 'trend' => 0.0, 'today' => 0, 'history' => []])
        ->and($forecasts[2]['forecast'])->toHaveCount(14)
        ->and($forecasts[2]['forecast'][0])->toBe(['date' => '2026-10-20', 'value' => 0.0, 'lower' => 0.0, 'upper' => 1.0])
        ->and($forecasts[2]['forecast'][13]['date'])->toBe('2026-11-02');
});

it('fits the complete days from the first one that counted anything, today counting apart', function (): void {
    DB::table('active_user_counts')->insert([
        ['seen_at' => '2026-10-13', 'count' => 10],
        ['seen_at' => '2026-10-14', 'count' => 12],
        ['seen_at' => '2026-10-15', 'count' => 14],
        ['seen_at' => '2026-10-16', 'count' => 16],
        ['seen_at' => '2026-10-17', 'count' => 18],
        ['seen_at' => '2026-10-18', 'count' => 20],
        ['seen_at' => '2026-10-19', 'count' => 22],
        ['seen_at' => '2026-10-20', 'count' => 5],
    ]);

    $activeUsers = resolve(ForecastsQuery::class)->get()[0];

    expect($activeUsers)->toMatchArray(['metric' => 'active_users', 'method' => 'linear', 'trend' => 2.0, 'today' => 5])
        ->and($activeUsers['history'])->toHaveCount(7)
        ->and($activeUsers['history'][0])->toBe(['date' => '2026-10-13', 'value' => 10])
        ->and($activeUsers['forecast'][0])->toBe(['date' => '2026-10-20', 'value' => 24.0, 'lower' => 24.0, 'upper' => 24.0]);
});

it('dates the next round number of people the signups forecast reaches, and never one it does not', function (): void {
    signupEveryDay();

    expect(resolve(ForecastsQuery::class)->milestones())->toBe([
        ['metric' => 'users', 'current' => 21, 'target' => 25, 'eta' => '2026-10-24'],
        ['metric' => 'commits', 'current' => 0, 'target' => 10, 'eta' => null],
    ]);
});

it('aims for 10, 25, 50, then 100 and so on', function (int $users, int $target): void {
    User::factory()->count($users)->create(['created_at' => now()->subYear()]);

    expect(resolve(ForecastsQuery::class)->milestones()[0]['target'])->toBe($target);
})->with([
    'nobody yet' => [0, 10],
    'exactly ten' => [10, 25],
    'past twenty-five' => [26, 50],
    'past fifty' => [50, 100],
]);

it('projects the month from what it counted so far and the forecast of its remaining days', function (): void {
    signupEveryDay();

    expect(resolve(ForecastsQuery::class)->month())->toBe([
        ['metric' => 'signups', 'month' => '2026-10', 'to_date' => 20, 'projected' => 31.0, 'lower' => 31.0, 'upper' => 31.0],
        ['metric' => 'commits', 'month' => '2026-10', 'to_date' => 0, 'projected' => 0.0, 'lower' => 0.0, 'upper' => 12.0],
    ]);
});
