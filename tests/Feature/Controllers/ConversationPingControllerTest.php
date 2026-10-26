<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Enums\RemoteStatus;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
    $this->user = User::factory()->withRemotes()->create();
});

it('records a ping, brings both participants out of the stash and marks it read only for its sender', function (): void {
    $partner = User::factory()->withRemotes()->create();
    $previous = sendChatMessage($partner, $this->user);
    $conversation = $previous->conversation;
    ConversationParticipant::query()->where('conversation_id', $conversation->id)->update(['archived_at' => now()]);
    $this->travel(10)->seconds();

    $response = $this->actingAs($this->user)->postJson(route('conversation-ping.store', $partner))->assertCreated();

    $ping = Message::query()->latest('id')->firstOrFail();
    $response->assertJsonPath('message.id', $ping->id)
        ->assertJsonPath('message.user_id', $this->user->id)
        ->assertJsonPath('message.body', '')
        ->assertJsonPath('message.event', 'ping');
    expect($ping->event)->toBe(OrgEvent::Ping)
        ->and($ping->conversation_id)->toBe($conversation->id)
        ->and($ping->client_id)->toBeUuid()
        ->and($conversation->fresh()->last_message_id)->toBe($ping->id);
    $this->assertDatabaseCount('messages', 2);
    $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $conversation->id, 'user_id' => $this->user->id, 'read_through_id' => $ping->id, 'read_at' => now(), 'archived_at' => null, 'last_message_at' => now()]);
    $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $conversation->id, 'user_id' => $partner->id, 'read_through_id' => $previous->id, 'archived_at' => null, 'last_message_at' => now()]);
});

it('requires a conversation before pinging', function (): void {
    $partner = User::factory()->withRemotes()->create();

    $this->actingAs($this->user)->postJson(route('conversation-ping.store', $partner))->assertForbidden()
        ->assertJsonPath('message', "Say hi to {$partner->mention()} before pinging them.");

    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('messages', 0);
});

it('hides a ping target when either person blocks the other', function (bool $senderBlocks): void {
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $this->user);
    $blocker = $senderBlocks ? $this->user : $partner;
    $blocked = $senderBlocks ? $partner : $this->user;
    $blocker->blocking()->attach($blocked);

    $this->actingAs($this->user)->postJson(route('conversation-ping.store', $partner))->assertNotFound();

    $this->assertDatabaseCount('messages', 1);
})->with([true, false]);

it('rejects pings while the sender has Remotes disabled', function (): void {
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $this->user);
    $this->user->update(['remotes_enabled' => false]);

    $this->actingAs($this->user)->postJson(route('conversation-ping.store', $partner))->assertNotFound();

    $this->assertDatabaseCount('messages', 1);
});

it('rejects pings while the partner has Remotes disabled', function (): void {
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $this->user);
    $partner->update(['remotes_enabled' => false]);

    $this->actingAs($this->user)->postJson(route('conversation-ping.store', $partner))->assertForbidden()
        ->assertJsonPath('message', "You can't ping {$partner->mention()} right now.");

    $this->assertDatabaseCount('messages', 1);
});

it('does not ping a request before it is merged or after it is dropped', function (string $side, RemoteStatus $status): void {
    $partner = User::factory()->withRemotes()->create();
    $message = sendChatMessage($partner, $this->user);
    ConversationParticipant::query()->where('conversation_id', $message->conversation_id)
        ->where('user_id', $side === 'sender' ? $this->user->id : $partner->id)->update(['status' => $status]);

    $this->actingAs($this->user)->postJson(route('conversation-ping.store', $partner))->assertForbidden()
        ->assertJsonPath('message', "You can't ping {$partner->mention()} right now.");

    $this->assertDatabaseCount('messages', 1);
})->with([
    'incoming request' => ['sender', RemoteStatus::Fetched],
    'outgoing request' => ['partner', RemoteStatus::Fetched],
    'dropped request' => ['partner', RemoteStatus::Dropped],
]);

it('returns 401 for a guest ping', function (): void {
    $partner = User::factory()->withRemotes()->create();

    $this->postJson(route('conversation-ping.store', $partner))->assertUnauthorized();

    $this->assertDatabaseCount('messages', 0);
});

it('limits a sender to three pings a minute across web and API remotes', function (): void {
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $this->user);
    $keyPair = apiKeyPairFor($this->user);
    [$member] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$member]);

    $this->actingAs($this->user)->postJson(route('conversation-ping.store', $partner))->assertCreated();
    signedApiRequest($keyPair, 'POST', route('api-conversation-ping.store', $partner))->assertCreated();
    $this->postJson(route('org-ping.store', $org))->assertCreated();
    signedApiRequest($keyPair, 'POST', route('api-org-ping.store', $org))->assertTooManyRequests()
        ->assertJsonPath('message', 'Three pings a minute, tops. Try again in a bit.')
        ->assertHeader('Retry-After', '60');

    expect(Message::query()->where('event', OrgEvent::Ping)->count())->toBe(3);

    $this->travel(61)->seconds();

    signedApiRequest($keyPair, 'POST', route('api-org-ping.store', $org))->assertCreated();

    expect(Message::query()->where('event', OrgEvent::Ping)->count())->toBe(4);
});
