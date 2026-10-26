<?php

declare(strict_types=1);

use App\Actions\CreateUserBan;
use App\Enums\ModerationReason;
use App\Enums\RemotesFrom;
use App\Models\Conversation;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create(['handle' => 'octocat']);
    $this->keyPair = apiKeyPairFor($this->user);
    $this->nuno = User::factory()->withRemotes()->create(['handle' => 'nunomaduro']);
});

it('pushes a message, starting the conversation, and answers with it as the thread shows it', function (): void {
    $clientId = Str::uuid()->toString();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), [
        'body' => '  merged it this morning :rocket:  ',
        'client_id' => $clientId,
    ]);

    $message = Message::query()->sole();

    $response->assertCreated()
        ->assertJsonPath('data.id', $message->id)
        ->assertJsonPath('data.user_id', $this->user->id)
        ->assertJsonPath('data.client_id', $clientId)
        ->assertJsonPath('data.body', 'merged it this morning 🚀')
        ->assertJsonPath('data.body_segments', [['type' => 'text', 'text' => 'merged it this morning 🚀']])
        ->assertJsonPath('data.images', [])
        ->assertJsonPath('data.reactions', [])
        ->assertJsonPath('data.link_preview', null)
        ->assertJsonPath('data.reply_to', null);

    expect(Conversation::between($this->user, $this->nuno)?->last_message_id)->toBe($message->id);

    $this->actingAs($this->nuno)->getJson(route('conversation-message.index', $this->user))
        ->assertJsonPath('messages.0.body', 'merged it this morning 🚀');
});

it('stores a retried message once, answering with the one it already created', function (): void {
    $payload = ['body' => 'on it', 'client_id' => Str::uuid()->toString()];

    $first = signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), $payload)->assertCreated();

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Message::query()->count())->toBe(1);
});

it('refuses messages from timed out and banned users without creating a message', function (string $target, string $restriction): void {
    if ($target === 'org') {
        $this->user->following()->attach($this->nuno);
        $uri = route('api-org-message.store', createOrg($this->nuno, [$this->user]));
    } else {
        $uri = route('api-conversation-message.store', $this->nuno);
    }

    if ($restriction === 'timeout') {
        UserTimeout::factory()->for($this->user)->create();
    } else {
        resolve(CreateUserBan::class)->handle($this->user, ModerationReason::Spam);
    }

    $messages = Message::query()->count();

    signedApiRequest($this->keyPair, 'POST', $uri, ['body' => 'on it', 'client_id' => Str::uuid()->toString()])
        ->assertForbidden()
        ->assertJsonPath('code', $restriction === 'timeout' ? 'timed_out' : 'banned');

    expect(Message::query()->count())->toBe($messages);
})->with(['conversation', 'org'])->with(['timeout', 'banned']);

it('replies to a message and picks the link card', function (): void {
    $question = sendChatMessage($this->nuno, $this->user, 'docs?');

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), [
        'body' => 'https://laravel.com or https://pestphp.com',
        'client_id' => Str::uuid()->toString(),
        'reply_to_id' => $question->id,
        'link_preview_url' => 'https://pestphp.com',
    ])->assertCreated()
        ->assertJsonPath('data.reply_to_id', $question->id)
        ->assertJsonPath('data.reply_to', ['id' => $question->id, 'user_id' => $this->nuno->id, 'body' => 'docs?', 'images_count' => 0, 'has_voice' => false])
        ->assertJsonPath('data.link_preview.url', 'https://pestphp.com')
        ->assertJsonPath('data.link_preview.is_pending', true);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), [
        'body' => 'no card for https://laravel.com',
        'client_id' => Str::uuid()->toString(),
        'link_preview_url' => '',
    ])->assertCreated()
        ->assertJsonPath('data.link_preview', null);

    expect(LinkPreview::query()->pluck('url')->all())->toBe(['https://pestphp.com']);
});

it('rejects invalid messages', function (array $payload, string $error): void {
    signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect(Message::query()->where('user_id', $this->user->id)->count())->toBe(0);
})->with([
    'missing body' => [fn (): array => ['client_id' => Str::uuid()->toString()], 'body'],
    'blank body' => [fn (): array => ['body' => '   ', 'client_id' => Str::uuid()->toString()], 'body'],
    'too long' => [fn (): array => ['body' => str_repeat('a', Message::MAX_LENGTH + 1), 'client_id' => Str::uuid()->toString()], 'body'],
    'missing client id' => [fn (): array => ['body' => 'hi'], 'client_id'],
    'invalid client id' => [fn (): array => ['body' => 'hi', 'client_id' => 'nope'], 'client_id'],
    'reply to a message elsewhere' => [fn (): array => ['body' => 'hi', 'client_id' => Str::uuid()->toString(), 'reply_to_id' => sendChatMessage(User::factory()->withRemotes()->create(), User::factory()->withRemotes()->create())->id], 'reply_to_id'],
    'a card for another link' => [fn (): array => ['body' => 'see https://laravel.com', 'client_id' => Str::uuid()->toString(), 'link_preview_url' => 'https://evil.test'], 'link_preview_url'],
]);

it("says why a message can not go yet, in the web's words", function (string $state, string $message): void {
    $partner = match ($state) {
        'request' => tap($this->nuno, function (User $nuno): void {
            $this->user->update(['remotes_from' => RemotesFrom::Following]);
            sendChatMessage($nuno, $this->user);
        }),
        'waiting' => tap($this->nuno, function (User $nuno): void {
            $nuno->update(['remotes_from' => RemotesFrom::Following]);
            sendChatMessage($this->user, $nuno);
        }),
        'restricted' => tap($this->nuno, function (User $nuno): void {
            config(['remotes.requests' => false]);
            $nuno->update(['remotes_from' => RemotesFrom::Following]);
        }),
        default => tap($this->nuno, fn (User $nuno): bool => $nuno->update(['remotes_enabled' => false])),
    };

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $partner), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])
        ->assertForbidden()
        ->assertJsonPath('message', $message);
})->with([
    'request' => ['request', "Merge @nunomaduro's request before replying."],
    'waiting' => ['waiting', "@nunomaduro hasn't merged your request yet."],
    'restricted' => ['restricted', "@nunomaduro doesn't follow you, so you can't push to them yet."],
    'closed' => ['closed', "@nunomaduro isn't taking remotes."],
]);

it('hides people the user cannot message', function (string $who): void {
    $partner = $who === 'themselves' ? $this->user : $this->nuno;

    match ($who) {
        'blocked' => $this->nuno->blocking()->attach($this->user),
        'remotes off' => $this->user->update(['remotes_enabled' => false]),
        default => null,
    };

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $partner), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])
        ->assertNotFound();

    expect(Message::query()->count())->toBe(0);
})->with(['themselves', 'blocked', 'remotes off']);

it('pushes a message to an org the user is in', function (): void {
    $this->user->following()->attach($this->nuno);
    $org = createOrg($this->nuno, [$this->user]);

    $question = pushToOrg($this->nuno, $org, 'docs?');

    signedApiRequest($this->keyPair, 'POST', route('api-org-message.store', $org), ['body' => 'on it', 'client_id' => Str::uuid()->toString()])
        ->assertCreated()
        ->assertJsonPath('data.body', 'on it');

    signedApiRequest($this->keyPair, 'POST', route('api-org-message.store', $org), [
        'body' => 'hello org, see https://laravel.com',
        'client_id' => Str::uuid()->toString(),
        'reply_to_id' => $question->id,
        'link_preview_url' => '',
    ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'hello org, see https://laravel.com')
        ->assertJsonPath('data.user_id', $this->user->id)
        ->assertJsonPath('data.reply_to.id', $question->id)
        ->assertJsonPath('data.link_preview', null);

    $this->actingAs($this->nuno)->getJson(route('org-message.index', $org))->assertJsonPath('messages.3.body', 'hello org, see https://laravel.com');
});

it('keeps people out of orgs they are not in', function (): void {
    $taylor = User::factory()->withRemotes()->create();
    $taylor->following()->attach($this->nuno);
    $org = createOrg($this->nuno, [$taylor]);

    signedApiRequest($this->keyPair, 'POST', route('api-org-message.store', $org), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])
        ->assertNotFound();
});

it('takes 30 messages a minute, as the web does', function (): void {
    foreach (range(1, 30) as $number) {
        signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), ['body' => "message {$number}", 'client_id' => Str::uuid()->toString()])
            ->assertCreated();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), ['body' => 'one more', 'client_id' => Str::uuid()->toString()])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');
});

it('rejects requests that are not signed', function (): void {
    $this->postJson(route('api-conversation-message.store', $this->nuno), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])
        ->assertUnauthorized();
});

it('pushes a voice note, signed beside its payload, and answers with a url signed for the user', function (): void {
    Storage::fake();
    $question = sendChatMessage($this->nuno, $this->user);

    $response = signedApiRequestWithVoice($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), [
        'client_id' => Str::uuid()->toString(),
        'reply_to_id' => $question->id,
        'voice_waveform' => voiceNoteWaveform(),
    ], voiceNoteUpload(voiceNoteWav(1500)))->assertCreated();

    $voice = Message::query()->latest('id')->firstOrFail()->voice;

    $response->assertJsonPath('data.body', '')
        ->assertJsonPath('data.reply_to.id', $question->id)
        ->assertJsonPath('data.reply_to.has_voice', false)
        ->assertJsonPath('data.voice.id', $voice?->id)
        ->assertJsonPath('data.voice.duration_ms', 1500)
        ->assertJsonPath('data.voice.waveform', voiceNoteWaveform())
        ->assertJsonMissingPath('data.voice.path');

    expect($response->json('data.voice.url'))->toStartWith(route('api-message-voice.show', $voice).'?expires=');
});

it('pushes a voice note to an org', function (): void {
    Storage::fake();
    $this->user->following()->attach($this->nuno);
    $org = createOrg($this->nuno, [$this->user]);

    signedApiRequestWithVoice($this->keyPair, 'POST', route('api-org-message.store', $org), [
        'client_id' => Str::uuid()->toString(),
        'voice_waveform' => voiceNoteWaveform(),
    ], voiceNoteUpload())
        ->assertCreated()
        ->assertJsonPath('data.voice.duration_ms', 2000);

    $this->actingAs($this->nuno)->getJson(route('org-message.index', $org))->assertJsonPath('messages.1.voice.duration_ms', 2000);
});

it('rejects a voice note the web would reject', function (array $payload, string $recording, string $error): void {
    Storage::fake();

    signedApiRequestWithVoice($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), [
        'client_id' => Str::uuid()->toString(),
        ...$payload,
    ], voiceNoteUpload($recording))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect(Message::query()->exists())->toBeFalse();
})->with([
    'with a body' => [['body' => 'hi', 'voice_waveform' => [50]], '', 'voice'],
    'without a waveform' => [[], '', 'voice_waveform'],
    'not a recording' => [['voice_waveform' => [50]], 'not audio', 'voice'],
]);
