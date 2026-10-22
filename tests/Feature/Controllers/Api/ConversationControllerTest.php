<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('lists conversations by latest message, with what is unread, as the web inbox does', function (): void {
    $nuno = User::factory()->withRemotes()->create(['username' => 'nunomaduro', 'name' => 'Nuno Maduro']);
    $taylor = User::factory()->withRemotes()->create(['username' => 'taylorotwell']);

    $this->travel(-5)->minutes();
    $thanks = sendChatMessage($this->user, $taylor, 'thanks!');
    $this->travelBack();
    sendChatMessage($nuno, $this->user, 'did you see the PR?');
    $latest = sendChatMessage($nuno, $this->user, 'ship it');

    $response = signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.kind', 'direct')
        ->assertJsonPath('data.0.name', null)
        ->assertJsonPath('data.0.partner.username', 'nunomaduro')
        ->assertJsonPath('data.0.partner.name', 'Nuno Maduro')
        ->assertJsonPath('data.0.partner.lane', $nuno->lane())
        ->assertJsonPath('data.0.members', null)
        ->assertJsonPath('data.0.last_message.id', $latest->id)
        ->assertJsonPath('data.0.last_message.body', 'ship it')
        ->assertJsonPath('data.0.last_message.user_id', $nuno->id)
        ->assertJsonPath('data.0.ahead', 2)
        ->assertJsonPath('data.0.is_archived', false)
        ->assertJsonPath('data.1.partner.username', 'taylorotwell')
        ->assertJsonPath('data.1.last_message.id', $thanks->id)
        ->assertJsonPath('data.1.ahead', 0)
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('unread_count', 2)
        ->assertJsonPath('requests_count', 0);
});

it('shows how far the partner pulled only while both share pulls', function (bool $sharesPulls): void {
    $nuno = User::factory()->withRemotes()->create(['pull_receipts' => $sharesPulls]);
    $message = sendChatMessage($this->user, $nuno);

    $this->actingAs($nuno)->putJson(route('conversation-read.update', $this->user), ['through' => $message->id])->assertNoContent();

    $response = signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'))->assertOk();

    expect($response->json('data.0.partner_read_through_id'))->toBe($sharesPulls ? $message->id : 0)
        ->and($response->json('data.0.partner_read_at') !== null)->toBe($sharesPulls);
})->with([true, false]);

it('lists orgs with their name and members', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno], 'dev/null core');

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $org)
        ->assertJsonPath('data.0.kind', 'org')
        ->assertJsonPath('data.0.name', 'dev/null core')
        ->assertJsonPath('data.0.partner', null)
        ->assertJsonPath('data.0.members.0.user.username', $this->user->username)
        ->assertJsonPath('data.0.members.0.role', 'maintainer')
        ->assertJsonPath('data.0.members.1.user.username', $nuno->username)
        ->assertJsonPath('data.0.members.1.role', 'contributor')
        ->assertJsonPath('data.0.last_message.event', 'created');
});

it('lists the stashed and fetched tabs', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $stranger = User::factory()->withRemotes()->create(['username' => 'stranger']);
    $this->user->update(['remotes_from' => 'following']);

    $this->user->following()->attach($nuno);
    sendChatMessage($nuno, $this->user);
    sendChatMessage($stranger, $this->user);
    $this->actingAs($this->user)->postJson(route('conversation-archive.store', $nuno))->assertNoContent();

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('requests_count', 1);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index', ['tab' => 'stashed']))
        ->assertOk()
        ->assertJsonPath('data.0.partner.id', $nuno->id)
        ->assertJsonPath('data.0.is_archived', true);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index', ['tab' => 'fetched']))
        ->assertOk()
        ->assertJsonPath('data.0.partner.username', 'stranger');
});

it('has no fetched tab while requests are off', function (): void {
    config(['remotes.requests' => false]);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index', ['tab' => 'fetched']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tab');
});

it('searches conversations by name or handle', function (string $term): void {
    $nuno = User::factory()->withRemotes()->create(['username' => 'nunomaduro', 'name' => 'Nuno Maduro']);
    $taylor = User::factory()->withRemotes()->create(['username' => 'taylorotwell', 'name' => 'Taylor Otwell']);

    sendChatMessage($nuno, $this->user);
    sendChatMessage($taylor, $this->user);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index', ['q' => $term]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.partner.username', 'nunomaduro');
})->with(['@NunoMad', 'nuno ma']);

it('pages through conversations, 30 at a time', function (): void {
    $partners = User::factory()->withRemotes()->count(31)->create();

    foreach ($partners as $index => $partner) {
        $this->travelTo(now()->addMinute());
        sendChatMessage($partner, $this->user, "message {$index}");
    }

    $first = signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('has_more', true);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index', ['before' => $first->json('data.29.id')]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.last_message.body', 'message 0')
        ->assertJsonPath('has_more', false);
});

it('leaves out conversations across a block', function (): void {
    $nuno = User::factory()->withRemotes()->create();

    sendChatMessage($nuno, $this->user);
    $nuno->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('unread_count', 0);
});

it('is not there while the user has Remotes off', function (): void {
    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'))->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $this->getJson(route('api-conversation.index'))->assertUnauthorized();
});
