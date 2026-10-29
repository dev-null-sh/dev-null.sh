<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Enums\PingsFrom;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('lets maintainers choose who can ping the org, as the web does', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-pings-from.update', $org), ['pings_from' => 'maintainers'])->assertNoContent();

    expect(Conversation::query()->findOrFail($org)->pings_from)->toBe(PingsFrom::Maintainers)
        ->and(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::PingsChanged);

    signedApiRequest($this->keyPair, 'GET', route('api-org-message.index', $org))->assertOk()->assertJsonPath('data.pings_from', 'maintainers');

    signedApiRequest($this->keyPair, 'PUT', route('api-org-pings-from.update', $org), ['pings_from' => 'contributors'])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('pings_from');
});

it('keeps contributors and outsiders from choosing who can ping', function (): void {
    $mark = User::factory()->create();
    $this->user->following()->attach($mark);
    $org = createOrg($mark, [$this->user]);
    $elsewhere = createOrg($mark, followersOf($mark, 1));

    signedApiRequest($this->keyPair, 'PUT', route('api-org-pings-from.update', $org), ['pings_from' => 'nobody'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can choose who can ping the org.');
    signedApiRequest($this->keyPair, 'PUT', route('api-org-pings-from.update', $elsewhere), ['pings_from' => 'nobody'])->assertNotFound();

    expect(Conversation::query()->findOrFail($org)->pings_from)->toBe(PingsFrom::Everyone);
});

it('hides the org from a maintainer with Remotes disabled', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-pings-from.update', $org), ['pings_from' => 'nobody'])->assertNotFound();

    expect(Conversation::query()->findOrFail($org)->pings_from)->toBe(PingsFrom::Everyone);
});

it('rejects requests that are not signed', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    auth()->logout();

    $this->putJson(route('api-org-pings-from.update', $org), ['pings_from' => 'nobody'])->assertUnauthorized();
});
