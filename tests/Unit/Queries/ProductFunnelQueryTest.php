<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Actions\CreateWorldReply;
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

    $this->follow = fn (User $follower, User $followed, string $at) => $follower->following()->attach($followed, ['created_at' => $at, 'updated_at' => $at]);

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

it('activates readers who starred commits by two other people within twelve hours', function (): void {
    $reader = User::factory()->create(['created_at' => '2026-10-10 08:00:00']);
    $authors = User::factory()->count(2)->create(['created_at' => '2026-09-01 00:00:00']);
    $commits = $authors->map(fn (User $author): Commit => Commit::factory()->for($author)->create());

    ($this->star)($reader, $commits[0], '2026-10-10 09:00:00');
    ($this->star)($reader, $commits[1], '2026-10-10 20:00:00');

    expect(($this->funnel)())
        ->activated->toBe(1)
        ->activation_eligible->toBe(1)
        ->rates->activation->toBe(1.0);
});

it('tells activation for each signup day, the days whose last signups are still within their 12 hours as incomplete', function (): void {
    $other = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $author = User::factory()->create(['created_at' => '2026-10-17 08:00:00']);
    User::factory()->create(['created_at' => '2026-10-17 09:00:00']);
    User::factory()->create(['created_at' => '2026-10-18 23:00:00']);
    User::factory()->create(['created_at' => '2026-10-19 00:00:00']);
    User::factory()->create(['created_at' => '2026-10-19 00:00:01']);

    ($this->star)($other, Commit::factory()->for($author)->create(['created_at' => '2026-10-17 09:00:00']), '2026-10-17 10:00:00');

    expect(($this->funnel)()['activation_by_day'])->toBe([
        ['date' => '2026-10-17', 'activated' => 1, 'eligible' => 2, 'complete' => true],
        ['date' => '2026-10-18', 'activated' => 0, 'eligible' => 1, 'complete' => true],
        ['date' => '2026-10-19', 'activated' => 0, 'eligible' => 1, 'complete' => false],
    ]);
});

it('does not activate readers who found fewer than two people in time', function (): void {
    $reader = User::factory()->create(['created_at' => '2026-10-10 08:00:00']);
    $author = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-09-01 00:00:00']);
    $late = User::factory()->create(['created_at' => '2026-09-01 00:00:00']);

    ($this->star)($reader, Commit::factory()->for($author)->create(), '2026-10-10 09:00:00');
    ($this->star)($reader, Commit::factory()->for($author)->create(), '2026-10-10 09:00:00');
    ($this->star)($reader, Commit::factory()->for($tom)->create(), '2026-10-10 09:00:00');
    ($this->star)($reader, Commit::factory()->for($late)->create(), '2026-10-10 20:00:01');

    expect(($this->funnel)())
        ->activated->toBe(0)
        ->activation_eligible->toBe(1);
});

it('activates people who followed ten others themselves within twelve hours', function (): void {
    $newcomer = User::factory()->create(['created_at' => '2026-10-10 08:00:00']);

    User::factory()->count(10)->create(['created_at' => '2026-09-01 00:00:00'])
        ->each(fn (User $followed, int $index) => ($this->follow)($newcomer, $followed, $index === 9 ? '2026-10-10 20:00:00' : '2026-10-10 09:00:00'));

    expect(($this->funnel)()['activated'])->toBe(1);
});

it('does not activate people whose follows were imported from GitHub, went to a bot, came too late, or were fewer than ten', function (): void {
    $newcomer = User::factory()->create(['created_at' => '2026-10-10 08:00:00', 'github_follows_imported_at' => '2026-10-10 08:00:05']);
    $tom = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-09-01 00:00:00']);
    $imported = User::factory()->count(5)->create(['created_at' => '2026-09-01 00:00:00']);
    $later = User::factory()->create(['created_at' => '2026-10-10 09:00:00', 'github_follows_imported_at' => '2026-10-10 09:00:05']);
    $others = User::factory()->count(10)->create(['created_at' => '2026-09-01 00:00:00']);

    $imported->each(fn (User $followed) => ($this->follow)($newcomer, $followed, '2026-10-10 08:00:01'));
    ($this->follow)($newcomer, $later, '2026-10-10 09:00:04');
    ($this->follow)($newcomer, $tom, '2026-10-10 09:00:00');
    $others->take(9)->each(fn (User $followed) => ($this->follow)($newcomer, $followed, '2026-10-10 10:00:00'));
    ($this->follow)($newcomer, $others->last(), '2026-10-10 20:00:01');

    expect(($this->funnel)())
        ->activated->toBe(0)
        ->activation_eligible->toBe(2);
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

it('measures retention from day 1 once signups got through it after tracking started', function (): void {
    ($this->trackSince)('2026-10-15');

    User::factory()->create(['created_at' => '2026-10-16 10:00:00', 'last_seen_at' => '2026-10-17']);
    User::factory()->create(['created_at' => '2026-10-16 10:00:00', 'last_seen_at' => '2026-10-16']);
    User::factory()->create(['created_at' => '2026-10-14 10:00:00', 'last_seen_at' => '2026-10-19']);
    User::factory()->create(['created_at' => '2026-10-13 10:00:00', 'last_seen_at' => '2026-10-19']);
    User::factory()->create(['created_at' => '2026-10-18 10:00:00', 'last_seen_at' => '2026-10-19']);

    expect(($this->funnel)())
        ->retained->toBe(2)
        ->retention_eligible->toBe(3)
        ->rates->retention->toBe(2 / 3);
});

it('has no retention before tracking started, or before signups got through their first day', function (): void {
    User::factory()->create(['created_at' => '2026-10-10 10:00:00', 'last_seen_at' => '2026-10-19']);

    expect(($this->funnel)())
        ->retention_eligible->toBe(0)
        ->rates->retention->toBeNull();

    ($this->trackSince)('2026-10-19');
    User::query()->update(['created_at' => '2026-10-18 08:00:00']);

    expect(($this->funnel)())
        ->retention_eligible->toBe(0)
        ->rates->retention->toBeNull();
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
        ->conversion_by_device->toBeNull()
        ->rates->conversion->toBeNull();
});

it('tells acquisition for each day, signups from the database and conversion from Fathom, today as incomplete', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);

    Http::fake(fn (Request $request) => Http::response(isset($request->data()['date_grouping']) ? match ($request->data()['entity_name']) {
        'guest-visit' => [['unique_conversions' => '400', 'date' => '2026-10-17'], ['unique_conversions' => '50', 'date' => '2026-10-19']],
        'signup' => [['unique_conversions' => '3', 'date' => '2026-10-17']],
    } : [['unique_conversions' => '0']]));

    User::factory()->count(2)->create(['created_at' => '2026-10-17 08:00:00']);
    User::factory()->create(['created_at' => '2026-10-18 23:00:00']);
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-18 00:00:00']);

    expect(($this->funnel)()['acquisition_by_day'])->toBe([
        ['date' => '2026-10-17', 'signups' => 2, 'visitors' => 400, 'converted' => 3, 'complete' => true],
        ['date' => '2026-10-18', 'signups' => 1, 'visitors' => 0, 'converted' => 0, 'complete' => true],
        ['date' => '2026-10-19', 'signups' => 0, 'visitors' => 50, 'converted' => 0, 'complete' => false],
    ]);
});

it('tells conversion for each type of device, most visitors first', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);

    Http::fake(fn (Request $request) => Http::response(isset($request->data()['field_grouping']) ? match ($request->data()['entity_name']) {
        'guest-visit' => [['unique_conversions' => '400', 'device_type' => 'Phone'], ['unique_conversions' => '100', 'device_type' => 'Desktop']],
        'signup' => [['unique_conversions' => '9', 'device_type' => 'Desktop'], ['unique_conversions' => '4', 'device_type' => 'Phone'], ['unique_conversions' => '1', 'device_type' => 'Tablet']],
    } : [['unique_conversions' => '0']]));

    expect(($this->funnel)()['conversion_by_device'])->toBe([
        ['device' => 'Phone', 'visitors' => 400, 'converted' => 4],
        ['device' => 'Desktop', 'visitors' => 100, 'converted' => 9],
        ['device' => 'Tablet', 'visitors' => 0, 'converted' => 1],
    ]);
});

it('tells signups for each day without Fathom', function (): void {
    User::factory()->create(['created_at' => '2026-10-17 08:00:00']);

    expect(($this->funnel)()['acquisition_by_day'])->toBe([
        ['date' => '2026-10-17', 'signups' => 1, 'visitors' => null, 'converted' => null, 'complete' => true],
    ]);
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
        'retention' => -0.5,
    ]);
});

it('compares a range with as many days before it', function (): void {
    ($this->trackSince)('2026-09-01');

    User::factory()->create(['created_at' => '2026-09-20 10:00:00', 'last_seen_at' => '2026-10-19']);
    User::factory()->create(['created_at' => '2026-09-20 10:00:00']);
    User::factory()->create(['created_at' => '2026-09-27 10:00:00', 'last_seen_at' => '2026-10-19']);

    expect(($this->funnel)('2026-09-27', '2026-10-03')['changes']['retention'])->toBe(0.5);
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
