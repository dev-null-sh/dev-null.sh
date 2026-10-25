<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('shows the inbox', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create(['username' => 'nunomaduro']);
    sendChatMessage($nuno, $mark, 'ship it');

    $this->actingAs($mark)->get(route('conversation.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('conversation/index')
            ->where('partner', null)
            ->where('thread', null)
            ->where('inbox.conversations.0.partner.username', 'nunomaduro')
            ->where('auth.unreadMessagesCount', 1));
});

it('opens a conversation, remembering where you were and marking it read', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create(['username' => 'nunomaduro']);
    $read = sendChatMessage($nuno, $mark, 'did you see the PR?');
    $this->actingAs($mark)->putJson(route('conversation-read.update', $nuno), ['through' => $read->id]);
    sendChatMessage($nuno, $mark, 'ship it');
    $latest = sendChatMessage($nuno, $mark, 'what do the dots mean?');

    $this->actingAs($mark)->get(route('conversation.show', $nuno))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('conversation/index')
            ->where('partner.username', 'nunomaduro')
            ->where('thread.read_through_id', $read->id)
            ->has('thread.messages', 3)
            ->where('inbox.conversations.0.ahead', 0));

    $this->get(route('conversation.index'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('auth.unreadMessagesCount', 0)
        ->where('inbox.conversations.0.ahead', 0));

    $this->actingAs($nuno)->getJson(route('conversation-message.index', $mark))->assertJsonPath('partner_read_through_id', $latest->id);
});

it('opens an empty conversation with someone new', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create(['username' => 'nunomaduro']);

    $this->actingAs($mark)->get(route('conversation.show', $nuno))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('partner.username', 'nunomaduro')
            ->where('thread.messages', []));
});

it('hides conversations you cannot have', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $nuno->blocking()->attach($mark);

    $this->actingAs($mark)->get(route('conversation.show', $nuno))->assertNotFound();
    $this->get(route('conversation.show', $mark))->assertNotFound();
});

it('requires authentication', function (): void {
    $this->get(route('conversation.index'))->assertRedirect();
});

it('polls the badge counts on an open conversation without marking it read', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create(['username' => 'nunomaduro']);
    sendChatMessage($nuno, $mark, 'ship it');

    $this->actingAs($mark)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'conversation/index',
        'X-Inertia-Partial-Data' => 'auth.unreadNotificationsCount,auth.unreadMessagesCount',
    ])->get(route('conversation.show', $nuno))->assertOk()
        ->assertJsonPath('props.auth.unreadMessagesCount', 1)
        ->assertJsonMissingPath('props.thread');

    $this->actingAs($nuno)->getJson(route('conversation-message.index', $mark))->assertJsonPath('partner_read_through_id', 0);
});
