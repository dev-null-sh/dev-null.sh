<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\User;
use App\Queries\NewcomerFollowsQuery;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));

    $this->follow = fn (User $follower, User $followed, string $at) => $follower->following()->attach($followed, ['created_at' => $at, 'updated_at' => $at]);

    $this->follows = fn (): array => resolve(NewcomerFollowsQuery::class)
        ->get(CarbonImmutable::parse('2026-10-10'), CarbonImmutable::parse('2026-10-19'));
});

it('counts the median follows of newcomers in their first 24 hours, and how many were followed back', function (): void {
    $alice = User::factory()->create(['created_at' => '2026-10-10 08:00:00']);
    $bob = User::factory()->create(['created_at' => '2026-10-11 08:00:00']);
    User::factory()->create(['created_at' => '2026-10-12 08:00:00']);
    $others = User::factory()->count(3)->create(['created_at' => '2026-09-01 00:00:00']);

    ($this->follow)($alice, $others[0], '2026-10-10 09:00:00');
    ($this->follow)($alice, $others[1], '2026-10-11 08:00:00');
    ($this->follow)($alice, $others[2], '2026-10-11 08:00:01');
    ($this->follow)($bob, $others[0], '2026-10-11 09:00:00');
    ($this->follow)($others[0], $alice, '2026-10-15 09:00:00');

    expect(($this->follows)())
        ->newcomers->toBe(3)
        ->median_follows->toBe(1.0)
        ->follows->toBe(3)
        ->followed_back->toBe(1);
});

it('leaves out follows imported from GitHub, follows of bots, and signups younger than 24 hours or bots', function (): void {
    $alice = User::factory()->create(['created_at' => '2026-10-10 08:00:00', 'github_follows_imported_at' => '2026-10-10 08:00:30']);
    $later = User::factory()->create(['created_at' => '2026-10-10 10:00:00', 'github_follows_imported_at' => '2026-10-10 10:00:05']);
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-10 08:00:00']);
    User::factory()->create(['created_at' => '2026-10-18 12:00:01']);

    ($this->follow)($alice, User::factory()->create(['created_at' => '2026-09-01 00:00:00']), '2026-10-10 08:00:25');
    ($this->follow)($alice, $later, '2026-10-10 10:00:02');
    ($this->follow)($alice, $tom, '2026-10-10 09:00:00');
    ($this->follow)($tom, $alice, '2026-10-10 09:00:00');

    expect(($this->follows)())
        ->newcomers->toBe(2)
        ->median_follows->toBe(0.0)
        ->follows->toBe(0)
        ->followed_back->toBe(0);
});

it('has no median without newcomers', function (): void {
    expect(($this->follows)())
        ->newcomers->toBe(0)
        ->median_follows->toBeNull();
});
