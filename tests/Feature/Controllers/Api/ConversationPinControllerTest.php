<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('shares pins between signed API requests and web sessions', function (): void {
    $user = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $user);
    $key = apiKeyPairFor($user);

    $at = signedApiRequest($key, 'PUT', route('api-conversation-pin.update', $partner), ['pinned' => true])
        ->assertOk()->json('pinned_at');

    signedApiRequest($key, 'GET', route('api-conversation.index'))->assertJsonPath('data.0.pinned_at', $at);
    signedApiRequest($key, 'GET', route('api-conversation-message.index', $partner))->assertJsonPath('data.pinned_at', $at);
    $this->actingAs($user)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.pinned_at', $at);
    signedApiRequest($key, 'PUT', route('api-conversation-pin.update', $partner), ['pinned' => false])->assertExactJson(['pinned_at' => null]);
    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.pinned_at', null);
});

it('pins and unpins an org through the signed API', function (): void {
    $user = User::factory()->withRemotes()->create();
    $org = createOrg($user, followersOf($user, 1));
    $key = apiKeyPairFor($user);

    $at = signedApiRequest($key, 'PUT', route('api-org-pin.update', $org), ['pinned' => true])->assertOk()->json('pinned_at');

    signedApiRequest($key, 'GET', route('api-org-message.index', $org))->assertJsonPath('data.pinned_at', $at);
    signedApiRequest($key, 'PUT', route('api-org-pin.update', $org), ['pinned' => false])->assertExactJson(['pinned_at' => null]);
});

it('validates pin choices through the signed API', function (): void {
    $user = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($partner, $user);
    $org = createOrg($user, followersOf($user, 1));
    $key = apiKeyPairFor($user);

    signedApiRequest($key, 'PUT', route('api-conversation-pin.update', $partner), [])->assertJsonValidationErrors('pinned');
    signedApiRequest($key, 'PUT', route('api-org-pin.update', $org), ['pinned' => 'yes'])->assertJsonValidationErrors('pinned');
});

it('hides nonexistent direct remotes and orgs outside the signed users membership', function (): void {
    $user = User::factory()->withRemotes()->create();
    $other = User::factory()->withRemotes()->create();
    $org = createOrg($other, followersOf($other, 1));
    $key = apiKeyPairFor($user);

    signedApiRequest($key, 'PUT', route('api-conversation-pin.update', $other), ['pinned' => true])->assertNotFound();
    signedApiRequest($key, 'PUT', route('api-org-pin.update', $org), ['pinned' => true])->assertNotFound();
});
