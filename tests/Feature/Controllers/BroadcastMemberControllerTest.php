<?php

declare(strict_types=1);

use App\Actions\CreateBroadcast;
use App\Actions\CreateBroadcastPublisher;
use App\Actions\CreateBroadcastSubscription;
use App\Actions\CreateMessage;
use App\Actions\DeleteBroadcastMember;
use App\Actions\DeleteBroadcastSubscription;
use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Enums\RemoteStatus;
use App\Models\BroadcastInvitation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->broadcast = resolve(CreateBroadcast::class)->handle($this->owner, 'crew', BroadcastVisibility::Public);
    $this->before = resolve(CreateMessage::class)->handle($this->owner, $this->broadcast, 'before joining', Str::uuid()->toString());
    resolve(CreateBroadcastSubscription::class)->handle($this->member, $this->broadcast);
    $this->message = resolve(CreateMessage::class)->handle($this->owner, $this->broadcast, 'after joining', Str::uuid()->toString());
});

it('kicks a subscriber without losing their first cutoff', function (): void {
    $url = route('broadcast-member.destroy', [$this->broadcast, $this->member->username]);
    $this->actingAs($this->owner)->deleteJson($url)->assertNoContent();

    $participant = $this->broadcast->participants()->where('user_id', $this->member->id)->firstOrFail();
    expect($participant->status)->toBe(RemoteStatus::Dropped)
        ->and($participant->visible_from_id)->toBe($this->before->id);

    $this->actingAs($this->member)->getJson(route('broadcast-message.index', $this->broadcast))->assertNotFound();
    $this->getJson(route('inbox.index'))->assertJsonCount(0, 'conversations')->assertJsonPath('unread', 0);
    $this->postJson(route('broadcast-subscription.store', $this->broadcast))->assertNoContent();
    $this->getJson(route('broadcast-message.index', $this->broadcast))
        ->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'after joining');
});

it('removes publisher privileges when kicked or banned', function (string $method): void {
    resolve(CreateBroadcastPublisher::class)->handle($this->broadcast, $this->member);
    $this->actingAs($this->owner)->json($method, route('broadcast-member.'.($method === 'DELETE' ? 'destroy' : 'update'), [$this->broadcast, $this->member->username]), ['banned' => true])->assertNoContent();

    $participant = $this->broadcast->participants()->where('user_id', $this->member->id)->firstOrFail();
    expect($participant->role)->toBe(BroadcastRole::Subscriber);

    if ($method === 'PUT') {
        $this->putJson(route('broadcast-member.update', [$this->broadcast, $this->member->username]), ['banned' => false])->assertNoContent();
    }

    $this->actingAs($this->member)->postJson(route('broadcast-subscription.store', $this->broadcast))->assertNoContent();
    $this->getJson(route('broadcast-message.index', $this->broadcast))->assertJsonPath('can_post', false);
})->with(['DELETE', 'PUT']);

it('bans a subscriber until unbanned and preserves history when they rejoin', function (): void {
    $url = route('broadcast-member.update', [$this->broadcast, $this->member->username]);
    $this->actingAs($this->owner)->putJson($url, ['banned' => true])->assertNoContent();

    $this->actingAs($this->member)->postJson(route('broadcast-subscription.store', $this->broadcast))->assertForbidden();
    $this->getJson(route('broadcast-message.index', $this->broadcast))->assertNotFound();
    $this->postJson(route('broadcast-message.store', $this->broadcast), ['body' => 'not allowed', 'client_id' => Str::uuid()->toString()])->assertNotFound();
    $this->deleteJson(route('broadcast-subscription.destroy', $this->broadcast))->assertNotFound();

    resolve(CreateMessage::class)->handle($this->owner, $this->broadcast, 'while banned', Str::uuid()->toString());
    $this->actingAs($this->owner)->putJson($url, ['banned' => false])->assertNoContent();

    $this->actingAs($this->member)->getJson(route('broadcast-message.index', $this->broadcast))->assertNotFound();
    $this->postJson(route('broadcast-subscription.store', $this->broadcast))->assertNoContent();
    $this->getJson(route('broadcast-message.index', $this->broadcast))->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.body', 'after joining')->assertJsonPath('messages.1.body', 'while banned');
});

it('keeps a ban in force when stale membership and sending actions run', function (): void {
    $this->actingAs($this->owner)->putJson(route('broadcast-member.update', [$this->broadcast, $this->member->username]), ['banned' => true])->assertNoContent();

    resolve(CreateBroadcastSubscription::class)->handle($this->member, $this->broadcast);
    resolve(DeleteBroadcastSubscription::class)->handle($this->member, $this->broadcast);
    resolve(DeleteBroadcastMember::class)->handle($this->broadcast, $this->member);
    resolve(CreateMessage::class)->handle($this->member, $this->broadcast, 'an already authorized send', Str::uuid()->toString());

    expect($this->broadcast->participants()->where('user_id', $this->member->id)->firstOrFail()->status)->toBe(RemoteStatus::Banned);
    $this->actingAs($this->member)->getJson(route('broadcast-message.index', $this->broadcast))->assertNotFound();
});

it('revokes message attachments, previews, reactions, reads and typing immediately', function (): void {
    Storage::fake();
    $message = $this->actingAs($this->owner)->post(route('broadcast-message.store', $this->broadcast), [
        'body' => 'notes at https://laravel.com/docs',
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('notes.png', 20, 20)],
    ], ['Accept' => 'application/json'])->assertCreated()->json('message');
    $this->deleteJson(route('broadcast-member.destroy', [$this->broadcast, $this->member->username]))->assertNoContent();

    $this->actingAs($this->member)->get('/remotes/images/'.$message['images'][0]['id'])->assertNotFound();
    $this->getJson(route('message-link-preview.show', $message['id']))->assertNotFound();
    $this->postJson(route('message-reaction.store', $message['id']), ['emoji' => '🎉'])->assertNotFound();
    $this->putJson(route('broadcast-read.update', $this->broadcast), ['through' => $message['id']])->assertNotFound();
    $this->putJson(route('broadcast-typing.update', $this->broadcast))->assertNotFound();
});

it('clears invitations and requires unbanning before a fresh invitation', function (): void {
    $this->broadcast->update(['visibility' => BroadcastVisibility::Invite]);
    BroadcastInvitation::query()->create(['conversation_id' => $this->broadcast->id, 'user_id' => $this->member->id]);
    $url = route('broadcast-member.update', [$this->broadcast, $this->member->username]);
    $this->actingAs($this->owner)->putJson($url, ['banned' => true])->assertNoContent();

    expect(BroadcastInvitation::query()->count())->toBe(0);
    $this->postJson(route('broadcast-invitation.store', $this->broadcast), ['member' => $this->member->username])->assertUnprocessable()->assertJsonValidationErrors('member');
    $this->actingAs($this->member)->getJson(route('broadcast.index'))->assertJsonCount(0, 'broadcasts');
    $this->get(route('broadcast.show', $this->broadcast))->assertNotFound();

    $this->actingAs($this->owner)->putJson($url, ['banned' => false])->assertNoContent();
    $this->actingAs($this->member)->postJson(route('broadcast-subscription.store', $this->broadcast))->assertForbidden();
    $this->actingAs($this->owner)->postJson(route('broadcast-invitation.store', $this->broadcast), ['member' => $this->member->username])->assertNoContent();
    $this->actingAs($this->member)->postJson(route('broadcast-subscription.store', $this->broadcast))->assertNoContent();
});

it('keeps moderation and the roster owner-only', function (bool $publisher): void {
    if ($publisher) {
        resolve(CreateBroadcastPublisher::class)->handle($this->broadcast, $this->member);
    }

    $this->actingAs($this->member)->getJson(route('broadcast-member.index', $this->broadcast))->assertForbidden();
    $this->putJson(route('broadcast-member.update', [$this->broadcast, $this->owner->username]), ['banned' => true])->assertForbidden();
    $this->deleteJson(route('broadcast-member.destroy', [$this->broadcast, $this->owner->username]))->assertForbidden();
})->with([false, true]);

it('does not allow removing the owner or unbanning an active member', function (): void {
    $this->actingAs($this->owner)->putJson(route('broadcast-member.update', [$this->broadcast, $this->owner->username]), ['banned' => true])->assertForbidden();
    $this->deleteJson(route('broadcast-member.destroy', [$this->broadcast, $this->owner->username]))->assertForbidden();
    $this->putJson(route('broadcast-member.update', [$this->broadcast, $this->member->username]), ['banned' => false])->assertForbidden();
    $this->putJson(route('broadcast-member.update', [$this->broadcast, $this->member->username]), ['banned' => 'not a boolean'])->assertForbidden();
});

it('hides moderation from outsiders and rejects targets from other broadcasts', function (): void {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->getJson(route('broadcast-member.index', $this->broadcast))->assertNotFound();
    $this->putJson(route('broadcast-member.update', [$this->broadcast, $this->member->username]), ['banned' => true])->assertNotFound();
    $this->deleteJson(route('broadcast-member.destroy', [$this->broadcast, $this->member->username]))->assertNotFound();
    $this->actingAs($this->owner)->putJson(route('broadcast-member.update', [$this->broadcast, $stranger->username]), ['banned' => true])->assertNotFound();
});

it('lists and searches active members and bans without exposing departed members', function (): void {
    $this->actingAs($this->owner)->putJson(route('broadcast-member.update', [$this->broadcast, $this->member->username]), ['banned' => true])->assertNoContent();
    $this->getJson(route('broadcast-member.index', $this->broadcast))->assertOk()->assertJsonCount(2, 'members')
        ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('members.1.is_banned', true);
    $this->getJson(route('broadcast-member.index', ['broadcast' => $this->broadcast, 'q' => '@'.$this->member->username]))->assertJsonCount(1, 'members');
    $this->putJson(route('broadcast-member.update', [$this->broadcast, $this->member->username]), ['banned' => false])->assertNoContent();
    $this->getJson(route('broadcast-member.index', $this->broadcast))->assertJsonCount(1, 'members');
});

it('paginates the owner roster without losing or duplicating members', function (): void {
    User::factory()->count(30)->create()->each(fn (User $user) => resolve(CreateBroadcastSubscription::class)->handle($user, $this->broadcast));
    $first = $this->actingAs($this->owner)->getJson(route('broadcast-member.index', $this->broadcast))->assertJsonCount(30, 'members');
    $cursor = $first->json('next_cursor');
    $second = $this->getJson(route('broadcast-member.index', ['broadcast' => $this->broadcast, 'after' => $cursor]))->assertJsonCount(2, 'members')->assertJsonPath('next_cursor', null);

    expect(collect([...$first->json('members'), ...$second->json('members')])->pluck('user.id')->unique())->toHaveCount(32);
});
