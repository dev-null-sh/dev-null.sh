<?php

declare(strict_types=1);

use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Queries\ConversationsQuery;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('lists conversations by latest message with how many of them are unread', function (array $query): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create(['handle' => 'nunomaduro']);
    $taylor = User::factory()->withRemotes()->create(['handle' => 'taylorotwell']);
    sendChatMessage($mark, $taylor, 'thanks!');
    sendChatMessage($nuno, $mark, 'did you see the PR?');
    $latest = sendChatMessage($nuno, $mark, 'ship it');

    $response = $this->actingAs($mark)->getJson(route('inbox.index', $query))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonMissingPath('notification_baseline')
        ->assertJsonPath('unread', 1);

    expect($response->json('conversations'))->toHaveCount(2)
        ->and($response->json('conversations.0.partner.handle'))->toBe('nunomaduro')
        ->and($response->json('conversations.0.ahead'))->toBe(2)
        ->and($response->json('conversations.0.last_message.body'))->toBe('ship it')
        ->and($response->json('conversations.0.last_incoming_message_id'))->toBe($latest->id)
        ->and($response->json('conversations.0.last_incoming_sender'))->toBe($nuno->name)
        ->and($response->json('conversations.1.partner.handle'))->toBe('taylorotwell')
        ->and($response->json('conversations.1.ahead'))->toBe(0)
        ->and($response->json('conversations.1.last_message.user_id'))->toBe($mark->id)
        ->and($response->json('conversations.1.last_incoming_message_id'))->toBeNull();

    $response->assertJsonPath('conversations.1.last_incoming_sender', null);
})->with([
    'not requested' => [[]],
    'explicitly disabled' => [['notification_baseline' => 0]],
]);

it('clears what is unread once read', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($nuno, $mark, 'ship it');

    $this->actingAs($mark)->putJson(route('conversation-read.update', $nuno), ['through' => $message->id]);

    $this->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('notification_baseline', [$message->conversation_id => $message->id])
        ->assertJsonPath('conversations.0.ahead', 0)
        ->assertJsonPath('conversations.0.last_incoming_message_id', $message->id);
});

it('baselines stashed unread messages without reading them or replaying them after a pop', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($nuno, $mark, 'ship it');

    $this->actingAs($mark)->postJson(route('conversation-archive.store', $nuno))->assertNoContent();

    $this->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonPath('conversations', [])
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('notification_baseline', [$message->conversation_id => $message->id]);
    $this->getJson(route('inbox.index', ['tab' => 'stashed']))
        ->assertOk()
        ->assertJsonPath('conversations.0.ahead', 1)
        ->assertJsonMissingPath('notification_baseline');

    $this->deleteJson(route('conversation-archive.destroy', $nuno))->assertNoContent();

    $this->getJson(route('inbox.index'))
        ->assertOk()
        ->assertJsonPath('conversations.0.ahead', 1)
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('conversations.0.last_incoming_message_id', $message->id)
        ->assertJsonMissingPath('notification_baseline');
    $this->assertDatabaseHas('conversation_participants', [
        'conversation_id' => $message->conversation_id,
        'user_id' => $mark->id,
        'read_through_id' => 0,
        'read_at' => null,
    ]);

    $next = sendChatMessage($nuno, $mark, 'one more thing');

    $this->actingAs($mark)->getJson(route('inbox.index'))
        ->assertOk()
        ->assertJsonPath('conversations.0.ahead', 2)
        ->assertJsonPath('conversations.0.last_incoming_message_id', $next->id)
        ->assertJsonMissingPath('notification_baseline');
});

it('baselines fetched but not dropped or other peoples messages', function (RemoteStatus $status): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $taylor = User::factory()->withRemotes()->create();
    $message = sendChatMessage($nuno, $mark, 'hey');
    ConversationParticipant::query()->where('user_id', $mark->id)->update(['status' => $status]);
    sendChatMessage($nuno, $taylor, 'private');

    $this->actingAs($mark)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonPath('conversations', [])
        ->assertJsonPath('notification_baseline', $status === RemoteStatus::Fetched ? [$message->conversation_id => $message->id] : []);

    config(['remotes.requests' => false]);

    $this->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonPath('notification_baseline', []);
})->with([RemoteStatus::Fetched, RemoteStatus::Dropped]);

it('baselines old stashes and newer conversations independently of search results', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create(['handle' => 'nunomaduro']);
    $taylor = User::factory()->withRemotes()->create(['handle' => 'taylorotwell']);
    $stashed = sendChatMessage($nuno, $mark, 'old stash');
    $this->actingAs($mark)->postJson(route('conversation-archive.store', $nuno))->assertNoContent();
    $latest = sendChatMessage($taylor, $mark, 'new conversation');

    $this->actingAs($mark)->getJson(route('inbox.index', ['q' => 'taylor', 'notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $latest->conversation_id)
        ->assertJsonPath('notification_baseline.'.$stashed->conversation_id, $stashed->id)
        ->assertJsonPath('notification_baseline.'.$latest->conversation_id, $latest->id)
        ->assertJsonCount(2, 'notification_baseline');
});

it('baselines conversations without visible last messages at zero', function (?int $visibilityOffset): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($nuno, $mark, 'hidden history');

    if ($visibilityOffset === null) {
        Conversation::query()->whereKey($message->conversation_id)->update(['last_message_id' => null]);
    } else {
        ConversationParticipant::query()->where('user_id', $mark->id)->update(['visible_from_id' => $message->id + $visibilityOffset]);
    }

    $this->actingAs($mark)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonPath('notification_baseline', [$message->conversation_id => 0]);
})->with(['no last message' => [null], 'at visibility boundary' => [0], 'before visibility boundary' => [1]]);

it("keeps the newest incoming org message past later events and the viewer's own messages", function (): void {
    $mark = User::factory()->withRemotes()->create();
    [$nuno, $taylor, $jess] = followersOf($mark, 3);
    $org = createOrg($mark, [$nuno, $taylor]);

    $this->actingAs($nuno)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertJsonPath('notification_baseline', [$org => Conversation::query()->findOrFail($org)->last_message_id])
        ->assertJsonPath('conversations.0.last_message.event', 'created')
        ->assertJsonPath('conversations.0.last_incoming_message_id', null)
        ->assertJsonPath('conversations.0.last_incoming_sender', null);

    $shipIt = pushToOrg($mark, $org, 'ship it');
    $this->actingAs($mark)->postJson(route('org-member.store', $org), ['members' => [$jess->handle], 'share_history' => true])->assertNoContent();

    $this->actingAs($nuno)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('notification_baseline', [$org => Conversation::query()->findOrFail($org)->last_message_id])
        ->assertJsonPath('conversations.0.last_message.event', 'added')
        ->assertJsonPath('conversations.0.ahead', 1)
        ->assertJsonPath('conversations.0.last_incoming_message_id', $shipIt->id)
        ->assertJsonPath('conversations.0.last_incoming_sender', $mark->name);

    $this->actingAs($mark)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('notification_baseline', [$org => Conversation::query()->findOrFail($org)->last_message_id])
        ->assertJsonPath('conversations.0.ahead', 0)
        ->assertJsonPath('conversations.0.last_incoming_message_id', null)
        ->assertJsonPath('conversations.0.last_incoming_sender', null);

    $this->actingAs($nuno)->putJson(route('org-read.update', $org), ['through' => $shipIt->id])->assertNoContent();

    $this->getJson(route('inbox.index'))
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('conversations.0.ahead', 0)
        ->assertJsonPath('conversations.0.last_incoming_message_id', $shipIt->id)
        ->assertJsonPath('conversations.0.last_incoming_sender', $mark->name);

    $reply = pushToOrg($nuno, $org, 'on it');

    $this->actingAs($nuno)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('notification_baseline', [$org => $reply->id])
        ->assertJsonPath('conversations.0.last_message.id', $reply->id)
        ->assertJsonPath('conversations.0.last_incoming_message_id', $shipIt->id)
        ->assertJsonPath('conversations.0.last_incoming_sender', $mark->name);

    $this->actingAs($mark)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('notification_baseline', [$org => $reply->id])
        ->assertJsonPath('conversations.0.ahead', 1)
        ->assertJsonPath('conversations.0.last_incoming_message_id', $reply->id)
        ->assertJsonPath('conversations.0.last_incoming_sender', $nuno->name);
});

it('only exposes incoming org message ids from the history shared with a new member', function (bool $sharesHistory): void {
    $mark = User::factory()->withRemotes()->create();
    [$nuno, $jess] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno]);
    $beforeJoining = pushToOrg($mark, $org, 'before joining');

    $this->actingAs($mark)->postJson(route('org-member.store', $org), [
        'members' => [$jess->handle],
        'share_history' => $sharesHistory,
    ])->assertNoContent();

    $this->actingAs($jess)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonPath('notification_baseline', [$org => Conversation::query()->findOrFail($org)->last_message_id])
        ->assertJsonPath('conversations.0.id', $org)
        ->assertJsonPath('conversations.0.last_incoming_message_id', $sharesHistory ? $beforeJoining->id : null)
        ->assertJsonPath('conversations.0.last_incoming_sender', $sharesHistory ? $mark->name : null);

    $afterJoining = pushToOrg($mark, $org, 'after joining');

    $this->actingAs($jess)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonPath('notification_baseline', [$org => $afterJoining->id])
        ->assertJsonPath('conversations.0.last_incoming_message_id', $afterJoining->id)
        ->assertJsonPath('conversations.0.last_incoming_sender', $mark->name);
})->with([false, true]);

it('leaves out conversations across a block', function (bool $blockedByPartner): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'hey');
    $blockedByPartner ? $nuno->blocking()->attach($mark) : $mark->blocking()->attach($nuno);

    $this->actingAs($mark)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertJsonPath('conversations', [])
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('notification_baseline', []);
})->with([false, true]);

it('keeps visible org messages in the notification baseline across a block', function (): void {
    $mark = User::factory()->withRemotes()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $message = pushToOrg($nuno, $org, 'org message');
    $mark->blocking()->attach($nuno);

    $this->actingAs($mark)->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonPath('conversations.0.last_incoming_message_id', $message->id)
        ->assertJsonPath('notification_baseline', [$message->conversation_id => $message->id]);
});

it('requires authentication', function (): void {
    $this->getJson(route('inbox.index'))->assertUnauthorized();
});

it('searches conversations by name or handle', function (string $term): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create(['handle' => 'nunomaduro', 'name' => 'Nuno Maduro']);
    $taylor = User::factory()->withRemotes()->create(['handle' => 'taylorotwell', 'name' => 'Taylor Otwell']);
    sendChatMessage($nuno, $mark, 'ship it');
    sendChatMessage($taylor, $mark, 'thanks!');

    $response = $this->actingAs($mark)->getJson(route('inbox.index', ['q' => $term]))
        ->assertOk()
        ->assertJsonMissingPath('notification_baseline');

    expect(collect($response->json('conversations'))->pluck('partner.handle')->all())->toBe(['nunomaduro']);
})->with(['nuno', '@NunoMad', 'maduro']);

it('validates the inbox filters', function (array $query, string $error): void {
    $this->actingAs(User::factory()->withRemotes()->create())->getJson(route('inbox.index', $query))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    [fn (): array => ['tab' => 'maybe'], 'tab'],
    [fn (): array => ['q' => str_repeat('a', 101)], 'q'],
    [fn (): array => ['notification_baseline' => 'yes'], 'notification_baseline'],
]);

it('pages through conversations, newest first', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $partners = User::factory()->withRemotes()->count(ConversationsQuery::PAGE + 2)->create();
    $partners->each(function (User $partner, int $index) use ($mark): void {
        $this->travel(1)->minutes();
        sendChatMessage($partner, $mark, "message {$index}");
    });

    $first = $this->actingAs($mark)->getJson(route('inbox.index'))
        ->assertJsonPath('has_more', true)
        ->assertJsonMissingPath('notification_baseline');

    expect($first->json('conversations'))->toHaveCount(ConversationsQuery::PAGE)
        ->and($first->json('conversations.0.last_message.body'))->toBe('message '.(ConversationsQuery::PAGE + 1));

    $second = $this->getJson(route('inbox.index', ['before' => $first->json('conversations.'.(ConversationsQuery::PAGE - 1).'.id')]))
        ->assertJsonPath('has_more', false)
        ->assertJsonMissingPath('notification_baseline');

    expect(collect($second->json('conversations'))->pluck('last_message.body')->all())->toBe(['message 1', 'message 0']);

    $this->getJson(route('inbox.index', ['notification_baseline' => 1]))
        ->assertOk()
        ->assertJsonCount(ConversationsQuery::PAGE + 2, 'notification_baseline')
        ->assertJsonPath('notification_baseline.'.$second->json('conversations.1.id'), $second->json('conversations.1.last_message.id'));
});
