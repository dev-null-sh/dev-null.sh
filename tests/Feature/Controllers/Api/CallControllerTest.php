<?php

declare(strict_types=1);

use App\Actions\CreateConversationCall;
use App\Actions\CreateOrgCall;
use App\Models\Conversation;
use App\Models\User;

it('shows the incoming caller and ongoing org calls with public member profiles', function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
    $user = User::factory()->withRemotes()->create();
    $caller = User::factory()->withRemotes()->create();
    $key = apiKeyPairFor($user);

    signedApiRequest($key, 'GET', route('api-call.show'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['call' => null, 'org_calls' => []]);
    sendChatMessage($caller, $user);
    resolve(CreateConversationCall::class)->handle($caller, $user, 'v=0 incoming');
    [$member] = followersOf($user, 1);
    $org = Conversation::query()->findOrFail(createOrg($user, [$member]));
    resolve(CreateOrgCall::class)->handle($member, $org);

    signedApiRequest($key, 'GET', route('api-call.show'))->assertOk()
        ->assertJsonPath('call.partner.id', $caller->id)
        ->assertJsonPath('call.sdp', 'v=0 incoming')
        ->assertJsonPath('org_calls.0.members.0.user.id', $member->id)
        ->assertJsonPath('org_calls.0.members.0.joined_at', now()->getTimestampMs())
        ->assertJsonMissingPath('call.partner.email')
        ->assertJsonMissingPath('org_calls.0.members.0.user.email');
});
