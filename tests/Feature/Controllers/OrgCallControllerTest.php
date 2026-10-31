<?php

declare(strict_types=1);

use App\Actions\CreateOrgCall;
use App\Actions\UpdateOrgCall;
use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Queries\OrgCallQuery;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->user = User::factory()->withRemotes()->create();
    [$this->member, $this->other] = followersOf($this->user, 2);
    $this->org = Conversation::query()->findOrFail(createOrg($this->user, [$this->member, $this->other]));
});

/**
 * @return array{id: string, type: string, sdp: string}
 */
function orgCallSignal(string $type = 'offer', string $id = 'offer-1'): array
{
    return ['id' => $id, 'type' => $type, 'sdp' => "v=0\r\n{$type}\r\n"];
}

it('starts the org call, which rings the org and goes in its history', function (): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))
        ->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('call.started_at', now()->getTimestampMs())
        ->assertJsonPath('call.is_ringing', true)
        ->assertJsonCount(1, 'call.members')
        ->assertJsonPath('call.members.0.user.handle', $this->user->handle)
        ->assertJsonPath('call.signals', []);

    $event = Message::query()->where('event', OrgEvent::CallStarted)->sole();
    expect($event->user_id)->toBe($this->user->id)
        ->and($event->conversation_id)->toBe($this->org->id)
        ->and(resolve(OrgCallQuery::class)->isInOne($this->user))->toBeTrue();
});

it('joins the call going on without starting another', function (): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->travel(5)->seconds();

    $this->actingAs($this->member)->postJson(route('org-call.store', $this->org))
        ->assertCreated()
        ->assertJsonPath('call.started_at', now()->subSeconds(5)->getTimestampMs())
        ->assertJsonPath('call.members.0.user.handle', $this->user->handle)
        ->assertJsonPath('call.members.1.user.handle', $this->member->handle)
        ->assertJsonPath('call.members.1.joined_at', now()->getTimestampMs());

    expect(Message::query()->where('event', OrgEvent::CallStarted)->count())->toBe(1);
});

it('starts a new call once everyone in the last one left', function (): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->user)->deleteJson(route('org-call.destroy', $this->org))->assertNoContent();

    $this->actingAs($this->member)->postJson(route('org-call.store', $this->org))
        ->assertCreated()
        ->assertJsonCount(1, 'call.members');

    expect(Message::query()->where('event', OrgEvent::CallStarted)->pluck('user_id')->all())->toBe([$this->user->id, $this->member->id]);
});

it('hides the org call from a nonmember or a member with Remotes off', function (bool $isMember): void {
    $user = $isMember ? $this->member : User::factory()->withRemotes()->create();
    $user->update(['remotes_enabled' => ! $isMember]);

    $this->actingAs($user)->postJson(route('org-call.store', $this->org))->assertNotFound();
    $this->actingAs($user)->putJson(route('org-call.update', $this->org), ['signals' => []])->assertNotFound();
    $this->actingAs($user)->deleteJson(route('org-call.destroy', $this->org))->assertNotFound();
    $this->actingAs($user)->getJson(route('org-call-ice-server.index', $this->org))->assertNotFound();
})->with([true, false]);

it('hands the relay credentials to a member', function (): void {
    $this->actingAs($this->member)->getJson(route('org-call-ice-server.index', $this->org))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['ice_servers' => [], 'is_relay_only' => false]);
});

it('refuses joining while on another org call, but lets the user rejoin their own', function (): void {
    $elsewhere = Conversation::query()->findOrFail(createOrg($this->user, [$this->member], 'elsewhere'));
    $this->actingAs($this->user)->postJson(route('org-call.store', $elsewhere))->assertCreated();

    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))
        ->assertForbidden()
        ->assertJsonPath('message', "You're already on a call.");
    $this->actingAs($this->user)->postJson(route('org-call.store', $elsewhere))->assertCreated();
});

it('refuses joining a full call, except for someone already in it', function (): void {
    $members = followersOf($this->user, CreateOrgCall::MAX_MEMBERS);
    $org = Conversation::query()->findOrFail(createOrg($this->user, $members));
    foreach ($members as $member) {
        resolve(CreateOrgCall::class)->handle($member, $org);
    }

    $this->actingAs($this->user)->postJson(route('org-call.store', $org))
        ->assertForbidden()
        ->assertJsonPath('message', 'This call is full.');
    $this->actingAs($members[0])->postJson(route('org-call.store', $org))
        ->assertCreated()
        ->assertJsonCount(CreateOrgCall::MAX_MEMBERS, 'call.members');
});

it('keeps someone who got in past the full check out of a call that filled up meanwhile', function (): void {
    $members = followersOf($this->user, CreateOrgCall::MAX_MEMBERS);
    $org = Conversation::query()->findOrFail(createOrg($this->user, $members));
    foreach ($members as $member) {
        resolve(CreateOrgCall::class)->handle($member, $org);
    }

    resolve(CreateOrgCall::class)->handle($this->user, $org);

    $calls = resolve(OrgCallQuery::class);
    expect($calls->get($org)['members'])->toHaveCount(CreateOrgCall::MAX_MEMBERS)->not->toHaveKey($this->user->id)
        ->and($calls->isInOne($this->user))->toBeFalse()
        ->and($calls->forMember($this->user, $org, $calls->get($org)))->toBeNull();
});

it('swaps offers and answers between members on their check-ins', function (): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->member)->postJson(route('org-call.store', $this->org))->assertCreated();

    $this->actingAs($this->member)->putJson(route('org-call.update', $this->org), ['signals' => [
        $this->user->id => orgCallSignal(),
        $this->other->id => orgCallSignal(),
    ]])->assertOk()->assertJsonPath('call.signals', []);

    $this->actingAs($this->user)->putJson(route('org-call.update', $this->org), ['signals' => [$this->member->id => orgCallSignal('answer')]])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('call.is_ringing', true)
        ->assertJsonPath('call.signals', [$this->member->id => orgCallSignal()]);

    $this->actingAs($this->member)->putJson(route('org-call.update', $this->org), ['signals' => []])
        ->assertJsonPath('call.signals', [$this->user->id => orgCallSignal('answer')]);

    // Only members of the call get signals, and only encrypted.
    expect(Cache::get(CreateOrgCall::signalKey($this->org, $this->member->id, $this->other->id)))->toBeNull()
        ->and(Cache::get(CreateOrgCall::signalKey($this->org, $this->member->id, $this->user->id)))->toBeString()->not->toContain('offer');
});

it('validates the signals', function (array $signals): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))->assertCreated();

    $this->actingAs($this->user)->putJson(route('org-call.update', $this->org), $signals)->assertUnprocessable();
})->with([
    'missing' => [[]],
    'not a list of signals' => [['signals' => 'offer']],
    'unknown type' => [['signals' => ['someone' => ['id' => '1', 'type' => 'hello', 'sdp' => 'v=0']]]],
    'not an sdp' => [['signals' => ['someone' => ['id' => '1', 'type' => 'offer', 'sdp' => 'nope']]]],
    'extra keys' => [['signals' => ['someone' => [...orgCallSignal(), 'ip' => '10.0.0.1']]]],
    'too many' => [['signals' => array_fill_keys(range(1, CreateOrgCall::MAX_MEMBERS + 1), orgCallSignal())]],
]);

it('keeps the member in the call while they check in, and drops them once they stop', function (): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->member)->postJson(route('org-call.store', $this->org))->assertCreated();

    foreach (range(1, 6) as $checkIn) {
        $this->travel(CreateOrgCall::SECONDS - 1)->seconds();
        $this->actingAs($this->user)->putJson(route('org-call.update', $this->org), ['signals' => []])->assertOk();
    }

    $this->actingAs($this->user)->putJson(route('org-call.update', $this->org), ['signals' => []])
        ->assertJsonCount(1, 'call.members')
        ->assertJsonPath('call.is_ringing', false);
    $this->actingAs($this->member)->putJson(route('org-call.update', $this->org), ['signals' => []])
        ->assertForbidden()
        ->assertJsonPath('message', "You're no longer in this call.");
});

it('leaves the call with every signal the user swapped, and ends it with the last one out', function (): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->member)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->member)->putJson(route('org-call.update', $this->org), ['signals' => [$this->user->id => orgCallSignal()]])->assertOk();
    $this->actingAs($this->user)->putJson(route('org-call.update', $this->org), ['signals' => [$this->member->id => orgCallSignal('answer')]])->assertOk();

    $this->actingAs($this->member)->deleteJson(route('org-call.destroy', $this->org))->assertNoContent();

    expect(resolve(OrgCallQuery::class)->get($this->org)['members'])->toHaveKeys([$this->user->id])->toHaveCount(1)
        ->and(resolve(OrgCallQuery::class)->isInOne($this->member))->toBeFalse()
        ->and(Cache::get(CreateOrgCall::signalKey($this->org, $this->member->id, $this->user->id)))->toBeNull()
        ->and(Cache::get(CreateOrgCall::signalKey($this->org, $this->user->id, $this->member->id)))->toBeNull();

    $this->actingAs($this->user)->deleteJson(route('org-call.destroy', $this->org))->assertNoContent();

    expect(Cache::get(CreateOrgCall::key($this->org)))->toBeNull()
        ->and(resolve(OrgCallQuery::class)->ongoing($this->other))->toBe([]);
});

it('leaves the user in another org call when they leave one they are not in', function (): void {
    $elsewhere = Conversation::query()->findOrFail(createOrg($this->user, [$this->member], 'elsewhere'));
    $this->actingAs($this->user)->postJson(route('org-call.store', $elsewhere))->assertCreated();

    $this->actingAs($this->user)->deleteJson(route('org-call.destroy', $this->org))->assertNoContent();

    expect(Cache::get(CreateOrgCall::memberKey($this->user->id)))->toBe($elsewhere->id);
});

it('does not put back someone whose check-in crossed paths with leaving', function (): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->member)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->member)->deleteJson(route('org-call.destroy', $this->org))->assertNoContent();

    resolve(UpdateOrgCall::class)->handle($this->member, $this->org, [$this->user->id => orgCallSignal()]);

    expect(resolve(OrgCallQuery::class)->isInOne($this->member))->toBeFalse()
        ->and(Cache::get(CreateOrgCall::signalKey($this->org, $this->member->id, $this->user->id)))->toBeNull();
});

it('rejoins without the signals of an earlier session', function (): void {
    $this->actingAs($this->user)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->member)->postJson(route('org-call.store', $this->org))->assertCreated();
    $this->actingAs($this->member)->putJson(route('org-call.update', $this->org), ['signals' => [$this->user->id => orgCallSignal()]])->assertOk();

    $this->actingAs($this->member)->postJson(route('org-call.store', $this->org))->assertCreated();

    $this->actingAs($this->user)->putJson(route('org-call.update', $this->org), ['signals' => []])->assertJsonPath('call.signals', []);
});

it('requires authentication', function (string $method, string $route): void {
    auth()->logout();

    $this->json($method, route($route, $this->org))->assertUnauthorized();
})->with([
    ['POST', 'org-call.store'],
    ['PUT', 'org-call.update'],
    ['DELETE', 'org-call.destroy'],
    ['GET', 'org-call-ice-server.index'],
]);
