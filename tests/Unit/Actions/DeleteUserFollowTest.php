<?php

declare(strict_types=1);

use App\Actions\DeleteUserFollow;
use App\Enums\RemoteStatus;
use App\Models\ConversationParticipant;
use App\Models\User;

it('may unfollow a user', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $follower->following()->attach($user);

    $action = resolve(DeleteUserFollow::class);

    $action->handle($follower, $user);

    expect($follower->isFollowing($user))->toBeFalse();
});

it('does nothing when not following', function (): void {
    $follower = User::factory()->create();
    $user = User::factory()->create();

    $action = resolve(DeleteUserFollow::class);

    $action->handle($follower, $user);

    expect($follower->following()->count())->toBe(0);
});

it('withdraws access to an existing conversation', function (): void {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    $follower->following()->attach($followed);
    sendChatMessage($followed, $follower, 'hello');

    resolve(DeleteUserFollow::class)->handle($follower, $followed);

    expect(ConversationParticipant::query()
        ->where('user_id', $follower->id)
        ->sole()
        ->status)->toBe(RemoteStatus::Dropped);
});

it('keeps a conversation open when no follow was removed', function (): void {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    $follower->following()->attach($followed);
    sendChatMessage($followed, $follower, 'hello');
    $follower->following()->detach($followed);

    resolve(DeleteUserFollow::class)->handle($follower, $followed);

    expect(ConversationParticipant::query()
        ->where('user_id', $follower->id)
        ->sole()
        ->status)->toBe(RemoteStatus::Open);
});
