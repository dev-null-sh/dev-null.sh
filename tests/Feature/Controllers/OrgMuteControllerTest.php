<?php

declare(strict_types=1);

use App\Models\User;

it('mutes an org for one member', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno, $taylor]);

    $this->actingAs($nuno)->postJson(route('org-mute.store', $org))->assertNoContent();
    pushToOrg($mark, $org, 'ship it?');

    $this->actingAs($nuno)->getJson(route('inbox.index'))
        ->assertJsonPath('conversations.0.is_muted', true)
        ->assertJsonPath('conversations.0.ahead', 1)
        ->assertJsonPath('unread', 0);
    $this->getJson(route('org-message.index', $org))->assertJsonPath('is_muted', true);

    $this->actingAs($taylor)->getJson(route('inbox.index'))->assertJsonPath('unread', 1);
});

it('unmutes an org', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    pushToOrg($mark, $org);
    $this->actingAs($nuno)->postJson(route('org-mute.store', $org));

    $this->deleteJson(route('org-mute.destroy', $org))->assertNoContent();

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.is_muted', false)->assertJsonPath('unread', 1);
});

it('keeps non-members out', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs(User::factory()->create())->postJson(route('org-mute.store', $org))->assertNotFound();
    $this->deleteJson(route('org-mute.destroy', $org))->assertNotFound();
});
