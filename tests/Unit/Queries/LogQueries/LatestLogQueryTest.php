<?php

declare(strict_types=1);

use App\Actions\UpdateCommitHotScores;
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
        ->and($commits->first()->relationLoaded('author'))->toBeTrue()
        ->and($commits->first()->relationLoaded('linkPreview'))->toBeTrue();
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
