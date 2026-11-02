<?php

declare(strict_types=1);

use App\Models\User;

it('only gives relay settings to someone in a messageable conversation', function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
    $caller = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    $key = apiKeyPairFor($caller);
    $url = route('api-conversation-call-ice-server.index', $partner);

    signedApiRequest($key, 'GET', $url)->assertNotFound();
    sendChatMessage($caller, $partner);
    signedApiRequest($key, 'GET', $url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['ice_servers' => [], 'is_relay_only' => false]);
    $partner->blocking()->attach($caller);
    signedApiRequest($key, 'GET', $url)->assertNotFound();
});
