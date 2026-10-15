<?php

declare(strict_types=1);

use App\Actions\CreateUserFollows;
use App\Actions\DeleteUserFollow;
use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

it('creates selected follows and returns the number newly followed', function (): void {
    $follower = User::factory()->create();
    $people = User::factory()->count(2)->create();

    $count = resolve(CreateUserFollows::class)->handle($follower, $people);

    expect($count)->toBe(2);
    expect($follower->following()->count())->toBe(2);
    foreach ($people as $person) {
        $notification = $person->notifications()->sole();
        expect($notification->type)->toBe(NotificationType::Followed)
            ->and($notification->actor_id)->toBe($follower->id);
    }
});

it('counts only new follows and preserves already read notifications', function (): void {
    $follower = User::factory()->create();
    $person = User::factory()->create();
    $action = resolve(CreateUserFollows::class);
    $people = new Collection([$person]);
    $action->handle($follower, $people);
    $notification = $person->notifications()->sole();
    $notification->update(['read_at' => now()]);

    $count = $action->handle($follower, $people);

    expect($count)->toBe(0);
    expect($person->notifications()->sole()->id)->toBe($notification->id);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('does not notify again when a discovered person is unfollowed and followed again', function (): void {
    $follower = User::factory()->create();
    $person = User::factory()->create();
    $people = new Collection([$person]);
    $action = resolve(CreateUserFollows::class);
    $action->handle($follower, $people);

    $notification = $person->notifications()->sole();
    $notification->update(['read_at' => now()]);

    resolve(DeleteUserFollow::class)->handle($follower, $person);

    $count = $action->handle($follower, $people);

    expect($count)->toBe(1);
    expect($person->notifications()->sole()->id)->toBe($notification->id);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('does nothing when no people are selected', function (): void {
    $follower = User::factory()->create();

    $count = resolve(CreateUserFollows::class)->handle($follower, new Collection);

    expect($count)->toBe(0);
    expect($follower->following()->count())->toBe(0);
});
