<?php

declare(strict_types=1);

use App\Services\TurnProviders\CloudflareTurnProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.cloudflare_turn.key_id' => 'key-id', 'services.cloudflare_turn.api_token' => 'api-token']);
});

it('only ever relays', function (): void {
    expect(new CloudflareTurnProvider()->isRelayOnly())->toBeTrue();
});

it('asks for four hours of credentials and keeps only the relay entries browsers can use', function (): void {
    Http::fake(['rtc.live.cloudflare.com/*' => Http::response(['iceServers' => [
        ['urls' => ['stun:stun.cloudflare.com:3478', 'stun:stun.cloudflare.com:53']],
        ['urls' => [
            'turn:turn.cloudflare.com:3478?transport=udp',
            'turn:turn.cloudflare.com:53?transport=udp',
            'turns:turn.cloudflare.com:443?transport=tcp',
            'turn:turn.cloudflare.com:53',
        ], 'username' => 'user', 'credential' => 'secret'],
        ['urls' => 'turns:turn.cloudflare.com:5349', 'username' => 'other', 'credential' => 'other-secret'],
    ]])]);

    expect(new CloudflareTurnProvider()->iceServers())->toBe([
        ['urls' => ['turn:turn.cloudflare.com:3478?transport=udp', 'turns:turn.cloudflare.com:443?transport=tcp'], 'username' => 'user', 'credential' => 'secret'],
        ['urls' => ['turns:turn.cloudflare.com:5349'], 'username' => 'other', 'credential' => 'other-secret'],
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://rtc.live.cloudflare.com/v1/turn/keys/key-id/credentials/generate-ice-servers'
        && $request->hasHeader('Authorization', 'Bearer api-token')
        && $request->data() === ['ttl' => 14400]);
});

it('fails when the relay refuses', function (): void {
    Http::fake(['rtc.live.cloudflare.com/*' => Http::response([], 401)]);

    new CloudflareTurnProvider()->iceServers();
})->throws(RequestException::class);
