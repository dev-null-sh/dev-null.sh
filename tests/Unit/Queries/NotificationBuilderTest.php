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

it('keeps approved mentions', function (): void {
    $notification = Notification::factory()->mentioned()->create();

    expect(Notification::query()->relevant()->sole()->is($notification))->toBeTrue();
});

it('hides mentions once rejected', function (): void {
    $notification = Notification::factory()->mentioned()->create();

    resolve(RejectCommit::class)->handle($notification->commit);

    expect(Notification::query()->whereKey($notification->id)->relevant()->exists())->toBeFalse();
});

it('deletes mentions along with the commit', function (): void {
    $notification = Notification::factory()->mentioned()->create();

    resolve(DeleteCommit::class)->handle($notification->commit);

    expect(Notification::query()->exists())->toBeFalse();
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

it('keeps approved quotes', function (): void {
    $notification = Notification::factory()->quoted()->create();

    expect(Notification::query()->relevant()->sole()->is($notification))->toBeTrue();
});

it('hides quotes once rejected', function (): void {
    $notification = Notification::factory()->quoted()->create();

    resolve(RejectCommit::class)->handle($notification->commit);

    expect(Notification::query()->whereKey($notification->id)->relevant()->exists())->toBeFalse();
});

it('deletes quotes along with the quote', function (): void {
    $notification = Notification::factory()->quoted()->create();

    resolve(DeleteCommit::class)->handle($notification->commit);

    expect($notification->fresh())->toBeNull();
});

it('deletes quotes along with the quoted commit', function (): void {
    $notification = Notification::factory()->quoted()->create();

    resolve(DeleteCommit::class)->handle($notification->commit->quotedCommit);

    expect($notification->fresh())->toBeNull();
});

it('deletes notifications along with the actor', function (Notification $notification): void {
    resolve(DeleteUser::class)->handle($notification->actor);

    expect($notification->fresh())->toBeNull();
})->with([
    'followed' => fn (): Notification => Notification::factory()->create(),
    'replied' => fn (): Notification => Notification::factory()->replied()->create(),
    'quoted' => fn (): Notification => Notification::factory()->quoted()->create(),
]);

it('deletes notifications along with the recipient', function (): void {
    $notification = Notification::factory()->create();

    resolve(DeleteUser::class)->handle($notification->recipient);

    expect($notification->fresh())->toBeNull();
});
