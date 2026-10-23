<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use App\Queries\BulkActivityQuery;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));

    $this->activity = fn (int $days = 1, int $limit = 10): array => resolve(BulkActivityQuery::class)->get($days, $limit);

    $this->people = fn (int $count, string $signedUpAt = '2026-09-01 00:00:00'): Collection => User::factory()->count($count)->create(['created_at' => $signedUpAt]);

    $this->follow = fn (User $follower, iterable $followed, string $at = '2026-10-23 01:00:00') => collect($followed)->each(
        fn (User $user) => $follower->following()->attach($user, ['created_at' => $at, 'updated_at' => $at]),
    );

    $this->star = fn (User $starrer, iterable $authors, string $at = '2026-10-23 01:00:00') => collect($authors)->each(
        fn (User $author) => Commit::factory()->for($author)->create(['created_at' => '2026-10-22 00:00:00'])
            ->stars()->attach($starrer, ['created_at' => $at, 'updated_at' => $at]),
    );

    $this->reply = fn (User $replier, iterable $authors, string $at = '2026-10-23 01:00:00') => collect($authors)->each(
        fn (User $author) => Commit::factory()->for($replier)
            ->for(Commit::factory()->for($author)->create(['created_at' => '2026-10-22 00:00:00']), 'parent')
            ->create(['created_at' => $at]),
    );
});

it('lists the accounts that followed 50 people a day, with the newcomers among them', function (): void {
    $mass = User::factory()->create();
    $busy = User::factory()->create();

    ($this->follow)($mass, ($this->people)(30, '2026-10-23 00:00:00'));
    ($this->follow)($mass, ($this->people)(20));
    ($this->follow)($busy, ($this->people)(49));

    expect(($this->activity)()['follows'])
        ->toHaveCount(1)
        ->sequence(fn ($row) => $row->user->id->toBe($mass->id)->count->toBe(50)->newcomers->toBe(30));
});

it('leaves out follows imported from GitHub, on either side, and bots', function (): void {
    $importer = User::factory()->create(['github_follows_imported_at' => '2026-10-23 01:00:05']);
    $member = User::factory()->create();
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]);

    ($this->follow)($importer, ($this->people)(50));
    ($this->follow)($member, User::factory()->count(50)->create(['github_follows_imported_at' => '2026-10-23 01:00:05']));
    ($this->follow)($tom, ($this->people)(50));

    expect(($this->activity)()['follows'])->toBe([]);
});

it('lists the accounts that starred 30 people a day, however many stars they gave', function (): void {
    $mass = User::factory()->create();
    $fan = User::factory()->create();
    $authors = ($this->people)(29);

    ($this->star)($mass, ($this->people)(30, '2026-10-23 00:00:00'));
    ($this->star)($fan, $authors);
    ($this->star)($fan, $authors);

    expect(($this->activity)()['stars'])
        ->toHaveCount(1)
        ->sequence(fn ($row) => $row->user->id->toBe($mass->id)->reach->toBe(30)->count->toBe(30)->newcomers->toBe(30));
});

it('lists the accounts that replied to 20 other people a day', function (): void {
    $mass = User::factory()->create();
    $chatty = User::factory()->create();

    ($this->reply)($mass, ($this->people)(20));
    ($this->reply)($chatty, ($this->people)(19));
    ($this->reply)($chatty, [$chatty]);

    expect(($this->activity)()['replies'])
        ->toHaveCount(1)
        ->sequence(fn ($row) => $row->user->id->toBe($mass->id)->reach->toBe(20)->count->toBe(20));
});

it('lists the accounts that posted 15 times a day', function (): void {
    $mass = User::factory()->create();
    $prolific = User::factory()->create();

    Commit::factory()->for($mass)->count(15)->create(['created_at' => '2026-10-23 01:00:00']);
    Commit::factory()->for($prolific)->count(14)->create(['created_at' => '2026-10-23 01:00:00']);
    Commit::factory()->for($prolific)->count(5)->create(['created_at' => '2026-10-22 11:00:00']);

    expect(($this->activity)()['posts'])
        ->toHaveCount(1)
        ->sequence(fn ($row) => $row->user->id->toBe($mass->id)->count->toBe(15)->reach->toBeNull());
});

it('scales the thresholds with the days of the window', function (): void {
    $poster = User::factory()->create();

    Commit::factory()->for($poster)->count(15)->create(['created_at' => '2026-10-20 01:00:00']);

    expect(($this->activity)(7)['posts'])->toBe([])
        ->and(resolve(BulkActivityQuery::class)->thresholds(7))->toBe([
            'follows' => ['perDay' => 50, 'total' => 350],
            'stars' => ['perDay' => 30, 'total' => 210],
            'replies' => ['perDay' => 20, 'total' => 140],
            'posts' => ['perDay' => 15, 'total' => 105],
        ]);
});

it('keeps the busiest accounts up to the limit', function (): void {
    User::factory()->count(3)->create()->each(fn (User $user, int $index) => Commit::factory()->for($user)->count(15 + $index)->create(['created_at' => '2026-10-23 01:00:00']));

    expect(($this->activity)(1, 2)['posts'])->toHaveCount(2)
        ->sequence(fn ($row) => $row->count->toBe(17), fn ($row) => $row->count->toBe(16));
});
