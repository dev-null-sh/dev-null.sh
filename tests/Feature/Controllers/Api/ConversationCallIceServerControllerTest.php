<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'remotes.requests' => true,
        'remotes.everyone' => true,
        'services.cloudflare_turn.key_id' => 'key',
        'services.cloudflare_turn.api_token' => 'token',
    ]);
    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->partner = User::factory()->withRemotes()->create();
});

it('hands out fresh relay credentials for a call with the partner', function (): void {
    sendChatMessage($this->partner, $this->user);
    Http::fake(['rtc.live.cloudflare.com/*' => Http::response(['iceServers' => [
        ['urls' => ['turn:turn.cloudflare.com:3478?transport=udp'], 'username' => 'user', 'credential' => 'secret'],
    ]])]);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-call-ice-server.index', $this->partner))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'ice_servers' => [['urls' => ['turn:turn.cloudflare.com:3478?transport=udp'], 'username' => 'user', 'credential' => 'secret']],
            'is_relay_only' => true,
        ]);
});

it('hands out nothing without a conversation', function (): void {
    signedApiRequest($this->keyPair, 'GET', route('api-conversation-call-ice-server.index', $this->partner))->assertNotFound();
});

it('hands out nothing while calls are not available', function (): void {
    sendChatMessage($this->partner, $this->user);
    config(['services.cloudflare_turn.api_token' => null]);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-call-ice-server.index', $this->partner))->assertForbidden()
        ->assertJsonPath('message', "Calls aren't available right now.");
});
