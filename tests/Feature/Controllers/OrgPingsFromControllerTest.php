<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Enums\PingsFrom;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserTimeout;

beforeEach(function (): void {
    $this->user = User::factory()->withRemotes()->create();
});

it('lets maintainers choose who can ping the org, and logs it', function (PingsFrom $from): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);

    $this->actingAs($this->user)->putJson(route('org-pings-from.update', $org), ['pings_from' => $from->value])->assertNoContent();

    $event = Message::query()->latest('id')->firstOrFail();
    expect(Conversation::query()->findOrFail($org)->pings_from)->toBe($from)
        ->and($event->event)->toBe(OrgEvent::PingsChanged)
        ->and($event->body)->toBe($from->value)
        ->and($event->user_id)->toBe($this->user->id);

    $this->actingAs($nuno)->getJson(route('org-message.index', $org))->assertOk()->assertJsonPath('pings_from', $from->value);
})->with([PingsFrom::Maintainers, PingsFrom::Nobody]);

it('logs nothing when the org already has that choice', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);

    $this->actingAs($this->user)->putJson(route('org-pings-from.update', $org), ['pings_from' => 'everyone'])->assertNoContent();

    expect(Conversation::query()->findOrFail($org)->pings_from)->toBe(PingsFrom::Everyone);
    $this->assertDatabaseCount('messages', 1);
});

it('keeps contributors and outsiders from choosing who can ping', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $this->user->following()->attach($mark);
    $org = createOrg($mark, [$this->user]);
    $elsewhere = createOrg($mark, followersOf($mark, 1));

    $this->actingAs($this->user)->putJson(route('org-pings-from.update', $org), ['pings_from' => 'nobody'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can choose who can ping the org.');
    $this->actingAs($this->user)->putJson(route('org-pings-from.update', $elsewhere), ['pings_from' => 'nobody'])->assertNotFound();

    expect(Conversation::query()->findOrFail($org)->pings_from)->toBe(PingsFrom::Everyone);
});

it('hides the org from a maintainer with Remotes disabled', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    $this->user->update(['remotes_enabled' => false]);

    $this->actingAs($this->user)->putJson(route('org-pings-from.update', $org), ['pings_from' => 'nobody'])->assertNotFound();
});

it('lets a maintainer in a timeout still turn pings off, as it posts nothing of theirs', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    UserTimeout::factory()->for($this->user)->create();

    $this->actingAs($this->user)->putJson(route('org-pings-from.update', $org), ['pings_from' => 'nobody'])->assertNoContent();

    expect(Conversation::query()->findOrFail($org)->pings_from)->toBe(PingsFrom::Nobody);
});

it('requires a known choice', function (mixed $from): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);

    $this->actingAs($this->user)->putJson(route('org-pings-from.update', $org), ['pings_from' => $from])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('pings_from');
})->with([null, 'contributors']);

it('returns 401 for a guest', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    auth()->logout();

    $this->putJson(route('org-pings-from.update', $org), ['pings_from' => 'nobody'])->assertUnauthorized();
});
