<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('says who can be messaged and why not', function (): void {
    $mark = User::factory()->create();
    $follower = User::factory()->create(['username' => 'ana_follows']);
    $follower->following()->attach($mark);
    User::factory()->create(['username' => 'ana_stranger']);
    $off = User::factory()->create(['username' => 'ana_off', 'remotes_enabled' => false]);
    $off->following()->attach($mark);

    $reasons = collect($this->actingAs($mark)->getJson(route('remote-candidate.index', ['q' => 'ana']))->assertOk()->json('candidates'))
        ->mapWithKeys(fn (array $candidate): array => [$candidate['user']['username'] => $candidate['reason']]);

    expect($reasons->all())->toEqualCanonicalizing([
        'ana_follows' => null,
        'ana_stranger' => "doesn't follow you",
        'ana_off' => 'has remotes off',
    ]);
});

it('resolves the access of every candidate in two queries', function (): void {
    $mark = User::factory()->create();
    foreach (range(1, 3) as $n) {
        User::factory()->create(['username' => "ana_follows_{$n}"])->following()->attach($mark);
        User::factory()->create(['username' => "ana_stranger_{$n}"]);
    }

    $open = User::factory()->create(['username' => 'ana_open']);
    $open->following()->attach($mark);
    sendChatMessage($mark, $open);
    User::factory()->create(['username' => 'ana_off', 'remotes_enabled' => false]);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $candidates = collect($this->actingAs($mark)->getJson(route('remote-candidate.index', ['q' => 'ana']))->assertOk()->json('candidates'));

    $tables = collect(DB::getQueryLog())->map(fn (array $query): string => str_contains($query['query'], 'from "conversation_participants"') ? 'participants' : (str_contains($query['query'], 'from "follows"') ? 'follows' : 'other'))->countBy();

    expect($candidates)->toHaveCount(8)
        ->and($candidates->pluck('reason', 'user.username')->filter()->all())->toEqualCanonicalizing([
            'ana_stranger_1' => "doesn't follow you",
            'ana_stranger_2' => "doesn't follow you",
            'ana_stranger_3' => "doesn't follow you",
            'ana_off' => 'has remotes off',
        ])
        ->and($tables->get('participants'))->toBe(1)
        ->and($tables->get('follows'))->toBe(1);

    DB::disableQueryLog();
});

it('says who can join an org and why not', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $nuno->update(['username' => 'ana_member']);
    $org = createOrg($mark, [$nuno]);
    $follower = User::factory()->create(['username' => 'ana_follows']);
    $follower->following()->attach($mark);

    $reasons = fn (array $query): array => collect($this->actingAs($mark)->getJson(route('remote-candidate.index', ['q' => 'ana', ...$query]))->assertOk()->json('candidates'))
        ->mapWithKeys(fn (array $candidate): array => [$candidate['user']['username'] => $candidate['reason']])
        ->all();

    expect($reasons(['org' => $org]))->toEqualCanonicalizing(['ana_member' => 'already in the org', 'ana_follows' => null])
        ->and($reasons(['for' => 'org']))->toEqualCanonicalizing(['ana_member' => null, 'ana_follows' => null]);

    $this->actingAs(User::factory()->create())->getJson(route('remote-candidate.index', ['q' => 'ana', 'org' => $org]))->assertNotFound();
});
