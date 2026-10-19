<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\UserConnectionsQuery;

it('lists the newest follow first', function (): void {
    $user = User::factory()->create();
    $earlier = User::factory()->create();
    $later = User::factory()->create();
    $user->followers()->attach($later);
    $user->followers()->attach($earlier, ['created_at' => now()->subMinute()]);

    expect(resolve(UserConnectionsQuery::class)->builder($user->followers(), null)->get()->modelKeys())
        ->toBe([$later->id, $earlier->id]);
});

it('says whether the viewer follows each one, and whether each follows the viewer', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $mutual = User::factory()->create();
    $stranger = User::factory()->create();
    $user->following()->attach($stranger, ['created_at' => now()->subMinute()]);
    $user->following()->attach($mutual);
    $viewer->following()->attach($mutual);
    $mutual->following()->attach($viewer);

    $connections = resolve(UserConnectionsQuery::class)->builder($user->following(), $viewer)->get();

    expect($connections->modelKeys())->toBe([$mutual->id, $stranger->id])
        ->and($connections->pluck('is_followed')->all())->toBe([true, false])
        ->and($connections->pluck('follows_you')->all())->toBe([true, false]);
});

it('says neither to a guest', function (): void {
    $user = User::factory()->create();
    $user->following()->attach(User::factory()->create());

    $connection = resolve(UserConnectionsQuery::class)->builder($user->following(), null)->sole();

    expect($connection->getAttributes())->not->toHaveKeys(['is_followed', 'follows_you']);
});
