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
    $this->user = User::factory()->withRemotes()->create();
    $this->partner = User::factory()->withRemotes()->create();
});

/**
 * @return list<OrgEvent>
 */
function callEvents(): array
{
    return Message::query()->whereNotNull('event')->orderBy('id')->pluck('event')->all();
}

it('rings the partner with the offer as sent, line breaks included, and logs the call', function (): void {
    sendChatMessage($this->user, $this->partner);

    $this->actingAs($this->user)->postJson(route('conversation-call.store', $this->partner), ['sdp' => "v=0\r\no=caller\r\n"])->assertNoContent();

    $call = resolve(CallQuery::class)->get($this->partner);
    expect($call->callerId)->toBe($this->user->id)
        ->and($call->offer)->toBe("v=0\r\no=caller\r\n")
        ->and($call->answer)->toBeNull()
        ->and($call->rangAt)->toBe(now()->getTimestamp())
        ->and(callEvents())->toBe([OrgEvent::CallStarted]);
    $this->assertDatabaseHas('messages', ['event' => 'call_started', 'user_id' => $this->user->id]);
});

it('keeps the offer encrypted in the cache', function (): void {
    sendChatMessage($this->user, $this->partner);

    $this->actingAs($this->user)->postJson(route('conversation-call.store', $this->partner), ['sdp' => "v=0\r\no=caller"])->assertNoContent();

    expect(Cache::get(CreateConversationCall::key($this->partner)))->toBeString()->not->toContain('o=caller');
});

it('refuses a call before anyone said hi', function (): void {
    $this->actingAs($this->user)->postJson(route('conversation-call.store', $this->partner), ['sdp' => 'v=0'])
        ->assertForbidden()
        ->assertJsonPath('message', "Say hi to {$this->partner->mention()} before calling them.");

    expect(resolve(CallQuery::class)->get($this->partner))->toBeNull();
});

it('hides a partner the user cannot message', function (): void {
    sendChatMessage($this->user, $this->partner);
    $this->partner->blocking()->attach($this->user);

    $this->actingAs($this->user)->postJson(route('conversation-call.store', $this->partner), ['sdp' => 'v=0'])->assertNotFound();
});

it('refuses calling a partner someone else is ringing', function (): void {
    sendChatMessage($this->user, $this->partner);
    $other = User::factory()->withRemotes()->create();
    sendChatMessage($other, $this->partner);
    resolve(CreateConversationCall::class)->handle($other, $this->partner, 'v=0');

    $this->actingAs($this->user)->postJson(route('conversation-call.store', $this->partner), ['sdp' => 'v=0'])
        ->assertForbidden()
        ->assertJsonPath('message', "You can't call {$this->partner->mention()} right now.");
});

it('refuses a call when either person is in an org call', function (bool $callerIsInOne): void {
    sendChatMessage($this->user, $this->partner);
    Cache::put(CreateOrgCall::memberKey(($callerIsInOne ? $this->user : $this->partner)->id), 'org', CreateOrgCall::SECONDS);

    $this->actingAs($this->user)->postJson(route('conversation-call.store', $this->partner), ['sdp' => 'v=0'])
        ->assertForbidden()
        ->assertJsonPath('message', $callerIsInOne ? "You're already on a call." : "You can't call {$this->partner->mention()} right now.");
})->with([true, false]);

it('validates the offer', function (mixed $sdp): void {
    sendChatMessage($this->user, $this->partner);

    $this->actingAs($this->user)->postJson(route('conversation-call.store', $this->partner), ['sdp' => $sdp])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sdp');
})->with([null, 'not an offer', 'v=0'.str_repeat('a', 10000)]);

it('shows the caller nothing until the partner answers, then their answer', function (): void {
    sendChatMessage($this->user, $this->partner);
    resolve(CreateConversationCall::class)->handle($this->user, $this->partner, 'v=0 offer');

    $this->actingAs($this->user)->getJson(route('conversation-call.show', $this->partner))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['call' => ['answer' => null]]);

    $this->actingAs($this->partner)->putJson(route('conversation-call.update', $this->user), ['sdp' => "v=0 answer\r\n"])->assertNoContent();

    $this->actingAs($this->user)->getJson(route('conversation-call.show', $this->partner))
        ->assertExactJson(['call' => ['answer' => "v=0 answer\r\n"]]);
    expect(resolve(CallQuery::class)->get($this->partner)->offer)->toBe('v=0 offer')
        ->and(callEvents())->toBe([OrgEvent::CallStarted, OrgEvent::CallAnswered]);
});

it('shows no call to someone who is not ringing the partner', function (): void {
    sendChatMessage($this->user, $this->partner);
    resolve(CreateConversationCall::class)->handle($this->partner, $this->user, 'v=0');

    $this->actingAs($this->user)->getJson(route('conversation-call.show', $this->partner))->assertExactJson(['call' => null]);
});

it('hides the call status from someone who cannot message the partner', function (): void {
    sendChatMessage($this->user, $this->partner);
    $this->user->blocking()->attach($this->partner);

    $this->actingAs($this->user)->getJson(route('conversation-call.show', $this->partner))->assertNotFound();
});

it('only answers a call the partner is ringing the user with', function (): void {
    sendChatMessage($this->user, $this->partner);

    $this->actingAs($this->partner)->putJson(route('conversation-call.update', $this->user), ['sdp' => 'v=0'])
        ->assertForbidden()
        ->assertJsonPath('message', 'This call has ended.');

    resolve(CreateConversationCall::class)->handle($this->user, $this->partner, 'v=0');
    $this->actingAs($this->partner)->putJson(route('conversation-call.update', $this->user), ['sdp' => 'v=0'])->assertNoContent();

    $this->actingAs($this->partner)->putJson(route('conversation-call.update', $this->user), ['sdp' => 'v=0'])->assertForbidden();
});

it('hides the answer endpoint from someone who cannot message the caller', function (): void {
    sendChatMessage($this->user, $this->partner);
    resolve(CreateConversationCall::class)->handle($this->user, $this->partner, 'v=0');
    $this->partner->blocking()->attach($this->user);

    $this->actingAs($this->partner)->putJson(route('conversation-call.update', $this->user), ['sdp' => 'v=0'])->assertNotFound();
});

it('validates the answer', function (): void {
    sendChatMessage($this->user, $this->partner);
    resolve(CreateConversationCall::class)->handle($this->user, $this->partner, 'v=0');

    $this->actingAs($this->partner)->putJson(route('conversation-call.update', $this->user), ['sdp' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sdp');
});

it('logs how an unanswered call ended', function (bool $isCaller, array $body, int $rangFor, OrgEvent $event): void {
    sendChatMessage($this->user, $this->partner);
    resolve(CreateConversationCall::class)->handle($this->user, $this->partner, 'v=0');
    $this->travel($rangFor)->seconds();

    $this->actingAs($isCaller ? $this->user : $this->partner)
        ->deleteJson(route('conversation-call.destroy', $isCaller ? $this->partner : $this->user), $body)
        ->assertNoContent();

    expect(resolve(CallQuery::class)->get($this->partner))->toBeNull()
        ->and(callEvents())->toBe([OrgEvent::CallStarted, $event]);
    $this->assertDatabaseHas('messages', ['event' => $event, 'user_id' => ($isCaller ? $this->user : $this->partner)->id]);
})->with([
    'cancelled' => [true, [], 10, OrgEvent::CallCancelled],
    'missed' => [true, [], CreateConversationCall::SECONDS, OrgEvent::CallMissed],
    'declined' => [false, [], 10, OrgEvent::CallDeclined],
    'failed to answer' => [false, ['failed' => true], 10, OrgEvent::CallFailed],
]);

it('forgets an answered call without logging anything else', function (): void {
    sendChatMessage($this->user, $this->partner);
    resolve(CreateConversationCall::class)->handle($this->user, $this->partner, 'v=0');
    $this->actingAs($this->partner)->putJson(route('conversation-call.update', $this->user), ['sdp' => 'v=0'])->assertNoContent();

    $this->actingAs($this->user)->deleteJson(route('conversation-call.destroy', $this->partner))->assertNoContent();

    expect(resolve(CallQuery::class)->get($this->partner))->toBeNull()
        ->and(callEvents())->toBe([OrgEvent::CallStarted, OrgEvent::CallAnswered]);
});

it('leaves a call between other people alone', function (): void {
    sendChatMessage($this->user, $this->partner);
    $other = User::factory()->withRemotes()->create();
    sendChatMessage($other, $this->partner);
    resolve(CreateConversationCall::class)->handle($other, $this->partner, 'v=0');

    $this->actingAs($this->user)->deleteJson(route('conversation-call.destroy', $this->partner))->assertNoContent();

    expect(resolve(CallQuery::class)->get($this->partner)->callerId)->toBe($other->id)
        ->and(callEvents())->toBe([OrgEvent::CallStarted]);
});

it('hides ending a call from someone who cannot message the partner', function (): void {
    sendChatMessage($this->user, $this->partner);
    $this->user->blocking()->attach($this->partner);

    $this->actingAs($this->user)->deleteJson(route('conversation-call.destroy', $this->partner))->assertNotFound();
});

it('validates how a call ended', function (): void {
    sendChatMessage($this->user, $this->partner);

    $this->actingAs($this->user)->deleteJson(route('conversation-call.destroy', $this->partner), ['failed' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('failed');
});

it('requires authentication', function (string $method, string $route): void {
    $this->json($method, route($route, $this->partner))->assertUnauthorized();
})->with([
    ['GET', 'conversation-call.show'],
    ['POST', 'conversation-call.store'],
    ['PUT', 'conversation-call.update'],
    ['DELETE', 'conversation-call.destroy'],
]);
