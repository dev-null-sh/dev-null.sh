<?php

declare(strict_types=1);

use App\Jobs\NotifyIssueComments;
use App\Models\Issue;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    assert($key !== false);
    openssl_pkey_export($key, $privateKey);

    config(['services.github_app' => ['id' => '12345', 'private_key' => str_replace("\n", '\n', $privateKey)]]);
    CarbonImmutable::setTestNow('2026-10-24 12:00:00');
});

/**
 * @param  list<array<string, mixed>>  ...$pages
 */
function fakeIssueComments(array ...$pages): void
{
    $sequence = Http::sequence();

    foreach ($pages as $page) {
        $sequence->push($page);
    }

    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/comments*' => $sequence,
    ]);
}

/**
 * @return array<string, mixed>
 */
function gitHubComment(int $id, int $issue, ?int $authorId, string $type = 'User', string $updatedAt = '2026-10-24T11:00:00Z'): array
{
    return [
        'id' => $id,
        'html_url' => "https://github.com/dev-null-sh/dev-null.sh/issues/{$issue}#issuecomment-{$id}",
        'issue_url' => "https://api.github.com/repos/dev-null-sh/dev-null.sh/issues/{$issue}",
        'user' => $authorId === null ? null : ['id' => $authorId, 'login' => "user{$authorId}", 'type' => $type],
        'updated_at' => $updatedAt,
        'body' => 'Thanks for the report!',
    ];
}

/**
 * A full page of comments by someone not on /dev/null, edited one second apart from the given time onward.
 *
 * @return list<array<string, mixed>>
 */
function fullPageOfComments(int $firstId, string $from, int $issue = 1096): array
{
    return array_map(
        fn (int $offset): array => gitHubComment($firstId + $offset, $issue, 2, updatedAt: CarbonImmutable::parse($from)->addSeconds($offset)->toIso8601ZuluString()),
        range(0, 99),
    );
}

/**
 * @return list<array{since: mixed, page: mixed}>
 */
function listedFrom(): array
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/issues/comments'))
        ->map(fn (array $pair): array => ['since' => $pair[0]['since'], 'page' => $pair[0]['page']])
        ->values()
        ->all();
}

it('asks GitHub nothing until someone has opened an issue here', function (): void {
    Http::fake();

    dispatch_sync(new NotifyIssueComments());

    Http::assertNothingSent();
    expect(Cache::get(NotifyIssueComments::CheckedAt))->toBeNull();
});

it('looks back a day the first time, then picks up from when the last check began', function (): void {
    Issue::factory()->create(['number' => 1096]);
    fakeIssueComments([], []);

    dispatch_sync(new NotifyIssueComments());
    CarbonImmutable::setTestNow('2026-10-24 12:05:00');
    dispatch_sync(new NotifyIssueComments());

    expect(array_column(listedFrom(), 'since'))->toBe(['2026-10-23T12:00:00Z', '2026-10-24T12:00:00Z'])
        ->and(Cache::get(NotifyIssueComments::CheckedAt))->toBe('2026-10-24T12:05:00Z');
});

it("lists the comments with the app's token, oldest edits first, from the last edit it has seen", function (): void {
    $reporter = User::factory()->create(['github_id' => '1']);
    Issue::factory()->for($reporter)->create(['number' => 1096]);
    fakeIssueComments(
        fullPageOfComments(1, '2026-10-24T10:00:00Z'),
        [gitHubComment(101, 1096, 2), gitHubComment(102, 1096, 3, 'Bot')],
    );

    dispatch_sync(new NotifyIssueComments());

    expect(Notification::query()->count())->toBe(101)
        ->and(listedFrom())->toBe([
            ['since' => '2026-10-23T12:00:00Z', 'page' => 1],
            ['since' => '2026-10-24T10:01:38Z', 'page' => 1],
        ]);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/issues/comments')
        && $request->hasHeader('Authorization', 'Bearer installation-token')
        && $request['sort'] === 'updated'
        && $request['direction'] === 'asc'
        && $request['per_page'] === 100);
});

it('moves on by page when a whole page was edited within the same second', function (): void {
    Issue::factory()->create(['number' => 1096]);
    Cache::forever(NotifyIssueComments::CheckedAt, '2026-10-24T11:00:00Z');
    $sameSecond = array_map(fn (int $id): array => gitHubComment($id, 1096, 2), range(1, 100));
    fakeIssueComments($sameSecond, []);

    dispatch_sync(new NotifyIssueComments());

    expect(listedFrom())->toBe([
        ['since' => '2026-10-24T11:00:00Z', 'page' => 1],
        ['since' => '2026-10-24T11:00:00Z', 'page' => 2],
    ]);
});

it('stops after ten requests and hands the next check where it got to, so nothing is skipped', function (): void {
    Issue::factory()->create(['number' => 1096]);
    $pages = array_map(
        fn (int $page): array => fullPageOfComments($page * 100 + 1, CarbonImmutable::parse('2026-10-24T01:00:00Z')->addMinutes($page * 10)->toIso8601ZuluString()),
        range(0, 10),
    );
    fakeIssueComments(...$pages);

    dispatch_sync(new NotifyIssueComments());

    Http::assertSentCount(12);
    expect(Notification::query()->count())->toBe(1000)
        ->and(Cache::get(NotifyIssueComments::CheckedAt))->toBe('2026-10-24T02:31:38Z');
});

it('notifies of comments whose author GitHub no longer shows, and skips entries that are not comments', function (): void {
    $issue = Issue::factory()->create(['number' => 1096]);
    fakeIssueComments([
        gitHubComment(1, 1096, null),
        ['id' => 'not-a-number'],
        'not-a-comment',
    ]);

    dispatch_sync(new NotifyIssueComments());

    expect(Notification::query()->sole())
        ->actor_id->toBe($issue->user_id)
        ->url->toBe('https://github.com/dev-null-sh/dev-null.sh/issues/1096#issuecomment-1')
        ->and(Cache::get(NotifyIssueComments::CheckedAt))->toBe('2026-10-24T12:00:00Z');
});

it('treats a response that is not a list as having no comments', function (): void {
    Issue::factory()->create();
    fakeIssueComments(['message' => 'unexpected']);

    dispatch_sync(new NotifyIssueComments());

    $this->assertDatabaseCount('notifications', 0);
    expect(Cache::get(NotifyIssueComments::CheckedAt))->toBe('2026-10-24T12:00:00Z');
});

it('keeps the last check when GitHub fails, so the next run lists those comments again', function (): void {
    Issue::factory()->create();
    Cache::forever(NotifyIssueComments::CheckedAt, '2026-10-24T11:55:00Z');
    Http::fake([
        'api.github.com/repos/dev-null-sh/dev-null.sh/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/dev-null-sh/dev-null.sh/issues/comments*' => Http::response(status: 502),
    ]);

    expect(fn () => dispatch_sync(new NotifyIssueComments()))->toThrow(RequestException::class)
        ->and(Cache::get(NotifyIssueComments::CheckedAt))->toBe('2026-10-24T11:55:00Z');
});

it('runs every five minutes', function (): void {
    $event = collect(resolve(Schedule::class)->events())
        ->sole(fn (Event $event): bool => $event->description === NotifyIssueComments::class);

    expect($event)->toBeInstanceOf(CallbackEvent::class)
        ->expression->toBe('*/5 * * * *');
});
