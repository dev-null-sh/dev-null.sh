<?php

declare(strict_types=1);

use App\Enums\RemotesFrom;
use App\Enums\RemoteStatus;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Str;

it('only lets you push to people who follow you while requests are off', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create(['handle' => 'nunomaduro']);

    $this->actingAs($mark)->getJson(route('conversation-message.index', $nuno))->assertJsonPath('access', 'restricted');
    $this->postJson(route('conversation-message.store', $nuno), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])
        ->assertForbidden()
        ->assertJsonPath('message', "@nunomaduro doesn't follow you, so you can't push to them yet.");

    $nuno->following()->attach($mark);

    $this->getJson(route('conversation-message.index', $nuno))->assertJsonPath('access', 'open');
    sendChatMessage($mark, $nuno, 'hi');

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations');
    sendChatMessage($nuno, $mark, 'hey, welcome');
});

it('ignores everyone while it is off, even for people who chose it before', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->withRemotes(RemotesFrom::Everyone)->create();

    $this->actingAs($mark)->getJson(route('conversation-message.index', $nuno))->assertJsonPath('access', 'restricted');
    $this->actingAs($nuno)->putJson(route('user-remote-preferences.update'), ['from' => 'everyone', 'pull_receipts' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('from');
});

it('keeps fetched requests out of reach while requests are off', function (): void {
    config(['remotes.requests' => true]);
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    sendChatMessage($mark, $nuno, 'hey');
    config(['remotes.requests' => false]);

    expect(ConversationParticipant::query()->where('user_id', $nuno->id)->sole()->status)->toBe(RemoteStatus::Fetched);

    $this->actingAs($nuno)->getJson(route('inbox.index', ['tab' => 'fetched']))
        ->assertUnprocessable()->assertJsonValidationErrors('tab');
    $this->getJson(route('inbox.index'))->assertJsonPath('requests', 0)->assertJsonPath('unread', 0);
    $this->postJson(route('conversation-merge.store', $mark))->assertNotFound();
    $this->postJson(route('conversation-drop.store', $mark))->assertNotFound();
});

it('lets site admins push to anyone with remotes on', function (): void {
    config(['app.admins' => ['mark']]);
    $mark = User::factory()->create(['handle' => 'mark']);
    $nuno = User::factory()->create();
    $off = User::factory()->create(['remotes_enabled' => false]);

    sendChatMessage($mark, $nuno, 'hi from staff');

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations');
    $this->actingAs($mark)->getJson(route('conversation-message.index', $off))->assertJsonPath('access', 'closed');
});
