<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('saves a personal pin across inbox and thread requests and removes it', function (): void {
    $this->freezeSecond();
    $user = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $user);
    $at = now()->toJSON();

    $this->actingAs($user)->putJson(route('conversation-pin.update', $partner), ['pinned' => true])
        ->assertOk()->assertExactJson(['pinned_at' => $at]);

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.pinned_at', $at);
    $this->getJson(route('conversation-message.index', $partner))->assertJsonPath('pinned_at', $at);
    $this->actingAs($partner)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.pinned_at', null);

    $this->actingAs($user)->putJson(route('conversation-pin.update', $partner), ['pinned' => false])
        ->assertOk()->assertExactJson(['pinned_at' => null]);
    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.pinned_at', null);
    $this->assertDatabaseHas('conversation_participants', ['user_id' => $user->id, 'pinned_at' => null]);
});

it('pins an org for each member independently and keeps a pin through stashing and messages', function (): void {
    $user = User::factory()->withRemotes()->create();
    [$member] = followersOf($user, 1);
    $org = createOrg($user, [$member]);

    $at = $this->actingAs($member)->putJson(route('org-pin.update', $org), ['pinned' => true])
        ->assertOk()->json('pinned_at');
    $this->getJson(route('org-message.index', $org))->assertJsonPath('pinned_at', $at);
    $this->postJson(route('org-archive.store', $org))->assertNoContent();
    $this->getJson(route('inbox.index', ['tab' => 'stashed']))->assertJsonPath('conversations.0.pinned_at', $at);
    pushToOrg($user, $org);
    $this->actingAs($member)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.pinned_at', $at);
    $this->actingAs($user)->getJson(route('org-message.index', $org))->assertJsonPath('pinned_at', null);
    $this->actingAs($member)->putJson(route('org-pin.update', $org), ['pinned' => false])->assertExactJson(['pinned_at' => null]);
});

it('paginates pins before newer unpinned remotes without skipping or repeating rows', function (): void {
    $user = User::factory()->withRemotes()->create();
    $partners = User::factory()->withRemotes()->count(35)->create();
    foreach ($partners as $index => $partner) {
        $this->travelTo(now()->parse('2026-10-01')->addMinutes($index));
        sendChatMessage($partner, $user);
    }

    $this->actingAs($user)->putJson(route('conversation-pin.update', $partners[0]), ['pinned' => true])->assertOk();
    $this->putJson(route('conversation-pin.update', $partners[1]), ['pinned' => true])->assertOk();

    $first = $this->getJson(route('inbox.index'))->assertOk()->assertJsonPath('has_more', true)->json('conversations');
    expect(array_column(array_slice($first, 0, 3), 'partner'))->sequence(
        fn ($partner) => $partner->handle->toBe($partners[1]->handle),
        fn ($partner) => $partner->handle->toBe($partners[0]->handle),
        fn ($partner) => $partner->handle->toBe($partners[34]->handle),
    );
    $next = $this->getJson(route('inbox.index', ['before' => $first[29]['id']]))
        ->assertOk()->assertJsonPath('has_more', false)->json('conversations');
    expect(array_unique(array_column([...$first, ...$next], 'id')))->toHaveCount(35);

    $afterPin = $this->getJson(route('inbox.index', ['before' => $first[0]['id']]))->assertOk()->json('conversations');
    expect($afterPin[0]['partner']['id'])->toBe($partners[0]->id);
    $this->putJson(route('conversation-pin.update', $partners[0]), ['pinned' => false])->assertOk();
    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.partner.id', $partners[1]->id)
        ->assertJsonPath('conversations.1.partner.id', $partners[34]->id);
});

it('keeps the original timestamp when pinning an already pinned remote', function (): void {
    $user = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($user, $partner);
    $at = $this->actingAs($user)->putJson(route('conversation-pin.update', $partner), ['pinned' => true])->json('pinned_at');
    $this->travel(1)->minutes();

    $this->putJson(route('conversation-pin.update', $partner), ['pinned' => true])->assertExactJson(['pinned_at' => $at]);
});

it('validates pin choices for direct remotes and orgs', function (array $data): void {
    $user = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $user);
    $org = createOrg($user, followersOf($user, 1));

    $this->actingAs($user)->putJson(route('conversation-pin.update', $partner), $data)->assertJsonValidationErrors('pinned');
    $this->putJson(route('org-pin.update', $org), $data)->assertJsonValidationErrors('pinned');
    expect(ConversationParticipant::query()->whereNotNull('pinned_at')->count())->toBe(0);
})->with([[[]], [['pinned' => null]], [['pinned' => 'yes']], [['pinned' => []]]]);

it('does not create a conversation to pin and hides blocked conversations', function (): void {
    $user = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();

    $this->actingAs($user)->putJson(route('conversation-pin.update', $partner), ['pinned' => true])->assertNotFound();
    expect(Conversation::query()->count())->toBe(0);
    sendChatMessage($partner, $user);
    $partner->blocking()->attach($user);
    $this->putJson(route('conversation-pin.update', $partner), ['pinned' => true])->assertNotFound();
});

it('hides org pins from outsiders and disabled accounts', function (): void {
    $user = User::factory()->withRemotes()->create();
    $org = createOrg($user, followersOf($user, 1));
    $this->actingAs(User::factory()->withRemotes()->create())->putJson(route('org-pin.update', $org), ['pinned' => true])->assertNotFound();
    $user->update(['remotes_enabled' => false]);
    $this->actingAs($user)->putJson(route('org-pin.update', $org), ['pinned' => true])->assertNotFound();
});

it('requires authentication for pinning remotes and orgs', function (): void {
    $this->putJson(route('conversation-pin.update', User::factory()->create()), ['pinned' => true])->assertUnauthorized();
    $this->putJson(route('org-pin.update', Conversation::factory()->create(['kind' => 'org'])), ['pinned' => true])->assertUnauthorized();
});

it('keeps recent notification candidates visible when older pins fill the inbox page', function (): void {
    $this->freezeSecond();
    $user = User::factory()->withRemotes()->create();
    foreach (User::factory()->withRemotes()->count(30)->create() as $partner) {
        sendChatMessage($partner, $user);
    }

    ConversationParticipant::query()->where('user_id', $user->id)->update(['pinned_at' => now()]);
    $this->travel(1)->minutes();
    $recent = User::factory()->withRemotes()->create();
    sendChatMessage($recent, $user, 'new activity beyond the pinned page');

    $response = $this->actingAs($user)->getJson(route('inbox.index', ['notification_conversations' => 1]))->assertOk();

    expect(collect($response->json('conversations'))->pluck('partner.id')->all())->not->toContain($recent->id);
    $response->assertJsonPath('notification_conversations.0.partner.id', $recent->id)
        ->assertJsonPath('notification_conversations.0.last_message.body', 'new activity beyond the pinned page');
});

it('returns the owners complete pin snapshot independently of the visible page', function (): void {
    $this->freezeSecond();
    $user = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $user);
    $conversation = Conversation::betweenOrFail($user, $partner);
    $this->actingAs($user)->putJson(route('conversation-pin.update', $partner), ['pinned' => true])->assertOk();
    $this->postJson(route('conversation-archive.store', $partner))->assertNoContent();

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations', [])
        ->assertJsonPath('pins', [$conversation->id => now()->toJSON()]);
    $this->actingAs($partner)->getJson(route('inbox.index'))->assertJsonPath('pins', []);
    $this->actingAs($user)->putJson(route('conversation-pin.update', $partner), ['pinned' => false])->assertOk();
    $this->getJson(route('inbox.index'))->assertJsonPath('pins', []);
});

it('uses one pin snapshot for the page, its cursor and the response despite concurrent changes', function (string $stage): void {
    $this->freezeSecond();
    $user = User::factory()->withRemotes()->create();
    $ids = [];
    foreach (User::factory()->withRemotes()->count(65)->create() as $index => $partner) {
        $conversation = Conversation::factory()->create(['pair' => Conversation::pairOf($user, $partner)]);
        $time = now()->parse($index < 30 ? '2026-10-01' : '2026-10-23')->addMinutes(65 - $index);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $partner->id, 'created_at' => $time]);
        $conversation->update(['last_message_id' => $message->id]);
        $ids[] = $conversation->id;
        ConversationParticipant::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'pinned_at' => $index < 30 ? now() : null,
            'last_message_at' => $time,
        ]);
        ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $partner->id, 'last_message_at' => $time]);
    }

    ConversationParticipant::query()->where('user_id', $user->id)->where('conversation_id', $ids[0])->update(['pinned_at' => null]);
    $injected = false;
    $event = 'eloquent.retrieved: '.($stage === 'page' ? Conversation::class : ConversationParticipant::class);
    Event::listen($event, function () use (&$injected, $stage, $user, $ids): void {
        if ($injected) {
            return;
        }

        $injected = true;
        ConversationParticipant::query()->where('user_id', $user->id)
            ->whereIn('conversation_id', $stage === 'cursor' ? [$ids[28], $ids[29]] : [$ids[29]])
            ->update(['pinned_at' => null]);
    });

    try {
        $response = $this->actingAs($user)->getJson(route('inbox.index', $stage === 'cursor' ? ['before' => $ids[28]] : []));
    } finally {
        Event::forget($event);
    }

    $response->assertOk()->assertJsonPath('has_more', true)
        ->assertJsonPath('pins.'.$ids[29], now()->toJSON());
    $expected = $stage === 'cursor' ? [$ids[29], ...array_slice($ids, 30, 29)] : [...array_slice($ids, 1, 29), $ids[30]];
    expect($injected)->toBeTrue()
        ->and(array_column($response->json('conversations'), 'id'))->toBe($expected);
    foreach ($response->json('conversations') as $conversation) {
        expect($conversation['pinned_at'])->toBe($response->json('pins.'.$conversation['id']));
    }

    $this->assertDatabaseHas('conversation_participants', ['user_id' => $user->id, 'conversation_id' => $ids[29], 'pinned_at' => null]);
    $this->getJson(route('inbox.index'))->assertOk()->assertJsonMissingPath('pins.'.$ids[29]);
})->with(['page', 'snapshot', 'cursor']);
