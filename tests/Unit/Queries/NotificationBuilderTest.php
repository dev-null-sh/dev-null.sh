<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Actions\DeleteUser;
use App\Actions\FollowUser;
use App\Actions\RejectCommit;
use App\Actions\UnfollowUser;
use App\Models\Notification;

it('scopes notifications to unread ones', function (): void {
    $unread = Notification::factory()->create();
    Notification::factory()->read()->create();

    expect(Notification::query()->unread()->sole()->is($unread))->toBeTrue();
});

it('keeps follows that still hold', function (): void {
    $notification = Notification::factory()->create();

    expect(Notification::query()->relevant()->sole()->is($notification))->toBeTrue();
});

it('hides follows once unfollowed, until followed again', function (): void {
    $notification = Notification::factory()->create();

    resolve(UnfollowUser::class)->handle($notification->actor, $notification->recipient);

    expect(Notification::query()->relevant()->exists())->toBeFalse();

    resolve(FollowUser::class)->handle($notification->actor, $notification->recipient);

    expect(Notification::query()->relevant()->sole()->is($notification))->toBeTrue();
});

it('does not mistake a follow back for a follow', function (): void {
    $notification = Notification::factory()->create();

    resolve(UnfollowUser::class)->handle($notification->actor, $notification->recipient);
    resolve(FollowUser::class)->handle($notification->recipient, $notification->actor);

    expect(Notification::query()->where('recipient_id', $notification->recipient_id)->relevant()->exists())->toBeFalse();
});

it('keeps approved replies', function (): void {
    $notification = Notification::factory()->replied()->create();

    expect(Notification::query()->relevant()->sole()->is($notification))->toBeTrue();
});

it('hides replies once rejected', function (): void {
    $notification = Notification::factory()->replied()->create();

    resolve(RejectCommit::class)->handle($notification->commit);

    expect(Notification::query()->whereKey($notification->id)->relevant()->exists())->toBeFalse();
});

it('deletes replies along with the reply', function (): void {
    $notification = Notification::factory()->replied()->create();

    resolve(DeleteCommit::class)->handle($notification->commit);

    expect($notification->fresh())->toBeNull();
});

it('deletes replies along with the replied commit', function (): void {
    $notification = Notification::factory()->replied()->create();

    resolve(DeleteCommit::class)->handle($notification->commit->parent);

    expect($notification->fresh())->toBeNull();
});

it('deletes notifications along with the actor', function (Notification $notification): void {
    resolve(DeleteUser::class)->handle($notification->actor);

    expect($notification->fresh())->toBeNull();
})->with([
    'followed' => fn (): Notification => Notification::factory()->create(),
    'replied' => fn (): Notification => Notification::factory()->replied()->create(),
]);

it('deletes notifications along with the recipient', function (): void {
    $notification = Notification::factory()->create();

    resolve(DeleteUser::class)->handle($notification->recipient);

    expect($notification->fresh())->toBeNull();
});

it('hides notifications from muted actors until they are unmuted', function (Notification $notification): void {
    $notification->recipient->muting()->attach($notification->actor_id);

    expect(Notification::query()->relevant()->exists())->toBeFalse();

    $notification->recipient->muting()->detach($notification->actor_id);

    expect(Notification::query()->relevant()->sole()->is($notification))->toBeTrue();
})->with([
    'followed' => fn (): Notification => Notification::factory()->create(),
    'replied' => fn (): Notification => Notification::factory()->replied()->create(),
]);

it('hides replies across a block in either direction', function (bool $recipientBlocks): void {
    $notification = Notification::factory()->replied()->create();

    $recipientBlocks
        ? $notification->recipient->blocking()->attach($notification->actor_id)
        : $notification->actor->blocking()->attach($notification->recipient_id);

    expect(Notification::query()->relevant()->exists())->toBeFalse();
})->with([true, false]);
