<?php

declare(strict_types=1);

use App\Actions\UpdateCommitHotScores;
use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LogQueries\LatestLogQuery;

it('includes commits of everyone newest first, however popular they are', function (): void {
    $starred = Commit::factory()->create(['created_at' => now()->subHour()]);
    $starred->stars()->attach(User::factory()->create());
    resolve(UpdateCommitHotScores::class)->handle([$starred->id]);
    $newer = Commit::factory()->create();

    $commits = resolve(LatestLogQuery::class)->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$newer->id, $starred->id])
        ->and($commits->first()->relationLoaded('user'))->toBeTrue()
        ->and($commits->first()->relationLoaded('linkPreview'))->toBeTrue();
});

it('shows older submissions first when they are approved later', function (): void {
    $delayed = Commit::factory()->pending()->create(['created_at' => now()->subHours(2)]);
    $recent = Commit::factory()->create(['created_at' => now()->subMinute()]);

    $this->travel(1)->minute();
    resolve(UpdateCommitStatus::class)->handle($delayed, CommitStatus::Approved);

    $commits = resolve(LatestLogQuery::class)->builder(null)->get();

    expect($commits->modelKeys())->toBe([$delayed->id, $recent->id]);
});

it('only includes approved top-level commits of others', function (): void {
    $user = User::factory()->create();

    $approved = Commit::factory()->create(['created_at' => now()->subMinutes(2)]);
    $ownPending = Commit::factory()->for($user)->pending()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();
    Commit::factory()->for($approved, 'parent')->create();

    $commits = resolve(LatestLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$ownPending->id, $approved->id]);
});

it('leaves out commits by muted people and across blocks', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();

    $user->muting()->attach($muted);
    $user->blocking()->attach($blocked);
    $blocker->blocking()->attach($user);

    $visible = Commit::factory()->create();
    Commit::factory()->for($muted)->create();
    Commit::factory()->for($blocked)->create();
    Commit::factory()->for($blocker)->create();

    $commits = resolve(LatestLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$visible->id]);
});
