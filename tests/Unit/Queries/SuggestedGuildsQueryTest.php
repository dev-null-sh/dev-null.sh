<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\User;
use App\Queries\SuggestedGuildsQuery;

it('suggests the guilds most of the people the user follows joined, then the largest', function (): void {
    $user = User::factory()->create();
    [$first, $second] = User::factory()->count(2)->create();
    $user->following()->attach([$first->id, $second->id]);

    $joinedByBoth = Guild::factory()->create(['handle' => 'both']);
    $joinedByOne = Guild::factory()->create(['handle' => 'one']);
    $largest = Guild::factory()->create(['handle' => 'largest']);
    $alphabetical = Guild::factory()->create(['handle' => 'alphabetical']);
    $last = Guild::factory()->create(['handle' => 'last']);

    $joinedByBoth->members()->attach([$first->id, $second->id], ['joined_at' => now()]);
    $joinedByOne->members()->attach($first, ['joined_at' => now()]);
    $largestMembers = User::factory()->count(3)->create();
    $largest->members()->attach($largestMembers, ['joined_at' => now()]);
    User::factory()->create()->following()->attach($largestMembers->pluck('id'));
    $alphabetical->members()->attach(User::factory()->count(1)->create(), ['joined_at' => now()]);
    $last->members()->attach(User::factory()->count(1)->create(), ['joined_at' => now()]);

    $suggested = resolve(SuggestedGuildsQuery::class)->get($user, limit: 4);

    expect($suggested->pluck('id')->all())->toBe([$joinedByBoth->id, $joinedByOne->id, $largest->id, $alphabetical->id])
        ->and($suggested->pluck('followed_members_count')->all())->toBe([2, 1, 0, 0]);
});

it('leaves out the guilds the user joined or muted, and paused guilds', function (): void {
    $user = User::factory()->create();
    $joined = Guild::factory()->create();
    $muted = Guild::factory()->create();
    Guild::factory()->create(['user_id' => null]);
    $suggestable = Guild::factory()->create();

    $joined->members()->attach($user, ['joined_at' => now()]);
    $muted->muters()->attach($user, ['muted_at' => now()]);

    expect(resolve(SuggestedGuildsQuery::class)->get($user)->pluck('id')->all())->toBe([$suggestable->id]);
});

it('suggests three guilds by default', function (): void {
    Guild::factory()->count(4)->create();

    expect(resolve(SuggestedGuildsQuery::class)->get(User::factory()->create()))->toHaveCount(3);
});
