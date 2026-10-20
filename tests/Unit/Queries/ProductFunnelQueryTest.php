<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Actions\CreateWorldReply;
use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\User;
use App\Queries\ProductFunnelQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));

    $this->funnel = fn (string $from = '2026-10-01', string $to = '2026-10-19', bool $isAllTime = false): array => resolve(ProductFunnelQuery::class)
        ->get(CarbonImmutable::parse($from), CarbonImmutable::parse($to), $isAllTime);

    $this->star = fn (User $user, Commit $commit, string $at) => $commit->stars()->attach($user, ['created_at' => $at, 'updated_at' => $at]);

    $this->trackSince = fn (string $date): bool => DB::table('active_user_counts')
        ->insert(['seen_at' => $date, 'count' => 0]);
});

it('counts the signups of the range, leaving the bots out', function (): void {
    User::factory()->create(['created_at' => '2026-10-01 00:00:00']);
    User::factory()->create(['created_at' => '2026-10-19 11:00:00']);
    User::factory()->create(['created_at' => '2026-09-30 23:59:59']);
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-02 00:00:00']);
    User::factory()->create(['github_id' => CreateWorldReply::GITHUB_ID, 'created_at' => '2026-10-02 00:00:00']);

    expect(($this->funnel)()['signups'])->toBe(2);
});

it('activates readers who starred or saved commits by three other people within twelve hours', function (): void {
    $reader = User::factory()->create(['created_at' => '2026-10-10 08:00:00']);
    $authors = User::factory()->count(3)->create(['created_at' => '2026-09-01 00:00:00']);
    $commits = $authors->map(fn (User $author): Commit => Commit::factory()->for($author)->create());

    ($this->star)($reader, $commits[0], '2026-10-10 09:00:00');
    ($this->star)($reader, $commits[1], '2026-10-10 19:59:59');
    Bookmark::factory()->create(['user_id' => $reader->id, 'commit_id' => $commits[2]->id, 'created_at' => '2026-10-10 20:00:00']);

    expect(($this->funnel)())
        ->activated->toBe(1)
        ->activation_eligible->toBe(1)
        ->rates->activation->toBe(1.0);
});

it('does not activate readers who found fewer than three people in time', function (): void {
    $reader = User::factory()->create(['created_at' => '2026-10-10 08:00:00']);
    $author = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-09-01 00:00:00']);
    $late = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);

    ($this->star)($reader, Commit::factory()->for($author)->create(), '2026-10-10 09:00:00');
    ($this->star)($reader, Commit::factory()->for($author)->create(), '2026-10-10 09:00:00');
    ($this->star)($reader, Commit::factory()->for($tom)->create(), '2026-10-10 09:00:00');
    ($this->star)($reader, Commit::factory()->for($late)->create(), '2026-10-10 20:00:01');
    Bookmark::factory()->create(['user_id' => $reader->id, 'commit_id' => Commit::factory()->for($reader)->create()->id, 'created_at' => '2026-10-10 09:00:00']);

    expect(($this->funnel)())
        ->activated->toBe(0)
        ->activation_eligible->toBe(1);
});

it('activates authors whose post got a reply, a quote or a star from someone else within twelve hours', function (string $reaction): void {
    $author = User::factory()->create(['created_at' => '2026-10-10 08:00:00']);
    $other = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $commit = Commit::factory()->for($author)->create(['created_at' => '2026-10-10 09:00:00']);

    match ($reaction) {
        'reply' => Commit::factory()->for($other)->for($commit, 'parent')->create(['created_at' => '2026-10-10 10:00:00']),
        'quote' => Commit::factory()->for($other)->create(['quoted_commit_id' => $commit->id, 'created_at' => '2026-10-10 10:00:00']),
        'star' => ($this->star)($other, $commit, '2026-10-10 10:00:00'),
    };

    expect(($this->funnel)()['activated'])->toBe(1);
})->with(['reply', 'quote', 'star']);

it('does not activate authors whose reactions came from themselves, a bot, too late, or are not approved', function (): void {
    $author = User::factory()->create(['created_at' => '2026-10-10 08:00:00']);
    $other = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-09-01 00:00:00']);
    $commit = Commit::factory()->for($author)->create(['created_at' => '2026-10-10 09:00:00']);

    Commit::factory()->for($author)->for($commit, 'parent')->create(['created_at' => '2026-10-10 10:00:00']);
    Commit::factory()->for($tom)->for($commit, 'parent')->create(['created_at' => '2026-10-10 10:00:00']);
    Commit::factory()->for($other)->for($commit, 'parent')->pending()->create(['created_at' => '2026-10-10 10:00:00']);
    Commit::factory()->for($other)->for($commit, 'parent')->create(['created_at' => '2026-10-10 20:00:01']);
    ($this->star)($tom, $commit, '2026-10-10 10:00:00');
    ($this->star)($other, Commit::factory()->for($author)->create(['created_at' => '2026-10-10 20:30:00']), '2026-10-10 20:40:00');

    expect(($this->funnel)()['activated'])->toBe(0);
});

it('waits twelve hours before counting a signup towards activation', function (): void {
    User::factory()->create(['created_at' => '2026-10-19 00:00:00']);
    User::factory()->create(['created_at' => '2026-10-19 00:00:01']);

    expect(($this->funnel)())
        ->signups->toBe(2)
        ->activation_eligible->toBe(1)
        ->rates->activation->toBe(0.0);
});

it('measures retention from day 14 once signups got through it after tracking started', function (): void {
    ($this->trackSince)('2026-10-01');

    User::factory()->create(['created_at' => '2026-10-01 10:00:00', 'last_seen_at' => '2026-10-15']);
    User::factory()->create(['created_at' => '2026-10-01 10:00:00', 'last_seen_at' => '2026-10-14']);
    User::factory()->create(['created_at' => '2026-10-04 10:00:00', 'last_seen_at' => '2026-10-19']);
    User::factory()->create(['created_at' => '2026-10-05 10:00:00', 'last_seen_at' => '2026-10-19']);

    expect(($this->funnel)())
        ->retention_horizon->toBe(14)
        ->retention_full_on->toBeNull()
        ->retained->toBe(2)
        ->retention_eligible->toBe(3)
        ->rates->retention->toBe(2 / 3);
});

it('falls back to day 7, then day 1, until signups reach day 14 after tracking started', function (string $trackedSince, string $signedUpAt, int $horizon, string $fullOn): void {
    ($this->trackSince)($trackedSince);

    User::factory()->create(['created_at' => $signedUpAt, 'last_seen_at' => '2026-10-19']);

    expect(($this->funnel)('2026-10-10', '2026-10-19'))
        ->retention_horizon->toBe($horizon)
        ->retention_full_on->toBe($fullOn)
        ->retained->toBe(1)
        ->retention_eligible->toBe(1);
})->with([
    'day 7' => ['2026-10-12', '2026-10-10 10:00:00', 7, '2026-10-25'],
    'day 1' => ['2026-10-18', '2026-10-17 10:00:00', 1, '2026-11-01'],
]);

it('has no retention before tracking started, or before signups got through their first day', function (): void {
    User::factory()->create(['created_at' => '2026-10-10 10:00:00', 'last_seen_at' => '2026-10-19']);

    expect(($this->funnel)())
        ->retention_horizon->toBeNull()
        ->retention_full_on->toBeNull()
        ->rates->retention->toBeNull();

    ($this->trackSince)('2026-10-19');
    User::query()->update(['created_at' => '2026-10-18 08:00:00']);

    expect(($this->funnel)())
        ->retention_horizon->toBeNull()
        ->retention_full_on->toBe('2026-11-02');
});

it('counts visitors and conversion from Fathom events', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);

    Http::fake(fn (Request $request) => Http::response([match ($request->data()['entity_name'] ?? null) {
        'guest-visit' => ['unique_conversions' => '400'],
        'signup' => ['unique_conversions' => '3'],
    }]));

    User::factory()->count(4)->create(['created_at' => '2026-10-02 00:00:00']);

    expect(($this->funnel)())
        ->visitors->toBe(400)
        ->signups->toBe(4)
        ->rates->conversion->toBe(0.0075);
});

it('has no visitors or conversion without Fathom', function (): void {
    User::factory()->create(['created_at' => '2026-10-02 00:00:00']);

    expect(($this->funnel)())
        ->visitors->toBeNull()
        ->rates->conversion->toBeNull();
});

it('compares the last seven days with the seven before when the range is all time', function (): void {
    ($this->trackSince)('2026-09-01');

    $old = User::factory()->create(['created_at' => '2026-09-01 10:00:00']);
    $other = User::factory()->create(['created_at' => '2026-09-01 10:00:00']);

    $before = User::factory()->create(['created_at' => '2026-10-07 10:00:00', 'last_seen_at' => '2026-10-19']);
    User::factory()->create(['created_at' => '2026-10-08 10:00:00']);
    $now = User::factory()->create(['created_at' => '2026-10-14 10:00:00']);
    User::factory()->create(['created_at' => '2026-10-15 10:00:00']);

    ($this->star)($now, Commit::factory()->for($old)->create(), '2026-10-14 11:00:00');
    ($this->star)($now, Commit::factory()->for($other)->create(), '2026-10-14 11:00:00');
    ($this->star)($now, Commit::factory()->for($before)->create(), '2026-10-14 11:00:00');

    expect(($this->funnel)('2026-09-01', '2026-10-19', isAllTime: true)['changes'])->toBe([
        'conversion' => null,
        'activation' => 0.5,
        'retention' => null,
    ]);
});

it('compares a range with as many days before it, at the same retention horizon', function (): void {
    ($this->trackSince)('2026-09-01');

    User::factory()->create(['created_at' => '2026-09-20 10:00:00', 'last_seen_at' => '2026-10-19']);
    User::factory()->create(['created_at' => '2026-09-20 10:00:00']);
    User::factory()->create(['created_at' => '2026-09-27 10:00:00', 'last_seen_at' => '2026-10-19']);

    expect(($this->funnel)('2026-09-27', '2026-10-03'))
        ->retention_horizon->toBe(14)
        ->changes->retention->toBe(0.5);
});

it('has no changes until the period before started after the launch', function (): void {
    ($this->trackSince)('2026-09-01');

    User::factory()->create(['created_at' => '2026-10-15 10:00:00']);

    expect(($this->funnel)('2026-10-15', '2026-10-19', isAllTime: true)['changes'])
        ->toBe(['conversion' => null, 'activation' => null, 'retention' => null]);
});

it('stops asking Fathom once it has no answer', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);
    ($this->trackSince)('2026-09-01');

    Http::fake(['api.usefathom.com/*' => Http::response(status: 500)]);

    User::factory()->create(['created_at' => '2026-09-01 10:00:00']);

    expect(($this->funnel)('2026-09-01', '2026-10-19', isAllTime: true))
        ->visitors->toBeNull()
        ->rates->conversion->toBeNull();

    Http::assertSentCount(1);
});
