<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Enums\ModerationReason;
use App\Enums\NotificationType;
use App\Enums\OperationsEventLevel;
use App\Enums\OperationsEventSource;
use App\Enums\OperationsEventWindow;
use App\Models\CommitReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * @phpstan-type OperationsEvent array{id: string, source: string, level: string, at: string, message: string, detail: string|null, actor: string|null, href: string|null}
 * @phpstan-type OperationsEventBucket array{at: string, info: int, notice: int, warning: int, error: int}
 */
final readonly class OperationsEventsQuery
{
    private const int DETAIL_LENGTH = 300;

    /**
     * The event log, newest first: signups, failed jobs, reports and their dismissals, moderation verdicts and the
     * commits held for review, bans, timeouts, and features switched on or off, each read from the table that records
     * it. A report only ever shows its reason, never what the reporter wrote.
     *
     * @return list<OperationsEvent>
     */
    public function get(CarbonImmutable $since, ?OperationsEventSource $source = null, ?OperationsEventLevel $level = null, string $search = '', int $limit = 300): array
    {
        /** @var list<object{source: string, kind: string, row_id: string, at: string, level: string, message: string, detail: string|null, actor: string|null, author: string|null, commit_id: string|null}> $events */
        $events = $this->events($since, $source, $level, $search)
            ->orderByDesc('at')
            ->orderByDesc('row_id')
            ->limit($limit)
            ->get()
            ->all();

        return array_map(fn (object $event): array => [
            'id' => $event->source.':'.$event->row_id,
            'source' => $event->source,
            'level' => $event->level,
            'at' => CarbonImmutable::parse($event->at)->toIso8601String(),
            'message' => $event->message,
            'detail' => $event->detail === null ? null : Str::limit($event->detail, self::DETAIL_LENGTH),
            'actor' => $event->actor,
            'href' => match ($event->kind) {
                'signup', 'ban', 'timeout' => $event->actor === null ? null : route('user.show', $event->actor, false),
                'failed_job' => route('system-health.show', absolute: false),
                'report' => route('reported-commit.index', absolute: false),
                'approved' => route('commit.show', [$event->author, $event->commit_id], false),
                'held' => route('moderation.show', absolute: false),
                'feature' => route('feature.index', absolute: false),
                default => null,
            },
        ], $events);
    }

    /**
     * How many events each source has, every source listed, whatever source is picked.
     *
     * @return array<string, int>
     */
    public function counts(CarbonImmutable $since, ?OperationsEventLevel $level = null, string $search = ''): array
    {
        $counts = $this->events($since, null, $level, $search)
            ->selectRaw('source, count(*) as count')
            ->groupBy('source')
            ->get()
            ->mapInto(Fluent::class)
            ->mapWithKeys(fn (Fluent $row): array => [$row->string('source')->value() => $row->integer('count')]);

        return collect(OperationsEventSource::cases())
            ->mapWithKeys(fn (OperationsEventSource $source): array => [$source->value => $counts->get($source->value, 0)])
            ->all();
    }

    /**
     * The events of the window in buckets of its size, oldest first, by level.
     *
     * @return list<OperationsEventBucket>
     */
    public function histogram(OperationsEventWindow $window, ?OperationsEventSource $source = null, ?OperationsEventLevel $level = null, string $search = ''): array
    {
        $start = $window->start(CarbonImmutable::now());
        $size = $window->bucketSeconds();

        $counts = $this->events($start, $source, $level, $search)
            ->selectRaw('floor(extract(epoch from at - ?::timestamp) / ?)::int as bucket, level, count(*) as count', [$start, $size])
            ->groupBy('bucket', 'level')
            ->get()
            ->mapInto(Fluent::class)
            ->mapWithKeys(fn (Fluent $row): array => [$row->integer('bucket').':'.$row->string('level') => $row->integer('count')]);

        return array_map(function (int $bucket) use ($start, $size, $counts): array {
            $count = fn (OperationsEventLevel $level): int => $counts->get("{$bucket}:{$level->value}", 0);

            return [
                'at' => $start->addSeconds($bucket * $size)->toIso8601String(),
                'info' => $count(OperationsEventLevel::Info),
                'notice' => $count(OperationsEventLevel::Notice),
                'warning' => $count(OperationsEventLevel::Warning),
                'error' => $count(OperationsEventLevel::Error),
            ];
        }, range(0, $window->buckets() - 1));
    }

    private function events(CarbonImmutable $since, ?OperationsEventSource $source, ?OperationsEventLevel $level, string $search): Builder
    {
        $pattern = '%'.addcslashes($search, '\\%_').'%';

        return DB::query()
            ->fromSub($this->signups($since)
                ->unionAll($this->failedJobs($since))
                ->unionAll($this->reports($since))
                ->unionAll($this->dismissedReports($since))
                ->unionAll($this->verdicts($since))
                ->unionAll($this->heldCommits($since))
                ->unionAll($this->bans($since))
                ->unionAll($this->timeouts($since))
                ->unionAll($this->features($since)), 'events')
            ->when($source, fn (Builder $query, OperationsEventSource $source): Builder => $query->where('source', $source->value))
            ->when($level, fn (Builder $query, OperationsEventLevel $level): Builder => $query->where('level', $level->value))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->whereLike('message', $pattern)
                ->orWhereLike('detail', $pattern)
                ->orWhereLike('actor', $pattern)));
    }

    private function signups(CarbonImmutable $since): Builder
    {
        return DB::table('users')
            ->leftJoin('profiles', 'profiles.github_id', '=', 'users.github_id')
            ->selectRaw(<<<'SQL'
                'signups' as source, 'signup' as kind, users.id::text as row_id, users.created_at as at, 'info' as level,
                'Signed up' as message, null::text as detail, profiles.handle as actor, null::text as author, null::text as commit_id
                SQL)
            ->whereNotIn('users.github_id', User::BOT_GITHUB_IDS)
            ->where('users.created_at', '>=', $since);
    }

    /**
     * The job is named by its class alone, as the queue it ran on already says where it belongs.
     */
    private function failedJobs(CarbonImmutable $since): Builder
    {
        return DB::table(config()->string('queue.failed.table'))
            ->selectRaw(<<<'SQL'
                'jobs' as source, 'failed_job' as kind, id::text as row_id, failed_at as at, 'error' as level,
                regexp_replace(coalesce(payload::jsonb->>'displayName', payload::jsonb->>'job', 'Unknown job'), '^.*\\', '') || ' failed on ' || queue as message,
                split_part(exception, E'\n', 1) as detail, null::text as actor, null::text as author, null::text as commit_id
                SQL)
            ->where('failed_at', '>=', $since);
    }

    private function reports(CarbonImmutable $since): Builder
    {
        return DB::table('commit_reports')
            ->join('users', 'users.id', '=', 'commit_reports.reporter_id')
            ->leftJoin('profiles', 'profiles.github_id', '=', 'users.github_id')
            ->selectRaw(<<<'SQL'
                'reports' as source, 'report' as kind, commit_reports.id::text as row_id, commit_reports.created_at as at,
                'warning' as level, 'Commit reported: ' || coalesce(?::jsonb->>commit_reports.reason, commit_reports.reason) as message,
                null::text as detail, profiles.handle as actor, null::text as author, null::text as commit_id
                SQL, [json_encode(CommitReport::REASONS)])
            ->where('commit_reports.created_at', '>=', $since);
    }

    private function dismissedReports(CarbonImmutable $since): Builder
    {
        return DB::table('commit_reports')
            ->selectRaw(<<<'SQL'
                'reports' as source, 'report' as kind, id::text || ':dismissed' as row_id, dismissed_at as at,
                'notice' as level, 'Report dismissed' as message, coalesce(?::jsonb->>reason, reason) as detail,
                null::text as actor, null::text as author, null::text as commit_id
                SQL, [json_encode(CommitReport::REASONS)])
            ->where('dismissed_at', '>=', $since);
    }

    /**
     * Rejections leave a verdict behind, sent to the author as their own actor, so the moderator is unknown. Approvals
     * no longer do, but the ones sent before still show.
     */
    private function verdicts(CarbonImmutable $since): Builder
    {
        return DB::table('notifications')
            ->join('users as actors', 'actors.id', '=', 'notifications.actor_id')
            ->leftJoin('profiles as actor_profiles', 'actor_profiles.github_id', '=', 'actors.github_id')
            ->leftJoin('commits', 'commits.id', '=', 'notifications.commit_id')
            ->leftJoin('users as authors', 'authors.id', '=', 'commits.user_id')
            ->leftJoin('profiles as author_profiles', 'author_profiles.github_id', '=', 'authors.github_id')
            ->selectRaw(<<<'SQL'
                'moderation' as source, notifications.type as kind, notifications.id::text as row_id,
                notifications.created_at as at, 'notice' as level,
                case when notifications.type = ? then 'Commit approved' else 'Commit rejected' end as message,
                notifications.reason as detail, actor_profiles.handle as actor, author_profiles.handle as author,
                notifications.commit_id::text as commit_id
                SQL, [NotificationType::Approved->value])
            ->whereIn('notifications.type', [NotificationType::Approved, NotificationType::Rejected])
            ->where('notifications.created_at', '>=', $since);
    }

    private function heldCommits(CarbonImmutable $since): Builder
    {
        return DB::table('commits')
            ->join('users', 'users.id', '=', 'commits.user_id')
            ->leftJoin('profiles', 'profiles.github_id', '=', 'users.github_id')
            ->selectRaw(<<<'SQL'
                'moderation' as source, 'held' as kind, commits.id::text as row_id, commits.created_at as at, 'notice' as level,
                'Commit held for review' as message, null::text as detail, profiles.handle as actor, null::text as author,
                null::text as commit_id
                SQL)
            ->where('commits.status', CommitStatus::Pending)
            ->whereNull('commits.deleted_at')
            ->where('commits.created_at', '>=', $since);
    }

    /**
     * Bans and timeouts are kept by GitHub account, so the person is only named while they still have a profile.
     */
    private function bans(CarbonImmutable $since): Builder
    {
        return DB::table('bans')
            ->leftJoin('profiles', 'profiles.github_id', '=', 'bans.github_id')
            ->selectRaw(<<<'SQL'
                'bans' as source, 'ban' as kind, bans.id::text as row_id, bans.created_at as at, 'error' as level,
                'Account banned' as message, coalesce(?::jsonb->>bans.reason, bans.reason) as detail,
                profiles.handle as actor, null::text as author, null::text as commit_id
                SQL, [json_encode(ModerationReason::options())])
            ->where('bans.created_at', '>=', $since);
    }

    private function timeouts(CarbonImmutable $since): Builder
    {
        return DB::table('user_timeouts')
            ->leftJoin('profiles', 'profiles.github_id', '=', 'user_timeouts.github_id')
            ->selectRaw(<<<'SQL'
                'timeouts' as source, 'timeout' as kind, user_timeouts.id::text as row_id, user_timeouts.created_at as at,
                'warning' as level, 'Timed out until ' || to_char(user_timeouts.ends_at, 'YYYY-MM-DD HH24:MI') || ' UTC' as message,
                coalesce(?::jsonb->>user_timeouts.reason, user_timeouts.reason) as detail, profiles.handle as actor,
                null::text as author, null::text as commit_id
                SQL, [json_encode(ModerationReason::options())])
            ->where('user_timeouts.created_at', '>=', $since);
    }

    private function features(CarbonImmutable $since): Builder
    {
        return DB::table('features')
            ->selectRaw(<<<'SQL'
                'features' as source, 'feature' as kind, name as row_id, updated_at as at, 'notice' as level,
                'Feature ' || name || ' turned ' || case when enabled then 'on' else 'off' end as message, null::text as detail,
                null::text as actor, null::text as author, null::text as commit_id
                SQL)
            ->where('updated_at', '>=', $since);
    }
}
