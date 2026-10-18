<?php

declare(strict_types=1);

use App\Jobs\UnfurlLink;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('starts one conversation between two people and keeps their messages in it', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $clientId = Str::uuid()->toString();

    $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), [
        'body' => '  merged it this morning  ',
        'client_id' => $clientId,
    ])->assertCreated()
        ->assertJsonPath('message.body', 'merged it this morning')
        ->assertJsonPath('message.client_id', $clientId)
        ->assertJsonPath('message.user_id', $mark->id);

    sendChatMessage($nuno, $mark, 'ship it');

    expect(Conversation::query()->count())->toBe(1)
        ->and(Conversation::query()->sole()->messages()->pluck('body')->all())->toBe(['merged it this morning', 'ship it']);
});

it('stores a retried message only once', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $clientId = Str::uuid()->toString();

    $first = $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), ['body' => 'on it', 'client_id' => $clientId])->assertCreated();
    $retry = $this->postJson(route('conversation-message.store', $nuno), ['body' => 'on it', 'client_id' => $clientId])->assertCreated();

    expect($retry->json('message.id'))->toBe($first->json('message.id'))
        ->and(Message::query()->count())->toBe(1);
});

it('stores emoji shortcodes as their emoji', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();

    $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), [
        'body' => 'shipped it :heart: :like: :rocket and `:rocket:`',
        'client_id' => Str::uuid()->toString(),
    ])->assertCreated()
        ->assertJsonPath('message.body', 'shipped it ❤️ 👍 :rocket and `:rocket:`');
});

it('rejects invalid messages', function (array $payload, string $error): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();

    $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($error);

    expect(Message::query()->count())->toBe(0);
})->with([
    'missing body' => [fn (): array => ['client_id' => Str::uuid()->toString()], 'body'],
    'blank body' => [fn (): array => ['body' => '   ', 'client_id' => Str::uuid()->toString()], 'body'],
    'too long' => [fn (): array => ['body' => str_repeat('a', Message::MAX_LENGTH + 1), 'client_id' => Str::uuid()->toString()], 'body'],
    'missing client id' => [fn (): array => ['body' => 'hi'], 'client_id'],
    'invalid client id' => [fn (): array => ['body' => 'hi', 'client_id' => 'nope'], 'client_id'],
]);

it('hides people you cannot message', function (string $who): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();

    match ($who) {
        'blocked' => $mark->blocking()->attach($nuno),
        'blocker' => $nuno->blocking()->attach($mark),
        default => null,
    };

    $recipient = $who === 'yourself' ? $mark : $nuno;

    $this->actingAs($mark)->postJson(route('conversation-message.store', $recipient), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])
        ->assertNotFound();
    $this->getJson(route('conversation-message.index', $recipient))->assertNotFound();

    expect(Message::query()->count())->toBe(0);
})->with(['yourself', 'blocked', 'blocker']);

it('returns the latest messages, then only newer ones, then older pages', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $messages = collect(range(1, 55))->map(fn (int $index): Message => sendChatMessage($index % 2 === 0 ? $mark : $nuno, $index % 2 === 0 ? $nuno : $mark, "message {$index}"));

    $latest = $this->actingAs($mark)->getJson(route('conversation-message.index', $nuno))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('partner.username', $nuno->username)
        ->assertJsonPath('has_older', true);

    expect($latest->json('messages'))->toHaveCount(50)
        ->and($latest->json('messages.0.body'))->toBe('message 6')
        ->and($latest->json('messages.49.body'))->toBe('message 55');

    $older = $this->getJson(route('conversation-message.index', [$nuno, 'before' => $latest->json('messages.0.id')]))->assertOk();

    expect(collect($older->json('messages'))->pluck('body')->all())->toBe(['message 1', 'message 2', 'message 3', 'message 4', 'message 5'])
        ->and($older->json('has_older'))->toBeFalse();

    $newest = sendChatMessage($nuno, $mark, 'one more');

    $newer = $this->actingAs($mark)->getJson(route('conversation-message.index', [$nuno, 'after' => $messages->last()->id]))->assertOk();

    expect(collect($newer->json('messages'))->pluck('id')->all())->toBe([$newest->id]);
});

it('returns an empty thread before anyone has written', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();

    $this->actingAs($mark)->getJson(route('conversation-message.index', $nuno))
        ->assertOk()
        ->assertJsonPath('messages', [])
        ->assertJsonPath('partner_read_through_id', 0)
        ->assertJsonPath('has_older', false);

    expect(Conversation::query()->count())->toBe(0);
});

it('requires authentication', function (): void {
    $nuno = User::factory()->withRemotes()->create();

    $this->postJson(route('conversation-message.store', $nuno), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])->assertUnauthorized();
    $this->getJson(route('conversation-message.index', $nuno))->assertUnauthorized();
});

it('stores message bodies encrypted at rest', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $message = sendChatMessage($mark, $nuno, 'the deploy key is in 1password');

    $stored = DB::table('messages')->where('id', $message->id)->value('body');

    expect($stored)->not->toContain('1password')
        ->and(Crypt::decryptString($stored))->toBe('the deploy key is in 1password');

    $this->actingAs($nuno)->getJson(route('conversation-message.index', $mark))
        ->assertJsonPath('messages.0.body', 'the deploy key is in 1password');
});

it('replies to a message in the same conversation', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $question = sendChatMessage($nuno, $mark, 'shipping remotes on friday?');

    $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), [
        'body' => 'only if replies land first',
        'client_id' => Str::uuid()->toString(),
        'reply_to_id' => $question->id,
    ])->assertCreated()
        ->assertJsonPath('message.reply_to.id', $question->id)
        ->assertJsonPath('message.reply_to.user_id', $nuno->id)
        ->assertJsonPath('message.reply_to.body', 'shipping remotes on friday?');

    $this->actingAs($nuno)->getJson(route('conversation-message.index', $mark))
        ->assertJsonPath('messages.1.reply_to.body', 'shipping remotes on friday?')
        ->assertJsonPath('messages.1.reply_to.images_count', 0);
});

it('refuses replies to messages from another conversation', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $taylor = User::factory()->withRemotes()->create();
    $elsewhere = sendChatMessage($taylor, $mark, 'a secret between us');

    $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), [
        'body' => 'replying across',
        'client_id' => Str::uuid()->toString(),
        'reply_to_id' => $elsewhere->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('reply_to_id');
});

it('links the people a message mentions and highlights its code', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $taylor = User::factory()->create(['username' => 'taylorotwell']);

    $response = $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), [
        'body' => "ask @TaylorOtwell about `php artisan serve`\n```php\necho 'hi';\n```",
        'client_id' => Str::uuid()->toString(),
    ])->assertCreated()
        ->assertJsonPath('message.mentions', ['taylorotwell' => $taylor->id]);

    expect(collect($response->json('message.body_segments'))->pluck('type')->all())->toBe(['text', 'code', 'block'])
        ->and(DB::table('messages')->value('mentions'))->not->toContain('taylorotwell');
});

it('unfurls the link the sender chose into a link card', function (array $payload, ?string $url): void {
    Queue::fake();

    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();

    $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), [
        'body' => 'release notes at https://laravel.com/docs and https://github.com/laravel',
        'client_id' => Str::uuid()->toString(),
        ...$payload,
    ])->assertCreated()
        ->assertJsonPath('message.link_preview.url', $url);

    $url === null ? Queue::assertNothingPushed() : Queue::assertPushed(UnfurlLink::class);
})->with([
    'the first link by default' => [[], 'https://laravel.com/docs'],
    'another link' => [['link_preview_url' => 'https://github.com/laravel'], 'https://github.com/laravel'],
    'no card' => [['link_preview_url' => ''], null],
]);

it('refuses a link card for a link that is not in the message', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();

    $this->actingAs($mark)->postJson(route('conversation-message.store', $nuno), [
        'body' => 'see https://laravel.com',
        'client_id' => Str::uuid()->toString(),
        'link_preview_url' => 'https://evil.example',
    ])->assertUnprocessable()->assertJsonValidationErrors('link_preview_url');
});

it('sends images, with or without a body', function (string $body): void {
    Storage::fake();

    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();

    $response = $this->actingAs($mark)->post(route('conversation-message.store', $nuno), [
        'body' => $body,
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('deploy.png', 40, 30), UploadedFile::fake()->image('logs.jpg', 20, 20)],
        'image_descriptions' => ['The deploy log.', null],
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('message.body', $body)
        ->assertJsonPath('message.images.0.description', 'The deploy log.')
        ->assertJsonCount(2, 'message.images');

    expect(Storage::disk()->allFiles('message-images'))->toHaveCount(2)
        ->and($response->json('message.images.0.url'))->toBe(route('message-image.show', Message::query()->sole()->images->first()));
})->with(['look at this', '']);

it('stores the images of a retried message only once', function (): void {
    Storage::fake();

    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $payload = ['body' => '', 'client_id' => Str::uuid()->toString()];

    $this->actingAs($mark)->post(route('conversation-message.store', $nuno), [...$payload, 'images' => [UploadedFile::fake()->image('a.png', 10, 10)]], ['Accept' => 'application/json'])->assertCreated();
    $this->post(route('conversation-message.store', $nuno), [...$payload, 'images' => [UploadedFile::fake()->image('a.png', 10, 10)]], ['Accept' => 'application/json'])->assertCreated();

    expect(Storage::disk()->allFiles('message-images'))->toHaveCount(1);
});
