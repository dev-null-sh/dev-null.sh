<?php

declare(strict_types=1);

use App\Enums\BroadcastVisibility;
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

it('honors choosing a link card or omitting it', function (string $choice): void {
    Queue::fake();
    $owner = User::factory()->create();
    $broadcast = createBroadcast($owner);

    $response = $this->actingAs($owner)->postJson(route('broadcast-message.store', $broadcast), [
        'body' => 'https://laravel.com/docs and https://php.net',
        'client_id' => Str::uuid()->toString(),
        'link_preview_url' => $choice,
    ])->assertCreated();

    if ($choice === '') {
        $response->assertJsonPath('message.link_preview', null);
    } else {
        $response->assertJsonPath('message.link_preview.url', $choice);
    }
})->with(['', 'https://php.net']);

it('lets the owner and publishers push, and no one else', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $taylor = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);
    subscribeToBroadcast($taylor, $broadcast);
    test()->actingAs($mark)->postJson(route('broadcast-publisher.store', [$broadcast, 'publisher' => $taylor->username]))->assertNoContent();

    pushToBroadcast($mark, $broadcast, 'from the owner');
    pushToBroadcast($taylor, $broadcast, 'from a publisher');

    $this->actingAs($nuno)->postJson(route('broadcast-message.store', $broadcast), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])
        ->assertForbidden()->assertJsonPath('message', 'Only the owner and publishers can post.');

    $this->actingAs(User::factory()->create())->postJson(route('broadcast-message.store', $broadcast), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])->assertNotFound();
});

it("starts each subscriber's history where they joined", function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    $before = pushToBroadcast($mark, $broadcast, 'from before nuno joined');
    subscribeToBroadcast($nuno, $broadcast);
    $after = pushToBroadcast($mark, $broadcast, 'welcome nuno');

    $thread = $this->actingAs($nuno)->getJson(route('broadcast-message.index', $broadcast))->assertOk();

    $thread->assertJsonPath('role', 'subscriber')
        ->assertJsonPath('can_post', false)
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.id', $after->id)
        ->assertJsonPath('has_older', false)
        ->assertJsonPath('subscriber_count', 2);

    $this->actingAs($mark)->getJson(route('broadcast-message.index', $broadcast))->assertJsonCount(2, 'messages');

    expect(ConversationParticipant::query()
        ->where('conversation_id', $broadcast)
        ->where('user_id', $nuno->id)
        ->value('visible_from_id'))->toBe($before->id);
});
it('replies only to messages in the history the poster can see', function (): void {
    $mark = User::factory()->create();
    $taylor = User::factory()->create();
    $broadcast = createBroadcast($mark);
    $before = pushToBroadcast($mark, $broadcast, 'from before taylor joined');
    subscribeToBroadcast($taylor, $broadcast);
    $after = pushToBroadcast($mark, $broadcast, 'welcome taylor');
    test()->actingAs($mark)->postJson(route('broadcast-publisher.store', [$broadcast, 'publisher' => $taylor->username]))->assertNoContent();

    $this->actingAs($taylor)->postJson(route('broadcast-message.store', $broadcast), ['body' => 'hi', 'client_id' => Str::uuid()->toString(), 'reply_to_id' => $before->id])
        ->assertUnprocessable()->assertJsonValidationErrors('reply_to_id');

    test()->actingAs($taylor)->postJson(route('broadcast-message.store', $broadcast), ['body' => 'thanks!', 'client_id' => Str::uuid()->toString(), 'reply_to_id' => $after->id])
        ->assertCreated()
        ->assertJsonPath('message.reply_to.user_id', $mark->id)
        ->assertJsonPath('message.reply_to.body', 'welcome taylor');
});

it('quotes for a reply only what the reader could already see', function (): void {
    Queue::fake();
    Storage::fake();

    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);

    test()->actingAs($mark)->post(route('broadcast-message.store', $broadcast), [
        'body' => 'early notes',
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('early.png', 20, 20)],
    ], ['Accept' => 'application/json'])->assertCreated();

    subscribeToBroadcast($nuno, $broadcast);

    $reply = test()->actingAs($mark)->postJson(route('broadcast-message.store', $broadcast), [
        'body' => 'about those early notes',
        'client_id' => Str::uuid()->toString(),
        'reply_to_id' => Message::query()->oldest('id')->firstOrFail()->id,
    ])->assertCreated()->json('message.id');

    $thread = $this->actingAs($nuno)->getJson(route('broadcast-message.index', $broadcast))->assertOk();

    $thread->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.id', $reply)
        ->assertJsonPath('messages.0.reply_to', null);

    $poll = $this->actingAs($nuno)->getJson(route('broadcast-message.index', [$broadcast, 'after' => 0]))->assertOk();

    $poll->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.reply_to', null);

    $props = $this->actingAs($nuno)->get("/remotes/broadcasts/{$broadcast}")->assertOk()->inertiaProps();

    expect($props['thread']['messages'][0]['reply_to'])->toBeNull();
});

it('keeps reactions and their polling within the cutoff', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    $before = pushToBroadcast($mark, $broadcast, 'from before nuno joined');
    subscribeToBroadcast($nuno, $broadcast);
    $after = pushToBroadcast($mark, $broadcast, 'welcome nuno');

    $this->actingAs($nuno)->postJson(route('message-reaction.store', $after), ['emoji' => '🎉'])->assertOk();
    $this->actingAs($nuno)->postJson(route('message-reaction.store', $before), ['emoji' => '🎉'])->assertNotFound();

    $thread = $this->actingAs($nuno)->getJson(route('broadcast-message.index', [$broadcast, 'reactions_after' => 0]))->assertOk();

    expect(collect($thread->json('reaction_changes'))->pluck('id')->all())->toBe([$after->id]);
});

it('shows typing only from posters', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);

    $this->actingAs($nuno)->putJson(route('broadcast-typing.update', $broadcast))->assertNotFound();
    $this->actingAs($mark)->putJson(route('broadcast-typing.update', $broadcast))->assertNoContent();

    $this->actingAs($nuno)->getJson(route('broadcast-message.index', $broadcast))->assertJsonPath('typing', [$mark->username]);
    $this->actingAs($mark)->getJson(route('broadcast-message.index', $broadcast))->assertJsonPath('typing', []);
});

it('lets subscribers keep their place, and no one else mark anything', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);
    $message = pushToBroadcast($mark, $broadcast);

    $this->actingAs($nuno)->putJson(route('broadcast-read.update', $broadcast), ['through' => $message->id])->assertNoContent();

    expect(ConversationParticipant::query()
        ->where('conversation_id', $broadcast)
        ->where('user_id', $nuno->id)
        ->value('read_through_id'))->toBe($message->id);

    $this->actingAs(User::factory()->create())->putJson(route('broadcast-read.update', $broadcast), ['through' => $message->id])->assertNotFound();
});

it('serves attachments and link cards only within the history', function (): void {
    Queue::fake();
    Storage::fake();

    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);

    $early = test()->actingAs($mark)->post(route('broadcast-message.store', $broadcast), [
        'body' => 'early notes at https://laravel.com/docs',
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('early.png', 20, 20)],
    ], ['Accept' => 'application/json'])->assertCreated()->json('message');

    subscribeToBroadcast($nuno, $broadcast);

    $late = test()->actingAs($mark)->post(route('broadcast-message.store', $broadcast), [
        'body' => 'late notes at https://laravel.com/docs',
        'client_id' => Str::uuid()->toString(),
        'images' => [UploadedFile::fake()->image('late.png', 20, 20)],
    ], ['Accept' => 'application/json'])->assertCreated()->json('message');

    $this->actingAs($nuno)->get("/remotes/images/{$late['images'][0]['id']}")->assertOk();
    $this->actingAs($nuno)->get("/remotes/images/{$early['images'][0]['id']}")->assertNotFound();

    $this->actingAs($nuno)->getJson(route('message-link-preview.show', $late['id']))->assertOk()->assertJsonPath('link_preview.url', 'https://laravel.com/docs');
    $this->actingAs($nuno)->getJson(route('message-link-preview.show', $early['id']))->assertNotFound();
});

it('stashes a broadcast for one subscriber', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $broadcast = createBroadcast($mark);
    subscribeToBroadcast($nuno, $broadcast);
    pushToBroadcast($mark, $broadcast);

    $this->actingAs($nuno)->postJson(route('broadcast-archive.store', $broadcast))->assertNoContent();
    $this->getJson(route('inbox.index'))->assertJsonCount(0, 'conversations')->assertJsonPath('unread', 0);
    $this->getJson(route('inbox.index', ['tab' => 'stashed']))->assertJsonCount(1, 'conversations');
    $this->deleteJson(route('broadcast-archive.destroy', $broadcast))->assertNoContent();
    $this->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations');
});
