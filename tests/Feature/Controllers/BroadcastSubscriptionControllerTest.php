<?php

declare(strict_types=1);

use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Models\BroadcastInvitation;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (! function_exists('createBroadcast')) {
    function createBroadcast(User $owner, string $name = 'ship log', BroadcastVisibility $visibility = BroadcastVisibility::Public): string
    {
        return test()->actingAs($owner)->postJson(route('broadcast.store'), [
            'name' => $name,
            'visibility' => $visibility->value,
        ])->assertCreated()->json('broadcast.id');
    }
}

if (! function_exists('subscribeToBroadcast')) {
    function subscribeToBroadcast(User $subscriber, string $broadcast): void
    {
        test()->actingAs($subscriber)->postJson(route('broadcast-subscription.store', $broadcast))->assertNoContent();
    }
}

if (! function_exists('pushToBroadcast')) {
    function pushToBroadcast(User $sender, string $broadcast, string $body = 'hello'): Message
    {
        test()->actingAs($sender)->postJson(route('broadcast-message.store', $broadcast), [
            'body' => $body,
            'client_id' => Str::uuid()->toString(),
        ])->assertCreated();

        return Message::query()->latest('id')->firstOrFail();
    }
}

it('subscribes to a public broadcast and starts unread-free', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    pushToBroadcast($mark, $broadcast);

    subscribeToBroadcast($nuno, $broadcast);

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.kind', 'broadcast')
        ->assertJsonPath('conversations.0.ahead', 0)
        ->assertJsonPath('unread', 0);

    pushToBroadcast($mark, $broadcast, 'while subscribed');

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.ahead', 1)->assertJsonPath('unread', 1);
});

it('never previews what the subscriber cannot see', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    pushToBroadcast($mark, $broadcast, 'secret before joining');

    subscribeToBroadcast($nuno, $broadcast);

    $inbox = $this->actingAs($nuno)->getJson(route('inbox.index'))->assertOk()
        ->assertJsonPath('conversations.0.last_message', null);

    expect($inbox->json('conversations.0.last_message_at'))->toBeString();

    pushToBroadcast($mark, $broadcast, 'welcome');

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.last_message.body', 'welcome');
});

it('denies subscribing to an invite-only broadcast without an invitation', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark, 'secret crew', BroadcastVisibility::Invite);

    $this->actingAs($nuno)->postJson(route('broadcast-subscription.store', $broadcast))
        ->assertForbidden()->assertJsonPath('message', 'This broadcast is invite-only.');

    $this->actingAs($mark)->postJson(route('broadcast-invitation.store', $broadcast), ['member' => $nuno->username])->assertNoContent();

    subscribeToBroadcast($nuno, $broadcast);

    expect(BroadcastInvitation::query()->count())->toBe(0)
        ->and(Conversation::query()->findOrFail($broadcast)->activeBroadcastRoleOf($nuno))->toBe(BroadcastRole::Subscriber);
});

it('removes everything of the broadcast when a subscriber leaves', function (): void {
    Queue::fake();
    Storage::fake();

    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    pushToBroadcast($mark, $broadcast);

    subscribeToBroadcast($nuno, $broadcast);

    $message = test()->actingAs($mark)->post(route('broadcast-message.store', $broadcast), [
        'body' => 'notes at https://laravel.com/docs',
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('notes.png', 20, 20)],
    ], ['Accept' => 'application/json'])->assertCreated()->json('message');

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonPath('unread', 1);

    $this->actingAs($nuno)->deleteJson(route('broadcast-subscription.destroy', $broadcast))->assertNoContent();

    $this->getJson(route('inbox.index'))->assertJsonCount(0, 'conversations')->assertJsonPath('unread', 0);
    $this->getJson(route('broadcast-message.index', $broadcast))->assertNotFound();
    $this->get("/remotes/broadcasts/{$broadcast}")->assertOk();
    $this->get("/remotes/images/{$message['images'][0]['id']}")->assertNotFound();
    $this->getJson(route('message-link-preview.show', $message['id']))->assertNotFound();
    $this->postJson(route('message-reaction.store', $message['id']), ['emoji' => '🎉'])->assertNotFound();
    $this->putJson(route('broadcast-read.update', $broadcast), ['through' => $message['id']])->assertNotFound();
    $this->putJson(route('broadcast-typing.update', $broadcast))->assertNotFound();
});

it('keeps the participant row and the first cutoff across a rejoin', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    $before = pushToBroadcast($mark, $broadcast, 'before the first join');

    subscribeToBroadcast($nuno, $broadcast);
    $during = pushToBroadcast($mark, $broadcast, 'while subscribed');
    $this->actingAs($nuno)->putJson(route('broadcast-read.update', $broadcast), ['through' => $during->id])->assertNoContent();

    $this->actingAs($nuno)->deleteJson(route('broadcast-subscription.destroy', $broadcast))->assertNoContent();

    $absent = pushToBroadcast($mark, $broadcast, 'while away');

    subscribeToBroadcast($nuno, $broadcast);

    $participant = ConversationParticipant::query()->where('conversation_id', $broadcast)->where('user_id', $nuno->id)->firstOrFail();

    expect($participant->visible_from_id)->toBe($before->id)
        ->and($participant->read_through_id)->toBe($during->id);

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonPath('unread', 1);

    $thread = $this->getJson(route('broadcast-message.index', $broadcast))->assertOk();

    expect(collect($thread->json('messages'))->pluck('id')->all())->toBe([$during->id, $absent->id]);
});

it('keeps the owner an owner, with no leaving', function (): void {
    $mark = User::factory()->create();
    $broadcast = createBroadcast($mark);

    $this->actingAs($mark)->deleteJson(route('broadcast-subscription.destroy', $broadcast))
        ->assertForbidden()->assertJsonPath('message', 'An owner cannot leave their broadcast.');

    expect(Conversation::query()->findOrFail($broadcast)->activeBroadcastRoleOf($mark))->toBe(BroadcastRole::Owner);
});

it('keeps a blocked stranger from subscribing', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    $mark->blocking()->attach($nuno);

    $this->actingAs($nuno)->postJson(route('broadcast-subscription.store', $broadcast))
        ->assertForbidden()->assertJsonPath('message', 'A block stands between you and the owner.');
});
