<?php

declare(strict_types=1);

use App\Enums\RemotesFrom;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes(RemotesFrom::Following)->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->mark = User::factory()->withRemotes()->create();

    sendChatMessage($this->mark, $this->user, 'saw your polling PR');
});

function requestStatus(User $user, User $partner): RemoteStatus
{
    return ConversationParticipant::query()
        ->where('conversation_id', Conversation::between($user, $partner)->id)
        ->where('user_id', $user->id)
        ->sole()
        ->status;
}

it('merges a request into open conversations, so the user can reply', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-conversation-merge.store', $this->mark))->assertNoContent();

    expect(requestStatus($this->user, $this->mark))->toBe(RemoteStatus::Open);

    $this->actingAs($this->user)->postJson(route('conversation-message.store', $this->mark), [
        'body' => 'welcome',
        'client_id' => fake()->uuid(),
    ])->assertCreated();
});

it('drops a request without telling the sender', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-conversation-drop.store', $this->mark))->assertNoContent();

    expect(requestStatus($this->user, $this->mark))->toBe(RemoteStatus::Dropped);

    $this->actingAs($this->mark)->getJson(route('conversation-message.index', $this->user))->assertJsonPath('access', 'waiting');
});

it('only merges or drops a request fetched for the user', function (string $route): void {
    signedApiRequest(apiKeyPairFor($this->mark), 'POST', route($route, $this->user))->assertNotFound();

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-merge.store', $this->mark))->assertNoContent();
    signedApiRequest($this->keyPair, 'POST', route($route, $this->mark))->assertNotFound();
})->with(['api-conversation-merge.store', 'api-conversation-drop.store']);

it('has no requests to merge or drop while requests are off', function (string $route): void {
    config(['remotes.requests' => false]);

    signedApiRequest($this->keyPair, 'POST', route($route, $this->mark))->assertNotFound();

    expect(requestStatus($this->user, $this->mark))->toBe(RemoteStatus::Fetched);
})->with(['api-conversation-merge.store', 'api-conversation-drop.store']);

it('rejects requests that are not signed', function (): void {
    $this->postJson(route('api-conversation-merge.store', $this->mark))->assertUnauthorized();
});
