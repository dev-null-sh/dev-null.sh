<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\EveryoneLogQuery;

it('includes commits of everyone newest first', function (): void {
    $older = Commit::factory()->create(['created_at' => now()->subMinute()]);
    $newer = Commit::factory()->create();

    $query = resolve(EveryoneLogQuery::class);

    $commits = $query->builder(User::factory()->create())->get();

    expect($commits->modelKeys())->toBe([$newer->id, $older->id])
        ->and($commits->first()->relationLoaded('user'))->toBeTrue()
        ->and($commits->first()->user->is_following)->toBeFalse()
        ->and($commits->first()->user->followers_count)->toBe(0)
        ->and($commits->first()->user->following_count)->toBe(0);
});

it('only includes approved commits of others', function (): void {
    $user = User::factory()->create();

    $approved = Commit::factory()->create(['created_at' => now()->subMinutes(2)]);
    $ownPending = Commit::factory()->for($user)->pending()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();

    $commits = resolve(EveryoneLogQuery::class)->builder($user)->get();

    expect($commits->modelKeys())->toBe([$ownPending->id, $approved->id]);
});
