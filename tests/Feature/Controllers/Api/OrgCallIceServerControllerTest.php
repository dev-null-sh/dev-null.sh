<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;

it('gives relay settings only to org members with Remotes enabled', function (): void {
    $user = User::factory()->withRemotes()->create();
    [$member] = followersOf($user, 1);
    $org = Conversation::query()->findOrFail(createOrg($user, [$member]));
    $url = route('api-org-call-ice-server.index', $org);
    $key = apiKeyPairFor($user);

    signedApiRequest($key, 'GET', $url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['ice_servers' => [], 'is_relay_only' => false]);
    signedApiRequest(apiKeyPairFor(User::factory()->withRemotes()->create()), 'GET', $url)->assertNotFound();
    $user->update(['remotes_enabled' => false]);
    signedApiRequest($key, 'GET', $url)->assertNotFound();
});
