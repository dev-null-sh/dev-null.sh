<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('clears the mentions asked for and answers with the ones left, as the web does', function (): void {
    [$nuno, $taylor] = followersOf($this->user, 2);
    $org = createOrg($this->user, [$nuno, $taylor]);
    $review = pushToOrg($nuno, $org, "@{$this->user->handle} can you review the deploy script?");
    $schema = pushToOrg($taylor, $org, "thoughts on the schema @{$this->user->handle}?");
    $cache = pushToOrg($nuno, $org, "@{$this->user->handle} the cache is up");

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-mention.destroy', $org), ['message_ids' => [$review->id, $cache->id]])
        ->assertOk()
        ->assertJsonPath('mentions.*.id', [$schema->id])
        ->assertJsonPath('mentions.0.user.handle', $taylor->handle)
        ->assertJsonPath('mentions_count', 1);

    signedApiRequest($this->keyPair, 'GET', route('api-org-message.index', $org))
        ->assertJsonPath('data.mentions.*.id', [$schema->id])
        ->assertJsonPath('data.mentions_count', 1);
});

it('clears every mention in one org at once, leaving the other orgs', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $core = createOrg($this->user, [$nuno]);
    $infra = createOrg($this->user, [$nuno], 'infra');
    pushToOrg($nuno, $core, "@{$this->user->handle} one");
    pushToOrg($nuno, $core, "@{$this->user->handle} two");
    $cache = pushToOrg($nuno, $infra, "@{$this->user->handle} the cache is up");

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-mention.destroy', $core))
        ->assertOk()
        ->assertJsonPath('mentions', [])
        ->assertJsonPath('mentions_count', 0);

    signedApiRequest($this->keyPair, 'GET', route('api-org-message.index', $infra))
        ->assertJsonPath('data.mentions.*.id', [$cache->id]);
});

it('refuses an empty list or a single mention instead of clearing them all', function (array $data, string $field): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    $message = pushToOrg($nuno, $org, "@{$this->user->handle} review?");

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-mention.destroy', $org), $data)
        ->assertJsonValidationErrors($field);

    signedApiRequest($this->keyPair, 'GET', route('api-org-message.index', $org))
        ->assertJsonPath('data.mentions.*.id', [$message->id]);
})->with([
    'an empty list' => [['message_ids' => []], 'message_ids'],
    'a single mention' => [['message_id' => 1], 'message_id'],
]);

it('is not there while the user has Remotes off', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    pushToOrg($nuno, $org, "@{$this->user->handle} review?");

    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-mention.destroy', $org))->assertNotFound();

    expect($this->user->mentions()->count())->toBe(1);
});

it('keeps non-members out', function (): void {
    $maintainer = User::factory()->create();
    [$nuno] = followersOf($maintainer, 1);
    $org = createOrg($maintainer, [$nuno]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-mention.destroy', $org))->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $maintainer = User::factory()->create();
    [$nuno] = followersOf($maintainer, 1);

    $this->deleteJson(route('api-org-mention.destroy', createOrg($maintainer, [$nuno])))->assertUnauthorized();
});
