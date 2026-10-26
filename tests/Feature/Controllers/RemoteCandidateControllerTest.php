<?php

declare(strict_types=1);

use App\Models\User;

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
