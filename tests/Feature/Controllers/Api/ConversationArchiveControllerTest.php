<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

function participantOf(User $user, string $conversationId): ConversationParticipant
{
    return ConversationParticipant::query()->where('conversation_id', $conversationId)->where('user_id', $user->id)->sole();
}

it('stashes a conversation for the user only, and pops it back', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user);
    $conversation = Conversation::between($this->user, $nuno);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-archive.store', $nuno))->assertNoContent();

    expect(participantOf($this->user, $conversation->id)->archived_at)->not->toBeNull()
        ->and(participantOf($nuno, $conversation->id)->archived_at)->toBeNull();

    signedApiRequest($this->keyPair, 'DELETE', route('api-conversation-archive.destroy', $nuno))->assertNoContent();

    expect(participantOf($this->user, $conversation->id)->archived_at)->toBeNull();
});

it('pops a stashed conversation back when either of them writes', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-archive.store', $nuno))->assertNoContent();
    sendChatMessage($nuno, $this->user, 'still there?');

    expect(participantOf($this->user, Conversation::between($this->user, $nuno)->id)->archived_at)->toBeNull();
});

it('has nothing to stash before the conversation starts', function (string $method, string $route): void {
    $nuno = User::factory()->withRemotes()->create();

    signedApiRequest($this->keyPair, $method, route($route, $nuno))->assertNoContent();

    expect(Conversation::query()->count())->toBe(0);
})->with([
    'stash' => ['POST', 'api-conversation-archive.store'],
    'pop' => ['DELETE', 'api-conversation-archive.destroy'],
]);

it('hides stashing from people the user cannot message', function (string $method, string $route): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user);
    $nuno->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, $method, route($route, $nuno))->assertNotFound();
})->with([
    'stash' => ['POST', 'api-conversation-archive.store'],
    'pop' => ['DELETE', 'api-conversation-archive.destroy'],
]);

it('stashes an org for the user only, and pops it back', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);

    signedApiRequest($this->keyPair, 'POST', route('api-org-archive.store', $org))->assertNoContent();

    expect(participantOf($this->user, $org)->archived_at)->not->toBeNull()
        ->and(participantOf($nuno, $org)->archived_at)->toBeNull();

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-archive.destroy', $org))->assertNoContent();

    expect(participantOf($this->user, $org)->archived_at)->toBeNull();
});

it('keeps people out of orgs they are not in', function (string $method, string $route): void {
    $maintainer = User::factory()->withRemotes()->create();
    [$nuno] = followersOf($maintainer, 1);
    $org = createOrg($maintainer, [$nuno]);

    signedApiRequest($this->keyPair, $method, route($route, $org))->assertNotFound();
})->with([
    'stash' => ['POST', 'api-org-archive.store'],
    'pop' => ['DELETE', 'api-org-archive.destroy'],
]);

it('rejects requests that are not signed', function (): void {
    $nuno = User::factory()->withRemotes()->create();

    $this->postJson(route('api-conversation-archive.store', $nuno))->assertUnauthorized();
});
