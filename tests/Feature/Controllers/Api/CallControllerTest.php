<?php

declare(strict_types=1);

use App\Actions\CreateConversationCall;
use App\Actions\UpdateConversationCall;
use App\Models\User;
use App\Queries\CallQuery;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->partner = User::factory()->withRemotes()->create();
    sendChatMessage($this->partner, $this->user);
});

it('shows the call ringing for the user, with its caller and offer', function (): void {
    resolve(CreateConversationCall::class)->handle($this->partner, $this->user, "v=0\r\ns=offer\r\n");

    signedApiRequest($this->keyPair, 'GET', route('api-call.show'))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('call.partner.handle', $this->partner->handle)
        ->assertJsonPath('call.sdp', "v=0\r\ns=offer\r\n")
        ->assertJsonPath('call.is_muted', false);
});

it('stops ringing once the user answers', function (): void {
    resolve(CreateConversationCall::class)->handle($this->partner, $this->user, "v=0\r\n");
    resolve(UpdateConversationCall::class)->handle($this->user, $this->partner, resolve(CallQuery::class)->get($this->user), "v=0\r\n");

    signedApiRequest($this->keyPair, 'GET', route('api-call.show'))->assertOk()
        ->assertExactJson(['call' => null]);
});

it('returns 401 for an unsigned check', function (): void {
    $this->getJson(route('api-call.show'))->assertUnauthorized();
});
