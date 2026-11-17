<?php

declare(strict_types=1);

use App\Actions\CreateConversationCall;
use App\Actions\CreateOrgCall;
use App\Enums\OrgEvent;
use App\Models\Message;
use App\Models\User;
use App\Queries\CallQuery;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('rings, answers and ends a call through signed requests', function (): void {
    $caller = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    $callerKey = apiKeyPairFor($caller);
    $partnerKey = apiKeyPairFor($partner);
    sendChatMessage($caller, $partner);

    signedApiRequest($callerKey, 'POST', route('api-conversation-call.store', $partner), ['sdp' => "v=0\r\no=caller\r\n"])->assertNoContent();

    expect(resolve(CallQuery::class)->get($partner)->offer)->toBe("v=0\r\no=caller\r\n");
    signedApiRequest($callerKey, 'GET', route('api-conversation-call.show', $partner))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['call' => ['answer' => null]]);
    signedApiRequest($partnerKey, 'PUT', route('api-conversation-call.update', $caller), ['sdp' => 'v=0 answer'])->assertNoContent();
    signedApiRequest($callerKey, 'GET', route('api-conversation-call.show', $partner))->assertExactJson(['call' => ['answer' => 'v=0 answer']]);
    signedApiRequest($callerKey, 'DELETE', route('api-conversation-call.destroy', $partner))->assertNoContent();

    expect(resolve(CallQuery::class)->get($partner))->toBeNull()
        ->and(Message::query()->whereNotNull('event')->orderBy('id')->pluck('event')->all())
        ->toBe([OrgEvent::CallStarted, OrgEvent::CallAnswered]);
});

it('records a declined or failed incoming call', function (bool $failed, OrgEvent $event): void {
    $caller = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($caller, $partner);
    resolve(CreateConversationCall::class)->handle($caller, $partner, 'v=0');

    signedApiRequest(apiKeyPairFor($partner), 'DELETE', route('api-conversation-call.destroy', $caller), ['failed' => $failed])->assertNoContent();

    expect(resolve(CallQuery::class)->get($partner))->toBeNull();
    $this->assertDatabaseHas('messages', ['event' => $event->value, 'user_id' => $partner->id]);
})->with([[false, OrgEvent::CallDeclined], [true, OrgEvent::CallFailed]]);

it('hides every call operation across a block', function (): void {
    $caller = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    $partner->blocking()->attach($caller);
    $key = apiKeyPairFor($caller);

    signedApiRequest($key, 'POST', route('api-conversation-call.store', $partner), ['sdp' => 'v=0'])->assertNotFound();
    signedApiRequest($key, 'PUT', route('api-conversation-call.update', $partner), ['sdp' => 'v=0'])->assertNotFound();
    signedApiRequest($key, 'GET', route('api-conversation-call.show', $partner))->assertNotFound();
    signedApiRequest($key, 'DELETE', route('api-conversation-call.destroy', $partner))->assertNotFound();
});

it('refuses a call before the conversation starts and an answer after it ends', function (): void {
    $caller = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    $key = apiKeyPairFor($caller);

    signedApiRequest($key, 'POST', route('api-conversation-call.store', $partner), ['sdp' => 'v=0'])
        ->assertForbidden()->assertJsonPath('message', "Say hi to {$partner->mention()} before calling them.");
    signedApiRequest($key, 'PUT', route('api-conversation-call.update', $partner), ['sdp' => 'v=0'])
        ->assertForbidden()->assertJsonPath('message', 'This call has ended.');
    expect(resolve(CallQuery::class)->get($partner))->toBeNull();
});

it('refuses calling when either person is in an org call', function (bool $callerIsInOne): void {
    $caller = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($caller, $partner);
    Cache::put(CreateOrgCall::memberKey(($callerIsInOne ? $caller : $partner)->id), 'org', CreateOrgCall::SECONDS);

    signedApiRequest(apiKeyPairFor($caller), 'POST', route('api-conversation-call.store', $partner), ['sdp' => 'v=0'])
        ->assertForbidden()->assertJsonPath('message', $callerIsInOne ? "You're already on a call." : "You can't call {$partner->mention()} right now.");
})->with([true, false]);

it('rejects an invalid offer and answer without changing the call', function (): void {
    $caller = User::factory()->withRemotes()->create();
    $partner = User::factory()->withRemotes()->create();
    sendChatMessage($caller, $partner);

    signedApiRequest(apiKeyPairFor($caller), 'POST', route('api-conversation-call.store', $partner), ['sdp' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors('sdp');
    expect(resolve(CallQuery::class)->get($partner))->toBeNull();
    resolve(CreateConversationCall::class)->handle($caller, $partner, 'v=0');
    signedApiRequest(apiKeyPairFor($partner), 'PUT', route('api-conversation-call.update', $caller), ['sdp' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors('sdp');
    expect(resolve(CallQuery::class)->get($partner)->answer)->toBeNull();
});
