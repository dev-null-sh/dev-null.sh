<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('records a ping from a maintainer or contributor with its sender', function (bool $contributor): void {
    [$member] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$member]);
    if ($contributor) {
        $this->user = $member;
        $this->keyPair = apiKeyPairFor($this->user);
    }

    $response = signedApiRequest($this->keyPair, 'POST', route('api-org-ping.store', $org))->assertCreated();

    $ping = Message::query()->latest('id')->firstOrFail();
    $response->assertJsonPath('data.id', $ping->id)
        ->assertJsonPath('data.event', 'ping')
        ->assertJsonPath('data.body', '')
        ->assertJsonPath('data.user.username', $this->user->username);
    expect($ping->event)->toBe(OrgEvent::Ping)
        ->and($ping->conversation_id)->toBe($org)
        ->and(Conversation::query()->findOrFail($org)->last_message_id)->toBe($ping->id);
    $this->assertDatabaseCount('messages', 2);
})->with([false, true]);

it('hides an org from a nonmember trying to ping it', function (): void {
    $maintainer = User::factory()->withRemotes()->create();
    [$member] = followersOf($maintainer, 1);
    $org = createOrg($maintainer, [$member]);

    signedApiRequest($this->keyPair, 'POST', route('api-org-ping.store', $org))->assertNotFound();

    $this->assertDatabaseCount('messages', 1);
});

it('rejects pings from a member with Remotes disabled', function (): void {
    [$member] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$member]);
    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'POST', route('api-org-ping.store', $org))->assertNotFound();

    $this->assertDatabaseCount('messages', 1);
});

it('returns 401 for an unsigned org ping', function (): void {
    [$member] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$member]);

    $this->postJson(route('api-org-ping.store', $org))->assertUnauthorized();

    $this->assertDatabaseCount('messages', 1);
});
