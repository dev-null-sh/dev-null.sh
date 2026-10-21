<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('keeps a muted conversation in the inbox but out of the badge, for you only', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create(['username' => 'nunomaduro']);
    sendChatMessage($mark, $nuno, 'ship it');

    $this->actingAs($mark)->postJson(route('conversation-mute.store', $nuno))->assertNoContent();
    sendChatMessage($nuno, $mark, 'one more thing');

    $this->actingAs($mark)->getJson(route('inbox.index'))
        ->assertJsonPath('conversations.0.partner.username', 'nunomaduro')
        ->assertJsonPath('conversations.0.is_muted', true)
        ->assertJsonPath('conversations.0.ahead', 1)
        ->assertJsonPath('unread', 0);
    $this->getJson(route('conversation-message.index', $nuno))->assertJsonPath('is_muted', true);

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.is_muted', false);
});

it('stays muted after a new message', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $this->actingAs($mark)->postJson(route('conversation-mute.store', $nuno));

    sendChatMessage($nuno, $mark, 'still there?');
    sendChatMessage($mark, $nuno, 'yes');

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.is_muted', true);
});

it('unmutes a conversation', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $this->actingAs($mark)->postJson(route('conversation-mute.store', $nuno));

    $this->deleteJson(route('conversation-mute.destroy', $nuno))->assertNoContent();

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.is_muted', false)->assertJsonPath('unread', 1);
});

it('hides muting from people you cannot message', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $nuno->blocking()->attach($mark);

    $this->actingAs($mark)->postJson(route('conversation-mute.store', $nuno))->assertNotFound();
    $this->deleteJson(route('conversation-mute.destroy', $nuno))->assertNotFound();
});

it('requires authentication', function (): void {
    $this->postJson(route('conversation-mute.store', User::factory()->withRemotes()->create()))->assertUnauthorized();
});
