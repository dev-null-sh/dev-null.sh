<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\SuggestedCommitsQuery;

it('suggests the latest commit of each other user', function (): void {
    $user = User::factory()->create();
    $author = User::factory()->create();

    Commit::factory()->for($author)->create(['created_at' => now()->subMinute()]);
    $latest = Commit::factory()->for($author)->create();
    $other = Commit::factory()->create(['created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($user)->create();

    $commits = resolve(SuggestedCommitsQuery::class)->get($user);

    expect($commits->pluck('id')->all())->toBe([$latest->id, $other->id])
        ->and($commits->first()->relationLoaded('user'))->toBeTrue();
});

it('only suggests approved commits', function (): void {
    $user = User::factory()->create();

    $approved = Commit::factory()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();

    $commits = resolve(SuggestedCommitsQuery::class)->get($user);

    expect($commits->pluck('id')->all())->toBe([$approved->id]);
});

it('does not suggest deleted commits', function (): void {
    $commit = Commit::factory()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->create()->delete();

    $commits = resolve(SuggestedCommitsQuery::class)->get(User::factory()->create());

    expect($commits->pluck('id')->all())->toBe([$commit->id]);
});

it('limits the suggested commits', function (?int $limit, int $count): void {
    $user = User::factory()->create();

    Commit::factory()->count(5)->create();

    $query = resolve(SuggestedCommitsQuery::class);

    $commits = $limit === null ? $query->get($user) : $query->get($user, $limit);

    expect($commits)->toHaveCount($count)
        ->and($commits->keys()->all())->toBe(range(0, $count - 1));
})->with([
    'default' => [null, 3],
    'custom' => [1, 1],
]);
