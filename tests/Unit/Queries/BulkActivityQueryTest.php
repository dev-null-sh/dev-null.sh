<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use App\Queries\BulkActivityQuery;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));

    $this->activity = fn (int $limit = 10): array => resolve(BulkActivityQuery::class)
        ->get(CarbonImmutable::parse('2026-10-16 12:00:00'), $limit);

    $this->follow = fn (User $follower, User $followed, string $at) => $follower->following()
        ->attach($followed, ['created_at' => $at, 'updated_at' => $at]);
});

it('ranks the accounts that followed the most people, with the newcomers among them', function (): void {
    $mass = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $member = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $old = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $newcomers = User::factory()->count(3)->create(['created_at' => '2026-10-20 00:00:00']);

    $newcomers->each(fn (User $newcomer) => ($this->follow)($mass, $newcomer, '2026-10-20 01:00:00'));
    ($this->follow)($mass, $old, '2026-10-20 01:00:00');
    ($this->follow)($member, $old, '2026-10-20 01:00:00');
    ($this->follow)($member, $newcomers[0], '2026-10-01 00:00:00');

    expect(($this->activity)()['follows'])
        ->toHaveCount(2)
        ->sequence(
            fn ($row) => $row->user->id->toBe($mass->id)->count->toBe(4)->newcomers->toBe(3),
            fn ($row) => $row->user->id->toBe($member->id)->count->toBe(1)->newcomers->toBe(0),
        );
});

it('leaves out follows imported from GitHub, on either side, and bots', function (): void {
    $importer = User::factory()->create(['github_follows_imported_at' => '2026-10-20 01:00:05']);
    $imported = User::factory()->create(['github_follows_imported_at' => '2026-10-20 02:00:05']);
    $member = User::factory()->create();
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]);

    ($this->follow)($importer, $member, '2026-10-20 01:00:00');
    ($this->follow)($member, $imported, '2026-10-20 02:00:00');
    ($this->follow)($tom, $member, '2026-10-20 03:00:00');

    expect(($this->activity)()['follows'])->toBe([]);
});

it('ranks the accounts that starred, replied and posted the most since the start of the window', function (): void {
    $starrer = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $replier = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $newcomer = User::factory()->create(['created_at' => '2026-10-20 00:00:00']);
    $member = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $posts = Commit::factory()->for($newcomer)->count(2)->create(['created_at' => '2026-10-20 01:00:00']);
    $memberPost = Commit::factory()->for($member)->create(['created_at' => '2026-10-20 01:00:00']);
    Commit::factory()->for($member)->create(['created_at' => '2026-10-01 00:00:00']);

    $posts->each(fn (Commit $post) => $post->stars()->attach($starrer, ['created_at' => '2026-10-20 02:00:00', 'updated_at' => '2026-10-20 02:00:00']));
    $memberPost->stars()->attach($starrer, ['created_at' => '2026-10-20 02:00:00', 'updated_at' => '2026-10-20 02:00:00']);
    Commit::factory()->for($replier)->for($posts[0], 'parent')->create(['created_at' => '2026-10-20 03:00:00']);
    Commit::factory()->for($replier)->for($memberPost, 'parent')->create(['created_at' => '2026-10-20 03:00:00']);
    Commit::factory()->for($newcomer)->for($posts[0], 'parent')->create(['created_at' => '2026-10-20 03:00:00']);

    $activity = ($this->activity)();

    expect($activity['stars'])->sequence(fn ($row) => $row->user->id->toBe($starrer->id)->count->toBe(3)->reach->toBe(2)->newcomers->toBe(1))
        ->and($activity['replies'])->sequence(fn ($row) => $row->user->id->toBe($replier->id)->count->toBe(2)->reach->toBe(2)->newcomers->toBe(1))
        ->and($activity['posts'])->sequence(
            fn ($row) => $row->user->id->toBe($newcomer->id)->count->toBe(2)->reach->toBeNull(),
            fn ($row) => $row->user->id->toBe($member->id)->count->toBe(1),
        );
});

it('keeps the busiest accounts up to the limit', function (): void {
    User::factory()->count(3)->create()->each(fn (User $user, int $index) => Commit::factory()->for($user)->count($index + 1)->create(['created_at' => '2026-10-20 01:00:00']));

    expect(($this->activity)(2)['posts'])->toHaveCount(2)
        ->sequence(fn ($row) => $row->count->toBe(3), fn ($row) => $row->count->toBe(2));
});
