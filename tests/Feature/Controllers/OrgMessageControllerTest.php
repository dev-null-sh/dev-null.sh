<?php

declare(strict_types=1);

use App\Models\User;

it('lets members push, read, and see who pulled', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno, $taylor]);
    $message = pushToOrg($mark, $org, 'ship it?');

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.ahead', 1)->assertJsonPath('unread', 1);
    $this->putJson(route('org-read.update', $org), ['through' => $message->id])->assertNoContent();
    $this->getJson(route('inbox.index'))->assertJsonPath('unread', 0);

    $members = collect($this->actingAs($mark)->getJson(route('org-message.index', $org))->assertOk()->json('members'))
        ->mapWithKeys(fn (array $member): array => [$member['user']['username'] => $member['read_through_id']]);

    expect($members[$nuno->username])->toBe($message->id)
        ->and($members[$taylor->username])->toBeLessThan($message->id);
});

it('hides pulls from people who do not share them', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $nuno->update(['pull_receipts' => false]);
    $org = createOrg($mark, [$nuno]);
    $message = pushToOrg($mark, $org);
    $this->actingAs($nuno)->putJson(route('org-read.update', $org), ['through' => $message->id]);

    $this->actingAs($mark)->getJson(route('org-message.index', $org))->assertJsonPath('members.1.read_through_id', 0);
});

it('shows who is typing', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs($nuno)->putJson(route('org-typing.update', $org))->assertNoContent();

    $this->actingAs($mark)->getJson(route('org-message.index', $org))->assertJsonPath('typing', [$nuno->username]);
    $this->actingAs($nuno)->getJson(route('org-message.index', $org))->assertJsonPath('typing', []);
});

it('stashes an org for one member', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs($nuno)->postJson(route('org-archive.store', $org))->assertNoContent();
    $this->getJson(route('inbox.index', ['tab' => 'stashed']))->assertJsonCount(1, 'conversations');
    $this->deleteJson(route('org-archive.destroy', $org))->assertNoContent();
    $this->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations');
});

it('finds orgs by name or by a member', function (string $term): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $nuno->update(['username' => 'nunomaduro']);
    createOrg($mark, [$nuno], name: 'polling crew');

    $this->actingAs($mark)->getJson(route('inbox.index', ['q' => $term]))->assertJsonCount(1, 'conversations');
})->with(['polling', 'nunomad']);

it('keeps non-members out', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs(User::factory()->create())->postJson(route('org-message.store', $org), ['body' => 'hi', 'client_id' => fake()->uuid()])->assertNotFound();
});
