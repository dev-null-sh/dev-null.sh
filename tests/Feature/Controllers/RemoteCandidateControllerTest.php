<?php

declare(strict_types=1);

use App\Actions\CreateConversation;
use App\Enums\RemoteStatus;
use App\Models\User;

it('only shares into conversations that can accept a message', function (bool $outgoing, bool $dropped, string $reason): void {
    $viewer = User::factory()->create();
    $partner = User::factory()->create(['handle' => 'share_partner']);
    $conversation = resolve(CreateConversation::class)->handle($outgoing ? $viewer : $partner, $outgoing ? $partner : $viewer);

    if ($dropped) {
        $conversation->participants()->where('user_id', $partner->id)->update(['status' => RemoteStatus::Dropped]);
    }

    $this->actingAs($viewer)->getJson(route('remote-candidate.index', ['q' => 'share_partner', 'for' => 'share']))
        ->assertOk()->assertJsonPath('candidates.0.reason', $reason);

    $this->getJson(route('remote-candidate.index', ['q' => 'share_partner', 'for' => 'remote']))
        ->assertOk()->assertJsonPath('candidates.0.reason', null);
})->with([
    'incoming request' => [false, false, 'merge their request first'],
    'outgoing request' => [true, false, 'your request is waiting to be merged'],
    'dropped request stays private' => [true, true, 'your request is waiting to be merged'],
]);

it('can share a first message when requests are enabled', function (): void {
    config(['remotes.requests' => true]);
    $viewer = User::factory()->create();
    User::factory()->create(['handle' => 'share_partner']);

    $this->actingAs($viewer)->getJson(route('remote-candidate.index', ['q' => 'share_partner', 'for' => 'share']))
        ->assertOk()->assertJsonPath('candidates.0.reason', null);
});

it('says who can be messaged and why not', function (): void {
    $mark = User::factory()->create();
    $follower = User::factory()->create(['handle' => 'ana_follows']);
    $follower->following()->attach($mark);
    User::factory()->create(['handle' => 'ana_stranger']);
    $off = User::factory()->create(['handle' => 'ana_off', 'remotes_enabled' => false]);
    $off->following()->attach($mark);

    $reasons = collect($this->actingAs($mark)->getJson(route('remote-candidate.index', ['q' => 'ana']))->assertOk()->json('candidates'))
        ->mapWithKeys(fn (array $candidate): array => [$candidate['user']['handle'] => $candidate['reason']]);

    expect($reasons->all())->toEqualCanonicalizing([
        'ana_follows' => null,
        'ana_stranger' => "doesn't follow you",
        'ana_off' => 'has remotes off',
    ]);
});

it('says who can join an org and why not', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $nuno->profile->update(['handle' => 'ana_member']);
    $org = createOrg($mark, [$nuno]);
    $follower = User::factory()->create(['handle' => 'ana_follows']);
    $follower->following()->attach($mark);

    $reasons = fn (array $query): array => collect($this->actingAs($mark)->getJson(route('remote-candidate.index', ['q' => 'ana', ...$query]))->assertOk()->json('candidates'))
        ->mapWithKeys(fn (array $candidate): array => [$candidate['user']['handle'] => $candidate['reason']])
        ->all();

    expect($reasons(['org' => $org]))->toEqualCanonicalizing(['ana_member' => 'already in the org', 'ana_follows' => null])
        ->and($reasons(['for' => 'org']))->toEqualCanonicalizing(['ana_member' => null, 'ana_follows' => null]);

    $this->actingAs(User::factory()->create())->getJson(route('remote-candidate.index', ['q' => 'ana', 'org' => $org]))->assertNotFound();
});
