<?php

declare(strict_types=1);

use App\Enums\RemotesFrom;
use App\Models\Ban;
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

it('leaves conversations and requests from banned people out of the inbox until the ban is lifted', function (): void {
    $mark = User::factory()->withRemotes(RemotesFrom::Following)->create();
    $nuno = User::factory()->withRemotes()->create();
    $taylor = User::factory()->withRemotes()->create();
    $mark->following()->attach($nuno);
    sendChatMessage($nuno, $mark, 'ship it');
    sendChatMessage($taylor, $mark, 'hey, saw your polling PR');

    Ban::factory()->create(['github_id' => $nuno->github_id]);
    Ban::factory()->create(['github_id' => $taylor->github_id]);

    $this->actingAs($mark)->getJson(route('inbox.index'))
        ->assertJsonPath('conversations', [])
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('requests', 0);
    $this->getJson(route('inbox.index', ['tab' => 'fetched']))->assertJsonPath('conversations', []);

    Ban::query()->delete();

    $this->getJson(route('inbox.index'))
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('unread', 2)
        ->assertJsonPath('requests', 1);
});

it('requires authentication', function (): void {
    $this->get(route('conversation.index'))->assertRedirect();
});
