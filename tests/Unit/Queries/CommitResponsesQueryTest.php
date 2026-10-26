<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitResponsesQuery;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));

    $this->responses = fn (): array => resolve(CommitResponsesQuery::class)
        ->get(CarbonImmutable::parse('2026-10-10'), CarbonImmutable::parse('2026-10-19'));
});

it('counts the commits answered within 24 hours, and the median wait for the first answer', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();

    $replied = Commit::factory()->for($author)->create(['created_at' => '2026-10-10 08:00:00']);
    Commit::factory()->for($other)->for($replied, 'parent')->create(['created_at' => '2026-10-10 08:10:00']);

    $quoted = Commit::factory()->for($author)->create(['created_at' => '2026-10-11 08:00:00']);
    Commit::factory()->for($other)->create(['quoted_commit_id' => $quoted->id, 'created_at' => '2026-10-11 08:30:00']);

    $starred = Commit::factory()->for($author)->create(['created_at' => '2026-10-12 08:00:00']);
    $starred->stars()->attach($other, ['created_at' => '2026-10-12 09:00:00', 'updated_at' => '2026-10-12 09:00:00']);

    $late = Commit::factory()->for($author)->create(['created_at' => '2026-10-13 08:00:00']);
    Commit::factory()->for($other)->for($late, 'parent')->create(['created_at' => '2026-10-14 08:00:01']);

    expect(($this->responses)())
        ->commits->toBe(5)
        ->answered->toBe(3)
        ->median_minutes->toBe(30);
});

it('leaves out answers from the author, from bots, or not approved, and commits that are not approved, too young, replies or by bots', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]);

    $commit = Commit::factory()->for($author)->create(['created_at' => '2026-10-10 08:00:00']);
    Commit::factory()->for($author)->for($commit, 'parent')->create(['created_at' => '2026-10-10 08:10:00']);
    Commit::factory()->for($tom)->for($commit, 'parent')->create(['created_at' => '2026-10-10 08:10:00']);
    Commit::factory()->for($other)->for($commit, 'parent')->pending()->create(['created_at' => '2026-10-10 08:10:00']);

    Commit::factory()->for($author)->pending()->create(['created_at' => '2026-10-11 08:00:00']);
    Commit::factory()->for($author)->create(['created_at' => '2026-10-18 12:00:01']);
    Commit::factory()->for($tom)->create(['created_at' => '2026-10-11 08:00:00']);
    Commit::factory()->for($author)->create(['created_at' => '2026-10-09 23:59:59']);

    expect(($this->responses)())
        ->commits->toBe(1)
        ->answered->toBe(0)
        ->median_minutes->toBeNull();
});

it('counts the first commits of their authors apart', function (): void {
    $newcomer = User::factory()->create();
    $regular = User::factory()->create();
    $other = User::factory()->create();

    $first = Commit::factory()->for($newcomer)->create(['created_at' => '2026-10-10 08:00:00']);
    Commit::factory()->for($other)->for($first, 'parent')->create(['created_at' => '2026-10-10 09:00:00']);

    Commit::factory()->for($regular)->create(['created_at' => '2026-10-01 08:00:00'])->delete();
    Commit::factory()->for($regular)->create(['created_at' => '2026-10-11 08:00:00']);
    Commit::factory()->for($newcomer)->create(['created_at' => '2026-10-12 08:00:00']);

    expect(($this->responses)())
        ->commits->toBe(3)
        ->first_commits->toBe(1)
        ->first_answered->toBe(1);
});
