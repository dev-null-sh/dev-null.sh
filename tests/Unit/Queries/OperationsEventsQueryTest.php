<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Enums\Feature;
use App\Enums\ModerationReason;
use App\Enums\NotificationType;
use App\Enums\OperationsEventLevel;
use App\Enums\OperationsEventSource;
use App\Enums\OperationsEventWindow;
use App\Jobs\UnfurlLink;
use App\Models\Ban;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserTimeout;
use App\Queries\OperationsEventsQuery;
use App\Services\Features;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));
});

it('logs signups, bots left out', function (): void {
    $user = User::factory()->create(['username' => 'alice', 'created_at' => now()->subHour()]);
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => now()->subHour()]);

    expect(resolve(OperationsEventsQuery::class)->get(now()->subDay()->toImmutable()))->toBe([[
        'id' => 'signups:'.$user->id,
        'source' => 'signups',
        'level' => 'info',
        'at' => '2026-10-20T11:30:00+00:00',
        'message' => 'Signed up',
        'detail' => null,
        'actor' => 'alice',
        'href' => '/@alice',
    ]]);
});

it('logs failed jobs by their class and queue, with the first line of their exception', function (): void {
    failedJob(queue: 'previews', failedAt: now()->subMinute(), exception: str_repeat('x', 301)."\nStack trace:\n#0 {main}", payload: ['displayName' => UnfurlLink::class]);
    failedJob(failedAt: now()->subMinutes(2), payload: []);

    expect(resolve(OperationsEventsQuery::class)->get(now()->subDay()->toImmutable()))
        ->sequence(
            fn ($event) => $event->toMatchArray([
                'source' => 'jobs',
                'level' => 'error',
                'at' => '2026-10-20T12:29:00+00:00',
                'message' => 'UnfurlLink failed on previews',
                'detail' => str_repeat('x', 300).'...',
                'actor' => null,
                'href' => '/operations/health',
            ]),
            fn ($event) => $event->toMatchArray(['message' => 'Unknown job failed on default', 'detail' => 'RuntimeException: Something broke']),
        );
});

it('logs reports by their reason, never what the reporter wrote, and their dismissals', function (): void {
    $reporter = User::factory()->create(['username' => 'reporter', 'created_at' => now()->subWeek()]);
    $report = CommitReport::factory()->create([
        'reporter_id' => $reporter->id,
        'commit_id' => Commit::factory()->create(['created_at' => now()->subWeek()])->id,
        'reason' => 'harassment',
        'details' => 'They keep posting my address',
        'created_at' => now()->subHours(2),
    ]);
    $report->forceFill(['dismissed_at' => now()->subHour()])->save();

    expect(resolve(OperationsEventsQuery::class)->get(now()->subDay()->toImmutable(), OperationsEventSource::Reports))->toBe([
        [
            'id' => "reports:{$report->id}:dismissed",
            'source' => 'reports',
            'level' => 'notice',
            'at' => '2026-10-20T11:30:00+00:00',
            'message' => 'Report dismissed',
            'detail' => 'Harassment or hate',
            'actor' => null,
            'href' => '/moderation/reports',
        ],
        [
            'id' => "reports:{$report->id}",
            'source' => 'reports',
            'level' => 'warning',
            'at' => '2026-10-20T10:30:00+00:00',
            'message' => 'Commit reported: Harassment or hate',
            'detail' => null,
            'actor' => 'reporter',
            'href' => '/moderation/reports',
        ],
    ]);
});

it('logs moderation verdicts and the commits held for review', function (): void {
    $author = User::factory()->create(['username' => 'author', 'created_at' => now()->subWeek()]);
    $approved = Commit::factory()->for($author)->create(['created_at' => now()->subWeek()]);
    $rejected = Commit::factory()->for($author)->rejected()->create(['created_at' => now()->subWeek()]);
    $held = Commit::factory()->for($author)->pending()->create(['created_at' => now()->subMinutes(10)]);
    Commit::factory()->for($author)->pending()->create(['created_at' => now()->subMinutes(5)])->delete();

    $approval = Notification::factory()->for($author, 'recipient')->for($author, 'actor')->create([
        'type' => NotificationType::Approved,
        'commit_id' => $approved->id,
        'created_at' => now()->subMinutes(30),
    ]);
    $rejection = Notification::factory()->for($author, 'recipient')->for($author, 'actor')->create([
        'type' => NotificationType::Rejected,
        'commit_id' => $rejected->id,
        'reason' => 'Off-topic',
        'created_at' => now()->subMinutes(20),
    ]);

    expect(resolve(OperationsEventsQuery::class)->get(now()->subDay()->toImmutable(), OperationsEventSource::Moderation))->toBe([
        [
            'id' => "moderation:{$held->id}",
            'source' => 'moderation',
            'level' => 'notice',
            'at' => '2026-10-20T12:20:00+00:00',
            'message' => 'Commit held for review',
            'detail' => null,
            'actor' => 'author',
            'href' => '/moderation',
        ],
        [
            'id' => "moderation:{$rejection->id}",
            'source' => 'moderation',
            'level' => 'notice',
            'at' => '2026-10-20T12:10:00+00:00',
            'message' => 'Commit rejected',
            'detail' => 'Off-topic',
            'actor' => 'author',
            'href' => null,
        ],
        [
            'id' => "moderation:{$approval->id}",
            'source' => 'moderation',
            'level' => 'notice',
            'at' => '2026-10-20T12:00:00+00:00',
            'message' => 'Commit approved',
            'detail' => null,
            'actor' => 'author',
            'href' => "/@author/commits/{$approved->id}",
        ],
    ]);
});

it('logs bans and timeouts by their reason, naming the person while they still have an account', function (): void {
    $user = User::factory()->create(['username' => 'spammer', 'created_at' => now()->subWeek()]);
    $ban = Ban::factory()->create(['github_id' => $user->github_id, 'reason' => ModerationReason::Spam, 'created_at' => now()->subMinutes(10)]);
    $timeout = UserTimeout::factory()->create([
        'github_id' => '123',
        'reason' => ModerationReason::Harassment,
        'ends_at' => CarbonImmutable::parse('2026-10-21 08:15:00'),
        'created_at' => now()->subMinutes(20),
    ]);

    expect(resolve(OperationsEventsQuery::class)->get(now()->subDay()->toImmutable()))->toBe([
        [
            'id' => "bans:{$ban->id}",
            'source' => 'bans',
            'level' => 'error',
            'at' => '2026-10-20T12:20:00+00:00',
            'message' => 'Account banned',
            'detail' => 'Spam or self-promotion',
            'actor' => 'spammer',
            'href' => '/@spammer',
        ],
        [
            'id' => "timeouts:{$timeout->id}",
            'source' => 'timeouts',
            'level' => 'warning',
            'at' => '2026-10-20T12:10:00+00:00',
            'message' => 'Timed out until 2026-10-21 08:15 UTC',
            'detail' => 'Harassment or hate',
            'actor' => null,
            'href' => null,
        ],
    ]);
});

it('logs features switched on or off', function (): void {
    resolve(Features::class)->update(Feature::Translations, false);

    expect(resolve(OperationsEventsQuery::class)->get(now()->subDay()->toImmutable()))->toBe([[
        'id' => 'features:translations',
        'source' => 'features',
        'level' => 'notice',
        'at' => '2026-10-20T12:30:00+00:00',
        'message' => 'Feature translations turned off',
        'detail' => null,
        'actor' => null,
        'href' => '/operations/features',
    ]]);
});

it('lists the newest events of the window first, up to the limit', function (): void {
    User::factory()->create(['username' => 'old', 'created_at' => now()->subDays(2)]);
    User::factory()->create(['username' => 'first', 'created_at' => now()->subHours(3)]);
    User::factory()->create(['username' => 'second', 'created_at' => now()->subHours(2)]);
    User::factory()->create(['username' => 'third', 'created_at' => now()->subHour()]);

    $events = resolve(OperationsEventsQuery::class)->get(now()->subDay()->toImmutable(), limit: 2);

    expect(array_column($events, 'actor'))->toBe(['third', 'second']);
});

it('filters by source, level, and a case-insensitive search of the message, detail and actor', function (): void {
    User::factory()->create(['username' => 'Searchable_Dev', 'created_at' => now()->subHour()]);
    User::factory()->create(['username' => 'other', 'created_at' => now()->subHour()]);
    failedJob(failedAt: now()->subHour(), exception: 'RuntimeException: searchable failure');
    failedJob(failedAt: now()->subHour(), exception: 'RuntimeException: 100% broken');

    $events = resolve(OperationsEventsQuery::class);
    $since = now()->subDay()->toImmutable();

    expect(array_column($events->get($since, search: 'searchable'), 'source'))->toEqualCanonicalizing(['signups', 'jobs'])
        ->and(array_column($events->get($since, OperationsEventSource::Signups, search: 'SEARCHABLE'), 'actor'))->toBe(['Searchable_Dev'])
        ->and(array_column($events->get($since, level: OperationsEventLevel::Error), 'source'))->toBe(['jobs', 'jobs'])
        ->and(array_column($events->get($since, search: 'unfurllink failed'), 'source'))->toBe(['jobs', 'jobs'])
        ->and(array_column($events->get($since, search: '0%'), 'detail'))->toBe(['RuntimeException: 100% broken'])
        ->and(array_column($events->get($since, search: 'e_d'), 'actor'))->toBe(['Searchable_Dev']);
});

it('counts the events of every source, whatever source is picked', function (): void {
    User::factory()->create(['created_at' => now()->subHour()]);
    User::factory()->create(['created_at' => now()->subDays(2)]);
    failedJob(failedAt: now()->subHour(), exception: 'RuntimeException: Connection refused');
    failedJob(failedAt: now()->subHour());

    $events = resolve(OperationsEventsQuery::class);
    $since = now()->subDay()->toImmutable();

    expect($events->counts($since))->toBe([
        'signups' => 1,
        'jobs' => 2,
        'reports' => 0,
        'moderation' => 0,
        'bans' => 0,
        'timeouts' => 0,
        'features' => 0,
    ])
        ->and($events->counts($since, OperationsEventLevel::Info))->toMatchArray(['signups' => 1, 'jobs' => 0])
        ->and($events->counts($since, search: 'refused'))->toMatchArray(['signups' => 0, 'jobs' => 1]);
});

it('draws the window in buckets of its size by level, oldest first', function (): void {
    User::factory()->create(['created_at' => CarbonImmutable::parse('2026-10-20 12:29:00')]);
    User::factory()->create(['created_at' => CarbonImmutable::parse('2026-10-20 11:59:59')]);
    User::factory()->create(['created_at' => CarbonImmutable::parse('2026-10-19 12:59:59')]);
    failedJob(failedAt: CarbonImmutable::parse('2026-10-20 12:00:00'));
    failedJob(queue: 'previews', failedAt: CarbonImmutable::parse('2026-10-19 13:00:00'));

    $events = resolve(OperationsEventsQuery::class);
    $histogram = $events->histogram(OperationsEventWindow::Day);

    expect($histogram)->toHaveCount(24)
        ->and($histogram[0])->toBe(['at' => '2026-10-19T13:00:00+00:00', 'info' => 0, 'notice' => 0, 'warning' => 0, 'error' => 1])
        ->and($histogram[1])->toBe(['at' => '2026-10-19T14:00:00+00:00', 'info' => 0, 'notice' => 0, 'warning' => 0, 'error' => 0])
        ->and($histogram[22])->toBe(['at' => '2026-10-20T11:00:00+00:00', 'info' => 1, 'notice' => 0, 'warning' => 0, 'error' => 0])
        ->and($histogram[23])->toBe(['at' => '2026-10-20T12:00:00+00:00', 'info' => 1, 'notice' => 0, 'warning' => 0, 'error' => 1])
        ->and(array_sum(array_column($events->histogram(OperationsEventWindow::Day, OperationsEventSource::Signups), 'error')))->toBe(0)
        ->and(array_sum(array_column($events->histogram(OperationsEventWindow::Day, level: OperationsEventLevel::Info), 'error')))->toBe(0)
        ->and(array_sum(array_column($events->histogram(OperationsEventWindow::Day, search: 'previews'), 'error')))->toBe(1);
});
