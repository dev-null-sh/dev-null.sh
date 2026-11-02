<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('pulls the conversation through a message, which clears what is unread', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user, 'first');
    $second = sendChatMessage($nuno, $this->user, 'second');

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-read.update', $nuno), ['through' => $second->id])->assertNoContent();

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'))
        ->assertJsonPath('data.0.ahead', 0)
        ->assertJsonPath('unread_count', 0);
});

it('only moves forward, and never past the last message', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $first = sendChatMessage($nuno, $this->user, 'first');
    $second = sendChatMessage($nuno, $this->user, 'second');
    $participant = Conversation::between($this->user, $nuno)->participants()->where('user_id', $this->user->id)->sole();

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-read.update', $nuno), ['through' => $second->id + 100])->assertNoContent();
    expect($participant->refresh()->read_through_id)->toBe($second->id);

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-read.update', $nuno), ['through' => $first->id])->assertNoContent();
    expect($participant->refresh()->read_through_id)->toBe($second->id);
});

it('has nothing to pull before the conversation starts', function (): void {
    $nuno = User::factory()->withRemotes()->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-read.update', $nuno), ['through' => 1])->assertNoContent();

    expect(Conversation::query()->count())->toBe(0);
});

it('needs a message to pull through', function (mixed $through): void {
    $nuno = User::factory()->withRemotes()->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-read.update', $nuno), ['through' => $through])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('through');
})->with([null, -1, 'last']);

it('hides people the user cannot message', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $this->user->blocking()->attach($nuno);

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-read.update', $nuno), ['through' => 1])->assertNotFound();
});

it('pulls an org through a message', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    $message = pushToOrg($nuno, $org);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-read.update', $org), ['through' => $message->id])->assertNoContent();

    expect(Conversation::query()->findOrFail($org)->participants()->where('user_id', $this->user->id)->sole()->read_through_id)->toBe($message->id);
});

it('keeps people out of orgs they are not in', function (): void {
    $maintainer = User::factory()->withRemotes()->create();
    [$nuno] = followersOf($maintainer, 1);
    $org = createOrg($maintainer, [$nuno]);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-read.update', $org), ['through' => 1])->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $nuno = User::factory()->withRemotes()->create();

    $this->putJson(route('api-conversation-read.update', $nuno), ['through' => 1])->assertUnauthorized();
});
