<?php

declare(strict_types=1);

use App\Actions\CreateConversationCall;
use App\Actions\CreateOrgCall;
use App\Enums\OrgEvent;
use App\Models\Message;
use App\Models\User;
use App\Queries\CallQuery;

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
    $this->offer = "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\ns=offer\r\n";
    $this->answer = "v=0\r\no=- 3 4 IN IP4 127.0.0.1\r\ns=answer\r\n";
});

it('rings the partner with the offer, which the web picks up and answers', function (): void {
    sendChatMessage($this->partner, $this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-call.store', $this->partner), ['sdp' => $this->offer])->assertNoContent();

    expect(resolve(CallQuery::class)->incoming($this->partner))->toMatchArray(['sdp' => $this->offer])
        ->and(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::CallStarted);
    signedApiRequest($this->keyPair, 'GET', route('api-conversation-call.show', $this->partner))->assertOk()
        ->assertExactJson(['call' => ['answer' => null]]);

    $this->actingAs($this->partner)->putJson(route('conversation-call.update', $this->user), ['sdp' => $this->answer])->assertNoContent();

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-call.show', $this->partner))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['call' => ['answer' => $this->answer]]);
});

it('has no outgoing call before ringing', function (): void {
    sendChatMessage($this->partner, $this->user);

    signedApiRequest($this->keyPair, 'GET', route('api-conversation-call.show', $this->partner))->assertOk()
        ->assertExactJson(['call' => null]);
});

it('answers a call the partner placed on the web', function (): void {
    sendChatMessage($this->partner, $this->user);
    $this->actingAs($this->partner)->postJson(route('conversation-call.store', $this->user), ['sdp' => $this->offer])->assertNoContent();

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-call.update', $this->partner), ['sdp' => $this->answer])->assertNoContent();

    expect(resolve(CallQuery::class)->outgoing($this->partner, $this->user))->toBe(['answer' => $this->answer])
        ->and(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::CallAnswered);
});

it('does not answer a call that is not ringing', function (): void {
    sendChatMessage($this->partner, $this->user);

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-call.update', $this->partner), ['sdp' => $this->answer])->assertForbidden()
        ->assertJsonPath('message', 'This call has ended.');
});

it('logs how an unanswered call ended', function (bool $isCaller, array $data, OrgEvent $event): void {
    sendChatMessage($this->partner, $this->user);
    [$caller, $callee] = $isCaller ? [$this->user, $this->partner] : [$this->partner, $this->user];
    resolve(CreateConversationCall::class)->handle($caller, $callee, $this->offer);

    signedApiRequest($this->keyPair, 'DELETE', route('api-conversation-call.destroy', $this->partner), $data)->assertNoContent();

    expect(resolve(CallQuery::class)->get($callee))->toBeNull()
        ->and(Message::query()->latest('id')->firstOrFail()->event)->toBe($event);
})->with([
    'cancelled' => [true, [], OrgEvent::CallCancelled],
    'declined' => [false, [], OrgEvent::CallDeclined],
    'failed to answer' => [false, ['failed' => true], OrgEvent::CallFailed],
]);

it('requires a conversation before calling', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-conversation-call.store', $this->partner), ['sdp' => $this->offer])->assertForbidden()
        ->assertJsonPath('message', "Say hi to {$this->partner->mention()} before calling them.");

    expect(resolve(CallQuery::class)->get($this->partner))->toBeNull();
});

it('does not call while calls are not available', function (): void {
    sendChatMessage($this->partner, $this->user);
    config(['services.cloudflare_turn.key_id' => null]);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-call.store', $this->partner), ['sdp' => $this->offer])->assertForbidden()
        ->assertJsonPath('message', "Calls aren't available right now.");
});

it('does not call while the user is on an org call', function (): void {
    sendChatMessage($this->partner, $this->user);
    cache()->put(CreateOrgCall::memberKey($this->user->id), fake()->uuid(), 60);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-call.store', $this->partner), ['sdp' => $this->offer])->assertForbidden()
        ->assertJsonPath('message', "You're already on a call.");
});

it('does not call a partner who cannot take it', function (Closure $busy): void {
    sendChatMessage($this->partner, $this->user);
    $busy($this->partner);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-call.store', $this->partner), ['sdp' => $this->offer])->assertForbidden()
        ->assertJsonPath('message', "You can't call {$this->partner->mention()} right now.");
})->with([
    'with Remotes disabled' => [fn (User $partner): bool => $partner->update(['remotes_enabled' => false])],
    'ringing for someone else' => [function (User $partner): void {
        $caller = User::factory()->withRemotes()->create();
        sendChatMessage($caller, $partner);
        resolve(CreateConversationCall::class)->handle($caller, $partner, "v=0\r\n");
    }],
    'on an org call' => [fn (User $partner): bool => cache()->put(CreateOrgCall::memberKey($partner->id), fake()->uuid(), 60)],
]);

it('rejects an offer that is not a session description', function (): void {
    sendChatMessage($this->partner, $this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-call.store', $this->partner), ['sdp' => 'hello'])->assertUnprocessable()
        ->assertJsonValidationErrors('sdp');
});

it('hides calls with someone either person blocks', function (string $method, string $route, array $data): void {
    sendChatMessage($this->partner, $this->user);
    $this->partner->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, $method, route($route, $this->partner), $data)->assertNotFound();
})->with([
    'show' => ['GET', 'api-conversation-call.show', []],
    'store' => ['POST', 'api-conversation-call.store', ['sdp' => "v=0\r\n"]],
    'update' => ['PUT', 'api-conversation-call.update', ['sdp' => "v=0\r\n"]],
    'destroy' => ['DELETE', 'api-conversation-call.destroy', []],
]);

it('hides calls while the user has Remotes disabled', function (): void {
    sendChatMessage($this->partner, $this->user);
    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'POST', route('api-conversation-call.store', $this->partner), ['sdp' => $this->offer])->assertNotFound();

    expect(resolve(CallQuery::class)->get($this->partner))->toBeNull();
});

it('returns 401 for an unsigned call', function (): void {
    $this->postJson(route('api-conversation-call.store', $this->partner), ['sdp' => $this->offer])->assertUnauthorized();

    expect(resolve(CallQuery::class)->get($this->partner))->toBeNull();
});
