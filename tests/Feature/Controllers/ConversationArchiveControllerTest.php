<?php

declare(strict_types=1);

use App\Models\User;

it('moves an archived conversation to the archived tab, for you only', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create(['username' => 'nunomaduro']);
    sendChatMessage($nuno, $mark, 'ship it');

    $this->actingAs($mark)->postJson(route('conversation-archive.store', $nuno))->assertNoContent();

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations', [])->assertJsonPath('unread', 0);
    $this->getJson(route('inbox.index', ['archived' => true]))
        ->assertJsonPath('conversations.0.partner.username', 'nunomaduro')
        ->assertJsonPath('conversations.0.is_archived', true);
    $this->getJson(route('conversation-message.index', $nuno))->assertJsonPath('is_archived', true);

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations');
});

it('brings an archived conversation back when either of you writes', function (string $writer): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $this->actingAs($mark)->postJson(route('conversation-archive.store', $nuno))->assertNoContent();

    $writer === 'you' ? sendChatMessage($mark, $nuno, 'one more thing') : sendChatMessage($nuno, $mark, 'one more thing');

    $this->actingAs($mark)->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations');
    $this->getJson(route('inbox.index', ['archived' => true]))->assertJsonPath('conversations', []);
})->with(['you', 'them']);

it('unarchives a conversation', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $this->actingAs($mark)->postJson(route('conversation-archive.store', $nuno));

    $this->deleteJson(route('conversation-archive.destroy', $nuno))->assertNoContent();

    $this->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations')->assertJsonPath('conversations.0.is_archived', false);
});

it('hides archiving from people you cannot message', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $nuno->blocking()->attach($mark);

    $this->actingAs($mark)->postJson(route('conversation-archive.store', $nuno))->assertNotFound();
    $this->deleteJson(route('conversation-archive.destroy', $nuno))->assertNotFound();
});

it('requires authentication', function (): void {
    $this->postJson(route('conversation-archive.store', User::factory()->create()))->assertUnauthorized();
});
