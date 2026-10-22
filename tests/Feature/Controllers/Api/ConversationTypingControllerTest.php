<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('shows the user typing to the partner for a few seconds', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user, 'what do the dots mean?');

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-typing.update', $nuno))->assertNoContent();

    $this->actingAs($nuno)->getJson(route('conversation-message.index', $this->user))->assertJsonPath('partner_typing', true);

    $this->travel(6)->seconds();

    $this->getJson(route('conversation-message.index', $this->user))->assertJsonPath('partner_typing', false);
});

it('has nobody to tell before the conversation starts', function (): void {
    $nuno = User::factory()->withRemotes()->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-typing.update', $nuno))->assertNoContent();

    expect(Conversation::query()->count())->toBe(0);
});

it('hides typing from people the user cannot message', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $this->user);
    $this->user->blocking()->attach($nuno);

    signedApiRequest($this->keyPair, 'PUT', route('api-conversation-typing.update', $nuno))->assertNotFound();
});

it('shows the user typing in an org', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-typing.update', $org))->assertNoContent();

    $this->actingAs($nuno)->getJson(route('org-message.index', $org))->assertJsonPath('typing', [$this->user->username]);
});

it('keeps people out of orgs they are not in', function (): void {
    $maintainer = User::factory()->withRemotes()->create();
    [$nuno] = followersOf($maintainer, 1);
    $org = createOrg($maintainer, [$nuno]);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-typing.update', $org))->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $nuno = User::factory()->withRemotes()->create();

    $this->putJson(route('api-conversation-typing.update', $nuno))->assertUnauthorized();
});
