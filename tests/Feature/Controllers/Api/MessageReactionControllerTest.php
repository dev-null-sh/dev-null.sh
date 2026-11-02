<?php

declare(strict_types=1);

use App\Enums\RemotesFrom;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->nuno = User::factory()->withRemotes()->create();
});

it('reacts to a message once, and answers with its reactions in the order they were left', function (): void {
    $message = sendChatMessage($this->nuno, $this->user, 'migrations are green');
    $this->actingAs($this->nuno)->postJson(route('message-reaction.store', $message), ['emoji' => '✅'])->assertOk();

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '😂'])
        ->assertOk()
        ->assertExactJson(['reactions' => [
            ['user_id' => $this->nuno->id, 'emoji' => '✅'],
            ['user_id' => $this->user->id, 'emoji' => '😂'],
        ]]);

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '😂'])->assertOk();

    expect(MessageReaction::query()->where('user_id', $this->user->id)->count())->toBe(1);
});

it('takes back only the reaction the user removes, even when it was never left', function (): void {
    $message = sendChatMessage($this->nuno, $this->user);
    $this->actingAs($this->nuno)->postJson(route('message-reaction.store', $message), ['emoji' => '❤️'])->assertOk();
    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '❤️'])->assertOk();

    signedApiRequest($this->keyPair, 'DELETE', route('api-message-reaction.destroy', $message), ['emoji' => '❤️'])
        ->assertOk()
        ->assertExactJson(['reactions' => [['user_id' => $this->nuno->id, 'emoji' => '❤️']]]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-message-reaction.destroy', $message), ['emoji' => '❤️'])->assertOk();
});

it('only takes emoji as reactions', function (mixed $emoji): void {
    $message = sendChatMessage($this->nuno, $this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => $emoji])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('emoji');
})->with([':joy:', 'lol', '', null]);

it('caps how many reactions one person leaves on a message', function (): void {
    $message = sendChatMessage($this->nuno, $this->user);

    foreach (['😂', '✅', '❤️', '🚀', '👀', '🔥', '🎉', '👍', '💯', '🙏', '🤔', '👏'] as $emoji) {
        MessageReaction::factory()->create(['message_id' => $message->id, 'user_id' => $this->user->id, 'emoji' => $emoji]);
    }

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '💀'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['emoji' => 'You can leave up to 12 reactions on a message.']);

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '😂'])->assertOk();
});

it('keeps reactions to the people who can see the message', function (string $method): void {
    $message = sendChatMessage($this->nuno, User::factory()->withRemotes()->create());

    signedApiRequest($this->keyPair, $method, route('api-message-reaction.store', $message), ['emoji' => '😂'])->assertNotFound();
})->with(['POST', 'DELETE']);

it('keeps reactions from across a block, and from a user with Remotes off', function (string $who): void {
    $message = sendChatMessage($this->nuno, $this->user);

    match ($who) {
        'blocked' => $this->user->blocking()->attach($this->nuno),
        default => $this->user->update(['remotes_enabled' => false]),
    };

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '😂'])->assertNotFound();
})->with(['blocked', 'remotes off']);

it('waits for a request to be merged before reacting', function (): void {
    $this->user->update(['remotes_from' => RemotesFrom::Following]);
    $message = sendChatMessage($this->nuno, $this->user, 'hey, saw your polling PR');

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '👀'])
        ->assertForbidden()
        ->assertJsonPath('message', "You can't react until the request is merged.");
});

it('lets org members react within the history they can see, but not to org events', function (): void {
    $this->user->following()->attach($this->nuno);
    $org = createOrg($this->nuno, [$this->user]);
    $message = pushToOrg($this->nuno, $org, 'welcome');
    $event = Message::query()->whereNotNull('event')->sole();

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '👋'])
        ->assertOk()
        ->assertJsonPath('reactions', [['user_id' => $this->user->id, 'emoji' => '👋']]);

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $event), ['emoji' => '👋'])->assertNotFound();
});

it('ranks the emoji the user reacts with most', function (): void {
    $messages = collect(range(1, 3))->map(fn (int $index): Message => sendChatMessage($this->nuno, $this->user, "message {$index}"));

    $messages->each(fn (Message $message) => signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $message), ['emoji' => '🚀'])->assertOk());

    signedApiRequest($this->keyPair, 'POST', route('api-message-reaction.store', $messages->first()), ['emoji' => '👀'])->assertOk();
    $this->actingAs($this->nuno)->postJson(route('message-reaction.store', $messages->first()), ['emoji' => '😂'])->assertOk();

    signedApiRequest($this->keyPair, 'GET', route('api-message-reaction.index'))
        ->assertOk()
        ->assertExactJson(['usage' => [['emoji' => '🚀', 'count' => 3], ['emoji' => '👀', 'count' => 1]]]);
});

it('is not there while the user has Remotes off', function (): void {
    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'GET', route('api-message-reaction.index'))->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $message = sendChatMessage($this->nuno, $this->user);

    $this->postJson(route('api-message-reaction.store', $message), ['emoji' => '😂'])->assertUnauthorized();
});
