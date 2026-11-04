<?php

declare(strict_types=1);

use App\Actions\CreateConversationCall;
use App\Actions\CreateOrgCall;
use App\Actions\UpdateOrgCall;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
    $this->user = User::factory()->withRemotes()->create();
});

it('shows the call ringing the user with its caller and offer', function (bool $isMuted): void {
    $caller = User::factory()->withRemotes()->create();
    $conversation = sendChatMessage($caller, $this->user)->conversation;
    ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $this->user->id)
        ->update(['muted_until_at' => $isMuted ? now()->addHour() : now()->subMinute()]);
    resolve(CreateConversationCall::class)->handle($caller, $this->user, "v=0\r\n");

    $this->actingAs($this->user)->getJson(route('call.show'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('call.partner.handle', $caller->handle)
        ->assertJsonPath('call.sdp', "v=0\r\n")
        ->assertJsonPath('call.is_muted', $isMuted)
        ->assertJsonPath('org_calls', []);
})->with([true, false]);

it('shows no call once it is answered, or from someone the user cannot message', function (bool $isAnswered): void {
    $caller = User::factory()->withRemotes()->create();
    sendChatMessage($caller, $this->user);
    $call = resolve(CreateConversationCall::class);
    $call->handle($caller, $this->user, 'v=0');

    if ($isAnswered) {
        $this->actingAs($this->user)->putJson(route('conversation-call.update', $caller), ['sdp' => 'v=0'])->assertNoContent();
    } else {
        $this->user->blocking()->attach($caller);
    }

    $this->actingAs($this->user)->getJson(route('call.show'))->assertJsonPath('call', null);
})->with([true, false]);

it('lists the calls going on in the user orgs, ringing while they are new', function (): void {
    [$member, $other] = followersOf($this->user, 2);
    $org = Conversation::query()->findOrFail(createOrg($this->user, [$member, $other]));
    Conversation::query()->findOrFail(createOrg($this->user, [$other], 'quiet'));
    resolve(CreateOrgCall::class)->handle($member, $org);

    $this->actingAs($this->user)->getJson(route('call.show'))
        ->assertJsonCount(1, 'org_calls')
        ->assertJsonPath('org_calls.0.org', ['id' => $org->id, 'name' => 'dev/null core'])
        ->assertJsonPath('org_calls.0.is_ringing', true)
        ->assertJsonPath('org_calls.0.is_muted', false)
        ->assertJsonPath('org_calls.0.started_at', now()->getTimestampMs())
        ->assertJsonPath('org_calls.0.members.0.user.handle', $member->handle);

    // The member keeps checking in while the call stops ringing.
    foreach (range(1, 5) as $checkIn) {
        $this->travel(9)->seconds();
        resolve(UpdateOrgCall::class)->handle($member, $org, []);
    }

    resolve(CreateOrgCall::class)->handle($other, $org);

    $this->actingAs($this->user)->getJson(route('call.show'))
        ->assertJsonPath('org_calls.0.is_ringing', false)
        ->assertJsonPath('org_calls.0.members.1.user.handle', $other->handle);
});

it('marks the org calls of a muted org', function (): void {
    [$member] = followersOf($this->user, 1);
    $org = Conversation::query()->findOrFail(createOrg($this->user, [$member]));
    $this->actingAs($this->user)->putJson(route('org-mute.update', $org), ['duration' => 'hour'])->assertSuccessful();
    resolve(CreateOrgCall::class)->handle($member, $org);

    $this->actingAs($this->user)->getJson(route('call.show'))->assertJsonPath('org_calls.0.is_muted', true);
});

it('leaves out an org call once nobody checks in to it', function (): void {
    [$member] = followersOf($this->user, 1);
    $org = Conversation::query()->findOrFail(createOrg($this->user, [$member]));
    resolve(CreateOrgCall::class)->handle($member, $org);
    $this->travel(CreateOrgCall::SECONDS + 1)->seconds();

    $this->actingAs($this->user)->getJson(route('call.show'))->assertJsonPath('org_calls', []);
});

it('lists no org calls to someone with Remotes off', function (): void {
    [$member] = followersOf($this->user, 1);
    $org = Conversation::query()->findOrFail(createOrg($this->user, [$member]));
    resolve(CreateOrgCall::class)->handle($member, $org);
    $this->user->update(['remotes_enabled' => false]);

    $this->actingAs($this->user)->getJson(route('call.show'))->assertJsonPath('org_calls', []);
});

it('requires authentication', function (): void {
    $this->getJson(route('call.show'))->assertUnauthorized();
});
