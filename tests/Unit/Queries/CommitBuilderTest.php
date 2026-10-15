<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('scopes commits to those visible to a user', function (): void {
    $user = User::factory()->create();

    $approved = Commit::factory()->create();
    $ownPending = Commit::factory()->for($user)->pending()->create();
    $ownRejected = Commit::factory()->for($user)->rejected()->create();
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();

    expect(Commit::query()->visibleTo($user)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$approved->id, $ownPending->id, $ownRejected->id])->sort()->values()->all());
});

it('scopes commits to pending ones', function (): void {
    $pending = Commit::factory()->pending()->create();
    Commit::factory()->create();
    Commit::factory()->rejected()->create();

    expect(Commit::query()->pending()->sole()->is($pending))->toBeTrue();
});

it('scopes commits to top-level ones', function (): void {
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->create();

    expect(Commit::query()->topLevel()->pluck('id')->all())->toBe([$parent->id]);
});

it('scopes commits to approved ones', function (): void {
    $user = User::factory()->create();
    $approved = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    expect(Commit::query()->approved()->sole()->is($approved))->toBeTrue();
});
