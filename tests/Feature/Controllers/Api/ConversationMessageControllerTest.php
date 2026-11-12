<?php

declare(strict_types=1);

use App\Enums\RemotesFrom;
use App\Jobs\UnfurlLink;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create(['handle' => 'octocat']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('loads the latest messages with the partner, as the web thread does', function (): void {
    Queue::fake();
    Storage::fake();

    $nuno = User::factory()->withRemotes()->create(['handle' => 'nunomaduro']);
    $question = sendChatMessage($nuno, $this->user, 'did @octocat see `the PR`, @ghost? https://laravel.com');
    $this->actingAs($this->user)->post(route('conversation-message.store', $nuno), [
        'body' => '',
        'client_id' => $clientId = Str::uuid()->toString(),
        'reply_to_id' => $question->id,
        'images' => [UploadedFile::fake()->image('screenshot.png', 40, 20)],
        'image_descriptions' => ['The diff'],
    ])->assertCreated();
    $answer = Message::query()->latest('id')->firstOrFail();
    $this->actingAs($nuno)->postJson(route('message-reaction.store', $answer), ['emoji' => '❤️'])->assertOk();

    $response = signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $nuno));

    $response->assertOk()
        ->assertJsonPath('data.partner.handle', 'nunomaduro')
        ->assertJsonPath('data.partner.lane', $nuno->lane())
        ->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.0.id', $question->id)
        ->assertJsonPath('data.messages.0.user_id', $nuno->id)
        ->assertJsonPath('data.messages.0.body', 'did @octocat see `the PR`, @ghost? https://laravel.com')
        ->assertJsonPath('data.messages.0.body_segments', [
            ['type' => 'text', 'text' => 'did @octocat see '],
            ['type' => 'code', 'code' => 'the PR'],
            ['type' => 'text', 'text' => ', @ghost? https://laravel.com'],
        ])
        ->assertJsonPath('data.messages.0.mentions', [['handle' => 'octocat', 'offset' => 4, 'length' => 8, 'kind' => 'user']])
        ->assertJsonPath('data.messages.0.link_preview.url', 'https://laravel.com')
        ->assertJsonPath('data.messages.0.link_preview.is_pending', true)
        ->assertJsonPath('data.messages.0.reply_to', null)
        ->assertJsonPath('data.messages.1.id', $answer->id)
        ->assertJsonPath('data.messages.1.client_id', $clientId)
        ->assertJsonPath('data.messages.1.body', '')
        ->assertJsonPath('data.messages.1.reply_to_id', $question->id)
        ->assertJsonPath('data.messages.1.reply_to', [
            'id' => $question->id,
            'user_id' => $nuno->id,
            'body' => 'did @octocat see `the PR`, @ghost? https://laravel.com',
            'images_count' => 0,
            'has_voice' => false,
        ])
        ->assertJsonPath('data.messages.1.images.0.id', $answer->images->sole()->id)
        ->assertJsonPath('data.messages.1.images.0.description', 'The diff')
        ->assertJsonPath('data.messages.1.reactions', [['user_id' => $nuno->id, 'emoji' => '❤️']])
        ->assertJsonPath('data.messages.1.event', null)
        ->assertJsonPath('data.messages.1.created_at', $answer->created_at->toIso8601ZuluString())
        ->assertJsonMissingPath('data.messages.1.user')
        ->assertJsonPath('data.has_older', false)
        ->assertJsonPath('data.reactions_version', 1)
        ->assertJsonPath('data.reaction_changes', [])
        ->assertJsonPath('data.read_through_id', $answer->id)
        ->assertJsonPath('data.partner_typing', false)
        ->assertJsonPath('data.is_archived', false)
        ->assertJsonPath('data.access', 'open');

    $this->get($response->json('data.messages.1.images.0.url'))->assertOk()->assertHeader('Content-Type', 'image/webp');
});

it('polls for the messages after one and the reactions changed since a version', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $first = sendChatMessage($nuno, $this->user, 'first');
    $second = sendChatMessage($nuno, $this->user, 'second');

    $version = signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $nuno))->json('data.reactions_version');

    $this->actingAs($nuno)->postJson(route('message-reaction.store', $first), ['emoji' => '👍'])->assertOk();
    $third = sendChatMessage($nuno, $this->user, 'third');

    $response = signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', [
        'partner' => $nuno,
        'after' => $second->id,
        'reactions_after' => $version,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.id', $third->id)
        ->assertJsonPath('data.reaction_changes', [['id' => $first->id, 'reactions' => [['user_id' => $nuno->id, 'emoji' => '👍']]]]);
});

it('goes back 50 messages at a time', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user, 'message 1');

    Message::factory()
        ->for(Conversation::between($this->user, $nuno))
        ->for($nuno)
        ->count(50)
        ->sequence(fn (Sequence $sequence): array => ['body' => 'message '.($sequence->index + 2)])
        ->create();

    $latest = signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $nuno))
        ->assertOk()
        ->assertJsonCount(50, 'data.messages')
        ->assertJsonPath('data.messages.0.body', 'message 2')
        ->assertJsonPath('data.has_older', true);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', ['partner' => $nuno, 'before' => $latest->json('data.messages.0.id')]))
        ->assertOk()
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.body', 'message 1')
        ->assertJsonPath('data.has_older', false);
});

it('says when the partner is typing', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user);

    $this->actingAs($nuno)->putJson(route('conversation-typing.update', $this->user))->assertNoContent();

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $nuno))
        ->assertOk()
        ->assertJsonPath('data.partner_typing', true);
});

it('shows how far the partner pulled only while both share pulls', function (bool $sharesPulls): void {
    $nuno = User::factory()->withRemotes()->create(['pull_receipts' => $sharesPulls]);
    $message = sendChatMessage($this->user, $nuno);

    $this->actingAs($nuno)->putJson(route('conversation-read.update', $this->user), ['through' => $message->id])->assertNoContent();

    $response = signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $nuno))->assertOk();

    expect($response->json('data.partner_read_through_id'))->toBe($sharesPulls ? $message->id : 0)
        ->and($response->json('data.partner_read_at') !== null)->toBe($sharesPulls);
})->with([true, false]);

it('has an empty thread with someone the user has not talked to, saying whether a first message can go to them', function (array $partner, bool $requests, string $access): void {
    config(['remotes.requests' => $requests]);

    $nuno = User::factory()->withRemotes(RemotesFrom::Following)->create($partner);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $nuno))
        ->assertOk()
        ->assertJsonPath('data.messages', [])
        ->assertJsonPath('data.has_older', false)
        ->assertJsonPath('data.read_through_id', 0)
        ->assertJsonPath('data.access', $access);
})->with([
    'requests on' => [[], true, 'open'],
    'requests off' => [[], false, 'restricted'],
    'remotes off' => [['remotes_enabled' => false], true, 'closed'],
]);

it('says when a request waits on either of them', function (): void {
    $nuno = User::factory()->withRemotes(RemotesFrom::Following)->create();
    $this->user->update(['remotes_from' => RemotesFrom::Following]);

    sendChatMessage($this->user, $nuno);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $nuno))
        ->assertOk()
        ->assertJsonPath('data.access', 'waiting');

    signedApiRequest(apiKeyPairFor($nuno), 'GET', route('api-conversation-message.index', $this->user))
        ->assertOk()
        ->assertJsonPath('data.access', 'request');
});

it('hides people the user cannot message', function (string $who): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user);

    $partner = match ($who) {
        'themselves' => $this->user,
        default => $nuno,
    };

    match ($who) {
        'blocked' => $this->user->blocking()->attach($nuno),
        'blocker' => $nuno->blocking()->attach($this->user),
        'remotes off' => $this->user->update(['remotes_enabled' => false]),
        default => null,
    };

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $partner))->assertNotFound();
})->with(['themselves', 'blocked', 'blocker', 'remotes off']);

it('returns not found for unknown handles', function (): void {
    signedApiRequest($this->keyPair, 'GET', 'api/v1/remotes/@missing/messages')->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $nuno = User::factory()->withRemotes()->create();

    $this->getJson(route('api-conversation-message.index', $nuno))->assertUnauthorized();
});

it('lets the author reword a message within three minutes', function (): void {
    Queue::fake();
    $nuno = User::factory()->withRemotes()->create(['handle' => 'nunomaduro']);
    $message = sendChatMessage($this->user, $nuno, 'did @octocat see the PR?');

    $this->actingAs($this->user)
        ->putJson(route('conversation-message.update', [$nuno, $message]), ['body' => 'did you see the fix?'])
        ->assertOk()
        ->assertJsonPath('message.body', 'did you see the fix?')
        ->assertJsonPath('message.edited_at', $message->refresh()->edited_at->toJSON());

    expect($message->mentions)->toBe([]);
});

it('hides message edits by other people as missing', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($nuno, $this->user, 'you cannot rewrite me');

    $this->actingAs($this->user)
        ->putJson(route('conversation-message.update', [$nuno, $message]), ['body' => 'rewritten'])
        ->assertNotFound();

    expect($message->refresh()->body)->toBe('you cannot rewrite me');
});

it('refuses edits once the partner blocks the author', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($this->user, $nuno, 'see you at 8');
    $message = sendChatMessage($this->user, $nuno, 'bring snacks');
    $nuno->blocking()->attach($this->user);

    $this->actingAs($this->user)
        ->putJson(route('conversation-message.update', [$nuno, $message]), ['body' => 'rewritten after the block'])
        ->assertNotFound();

    expect($message->refresh()->body)->toBe('bring snacks');
});

it('freezes a message once its edit window closes', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($this->user, $nuno, 'typo here');

    $this->travel(4)->minutes();

    $this->actingAs($this->user)
        ->putJson(route('conversation-message.update', [$nuno, $message]), ['body' => 'fixed'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Messages can only be edited for 3 minutes after pushing.');
});

it('requires a body to edit', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($this->user, $nuno, 'typo here');

    $this->actingAs($this->user)
        ->putJson(route('conversation-message.update', [$nuno, $message]), ['body' => ''])
        ->assertUnprocessable();
});

it('lets a caption be cleared while images remain', function (): void {
    Storage::fake();
    $nuno = User::factory()->withRemotes()->create();
    $this->actingAs($this->user)->post(route('conversation-message.store', $nuno), [
        'body' => 'the diff',
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('screenshot.png', 40, 20)],
    ])->assertCreated();
    $message = Message::query()->latest('id')->firstOrFail();

    $this->actingAs($this->user)
        ->putJson(route('conversation-message.update', [$nuno, $message]), ['body' => ''])
        ->assertOk()
        ->assertJsonPath('message.body', '');
});

it('unfurls a link added by an edit', function (): void {
    Queue::fake();
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($this->user, $nuno, 'look at this');

    $this->actingAs($this->user)
        ->putJson(route('conversation-message.update', [$nuno, $message]), ['body' => 'look at this https://laravel.com'])
        ->assertOk();

    Queue::assertPushed(UnfurlLink::class);
});
