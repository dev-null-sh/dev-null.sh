<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use App\Queries\LogQueries\TrendingLogQuery;

function trendingCommit(array $attributes = [], int $stars = 0, int $replies = 0): Commit
{
    $commit = Commit::factory()->create($attributes);

    $commit->stars()->attach(User::factory()->count($stars)->create());
    Commit::factory()->for($commit, 'parent')->count($replies)->create();

    return $commit;
}

it('moves a commit six hours forward for each star and twelve for each reply', function (): void {
    $fresh = trendingCommit(stars: 1);
    $starred = trendingCommit(['created_at' => now()->subHours(11)], stars: 3);
    $replied = trendingCommit(['created_at' => now()->subDay()], replies: 3);
    $stale = trendingCommit(['created_at' => now()->subDays(2)], stars: 1, replies: 2);

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$replied->id, $starred->id, $fresh->id, $stale->id]);
});

it('ranks fresh commits above older ones with the same engagement', function (): void {
    $older = trendingCommit(['created_at' => now()->subMinute()], stars: 2);
    $fresher = trendingCommit(stars: 2);

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$fresher->id, $older->id]);
});

it('breaks ties by id', function (): void {
    $first = trendingCommit(['id' => '00000000-0000-7000-8000-000000000000'], stars: 1);
    $last = trendingCommit(['id' => 'ffffffff-ffff-7fff-bfff-ffffffffffff'], stars: 1);

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$last->id, $first->id]);
});

it('ignores stars and replies from the author', function (): void {
    $selfPromoted = trendingCommit();
    $selfPromoted->stars()->attach($selfPromoted->user);
    Commit::factory()->for($selfPromoted->user)->for($selfPromoted, 'parent')->count(3)->create();

    $starred = trendingCommit(['created_at' => now()->subHours(5)], stars: 1);
    $starred->stars()->attach($starred->user);

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$starred->id]);
});

it('only counts approved replies that are not deleted', function (): void {
    $starred = trendingCommit(stars: 1);
    $withHiddenReplies = trendingCommit(['created_at' => now()->subHour()], stars: 1);
    Commit::factory()->for($withHiddenReplies, 'parent')->pending()->create();
    Commit::factory()->for($withHiddenReplies, 'parent')->rejected()->create();
    Commit::factory()->for($withHiddenReplies, 'parent')->create()->delete();
    Commit::factory()->for(trendingCommit(), 'parent')->pending()->create();

    $commits = resolve(TrendingLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$starred->id, $withHiddenReplies->id]);
});

it('only includes approved top-level commits from the last week', function (): void {
    $user = User::factory()->create();

    $thisWeek = trendingCommit(['created_at' => now()->subWeek()], stars: 1);
    trendingCommit(['created_at' => now()->subWeek()->subSecond()], stars: 5);
    trendingCommit(['user_id' => $user->id, 'status' => CommitStatus::Pending], stars: 1);
    trendingCommit(['status' => CommitStatus::Rejected], stars: 1);
    trendingCommit(stars: 1)->delete();
    $parent = trendingCommit(['created_at' => now()->subDays(6)]);
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $reply->stars()->attach(User::factory()->count(5)->create());

    $commits = resolve(TrendingLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$parent->id, $thisWeek->id]);
});

it('loads what the log shows for each commit', function (): void {
    $user = User::factory()->create();
    $commit = trendingCommit(['link_preview_id' => LinkPreview::factory()->fetched()->create()->id], stars: 1, replies: 2);
    $commit->stars()->attach($user);
    Commit::factory()->for($commit, 'parent')->pending()->for($user)->create();
    Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $commit->id]);

    $loaded = resolve(TrendingLogQuery::class)->builder($user)->sole();

    expect($loaded->relationLoaded('user'))->toBeTrue()
        ->and($loaded->relationLoaded('linkPreview'))->toBeTrue()
        ->and($loaded->stars_count)->toBe(2)
        ->and($loaded->starred)->toBeTrue()
        ->and($loaded->replies_count)->toBe(3)
        ->and($loaded->is_saved)->toBeTrue();
});
