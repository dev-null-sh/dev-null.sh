<?php

declare(strict_types=1);

use App\Actions\CreateOrgCall;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgCallQuery;

it('starts, joins, exchanges signals and leaves an org call through signed requests', function (): void {
    $user = User::factory()->withRemotes()->create();
    [$member] = followersOf($user, 1);
    $org = Conversation::query()->findOrFail(createOrg($user, [$member]));
    $key = apiKeyPairFor($user);
    $memberKey = apiKeyPairFor($member);
    $signal = ['id' => 'offer-1', 'type' => 'offer', 'sdp' => "v=0\r\no=caller\r\n"];

    signedApiRequest($key, 'POST', route('api-org-call.store', $org))->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('call.members.0.user.id', $user->id);
    signedApiRequest($memberKey, 'POST', route('api-org-call.store', $org))->assertCreated()->assertJsonCount(2, 'call.members');

    $response = signedApiRequest($key, 'PUT', route('api-org-call.update', $org), ['signals' => [$member->id => $signal]]);

    $response->assertOk()->assertJsonPath('call.signals', []);
    expect(json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR)->call->signals)->toBeInstanceOf(stdClass::class);
    signedApiRequest($memberKey, 'PUT', route('api-org-call.update', $org), ['signals' => []])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('call.signals', [$user->id => $signal]);
    signedApiRequest($key, 'DELETE', route('api-org-call.destroy', $org))->assertNoContent();
    expect(resolve(OrgCallQuery::class)->isInOne($user))->toBeFalse();
});

it('hides call operations from nonmembers and members with Remotes off', function (bool $isMember): void {
    $owner = User::factory()->withRemotes()->create();
    [$member] = followersOf($owner, 1);
    $org = Conversation::query()->findOrFail(createOrg($owner, [$member]));
    $user = $isMember ? $member : User::factory()->withRemotes()->create();
    $user->update(['remotes_enabled' => ! $isMember]);

    $key = apiKeyPairFor($user);

    signedApiRequest($key, 'POST', route('api-org-call.store', $org))->assertNotFound();
    signedApiRequest($key, 'PUT', route('api-org-call.update', $org), ['signals' => []])->assertNotFound();
    signedApiRequest($key, 'DELETE', route('api-org-call.destroy', $org))->assertNotFound();
})->with([true, false]);

it('refuses joining another org call and checking in after leaving', function (): void {
    $user = User::factory()->withRemotes()->create();
    [$member] = followersOf($user, 1);
    $first = Conversation::query()->findOrFail(createOrg($user, [$member], 'first'));
    $second = Conversation::query()->findOrFail(createOrg($user, [$member], 'second'));
    $key = apiKeyPairFor($user);
    resolve(CreateOrgCall::class)->handle($user, $first);

    signedApiRequest($key, 'POST', route('api-org-call.store', $second))->assertForbidden()
        ->assertJsonPath('message', "You're already on a call.");
    signedApiRequest($key, 'PUT', route('api-org-call.update', $second), ['signals' => []])->assertForbidden()
        ->assertJsonPath('message', "You're no longer in this call.");
});

it('rejects invalid signals without delivering them', function (): void {
    $user = User::factory()->withRemotes()->create();
    [$member] = followersOf($user, 1);
    $org = Conversation::query()->findOrFail(createOrg($user, [$member]));
    resolve(CreateOrgCall::class)->handle($user, $org);

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-org-call.update', $org), ['signals' => [$member->id => ['id' => 'bad', 'type' => 'offer', 'sdp' => 'invalid']]])
        ->assertUnprocessable()->assertJsonValidationErrors('signals.'.$member->id.'.sdp');
});
