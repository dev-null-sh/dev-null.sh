<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('mutes a conversation for the user only, shows it in their inbox and thread, and unmutes it', function (string $duration, string $expected): void {
    $this->travelTo(now()->parse('2026-01-02T02:00:00Z'));
    $this->user->update(['timezone' => 'America/Los_Angeles']);
    $nuno = User::factory()->withRemotes()->create(['timezone' => 'Asia/Kathmandu']);
    sendChatMessage($nuno, $this->user);

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-mute.update', $nuno), ['duration' => $duration])
        ->assertOk()
        ->assertExactJson(['muted_until_at' => $expected]);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation.index'))->assertJsonPath('data.0.muted_until_at', $expected);
    signedApiRequest($this->keyPair, 'GET', route('api-conversation-message.index', $nuno))->assertJsonPath('data.muted_until_at', $expected);
    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.muted_until_at', null);

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-mute.update', $nuno), ['duration' => 'off'])
        ->assertExactJson(['muted_until_at' => null]);
})->with([
    'an hour' => ['hour', '2026-01-02T03:00:00.000000Z'],
    'the signed users next local midnight' => ['tomorrow', '2026-01-02T08:00:00.000000Z'],
]);

it('mutes an org for the user only', function (string $duration, string $expected): void {
    $this->travelTo(now()->parse('2026-03-08T05:00:00Z'));
    $this->user->update(['timezone' => 'America/New_York']);
    [$nuno] = followersOf($this->user, 1);
    $nuno->update(['timezone' => 'Asia/Kathmandu']);
    $this->user->following()->attach($nuno);
    $org = createOrg($nuno, [$this->user]);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-mute.update', $org), ['duration' => $duration])
        ->assertOk()
        ->assertExactJson(['muted_until_at' => $expected]);

    signedApiRequest($this->keyPair, 'GET', route('api-org-message.index', $org))->assertJsonPath('data.muted_until_at', $expected);
    $this->actingAs($nuno)->getJson(route('org-message.index', $org))->assertJsonPath('muted_until_at', null);
})->with([
    'forever' => ['forever', '9999-12-31T00:00:00.000000Z'],
    'the signed members next local midnight across DST' => ['tomorrow', '2026-03-09T04:00:00.000000Z'],
]);

it('rejects durations it does not offer', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user);

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-mute.update', $nuno), ['duration' => 'month'])
        ->assertJsonValidationErrors('duration');
    signedApiRequest($this->keyPair, 'PUT', route('api-org-mute.update', createOrg($this->user, followersOf($this->user, 1))), [])
        ->assertJsonValidationErrors('duration');
});

it('has nothing to mute before the conversation starts, or with people the user cannot message', function (): void {
    $stranger = User::factory()->withRemotes()->create();
    $blocker = User::factory()->withRemotes()->create();
    sendChatMessage($blocker, $this->user);
    $blocker->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-mute.update', $stranger), ['duration' => 'hour'])->assertNotFound();
    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-mute.update', $blocker), ['duration' => 'hour'])->assertNotFound();

    expect(Conversation::query()->count())->toBe(1);
});

it('hides muting an org from people outside it', function (): void {
    $maintainer = User::factory()->withRemotes()->create();
    $org = createOrg($maintainer, followersOf($maintainer, 1));

    signedApiRequest($this->keyPair, 'PUT', route('api-org-mute.update', $org), ['duration' => 'hour'])->assertNotFound();
});
