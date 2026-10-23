import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import FailedJobController from '@/actions/App/Http/Controllers/FailedJobController';
import FailedJobRetryController from '@/actions/App/Http/Controllers/FailedJobRetryController';
import FailedJobsController from '@/actions/App/Http/Controllers/FailedJobsController';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import { EmptyFeed } from '@/components/commit-feed';
import { Field, Section } from '@/components/operations/section';
import { Status } from '@/components/operations/status';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import OperationsLayout, { TAB_PROPS } from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import { formatAge } from '@/lib/operations';
import { cn } from '@/lib/utils';
import type {
    Connectivity,
    FailedJob,
    FailedJobs,
    GitHubRateLimit,
    LinkPreviewHealth,
    QueueBacklog,
} from '@/types';

const STALE_JOB_SECONDS = 300;

const LEAVE_DELAY_MS = 700;

function classBasename(name: string): string {
    return name.split('\\').pop() ?? name;
}

function GitHubRateLimitValue({
    rateLimit,
}: {
    rateLimit: GitHubRateLimit | null | undefined;
}) {
    const now = useNow();

    if (!rateLimit) {
        return (
            <Status isUp={false}>
                unavailable
                <span className="font-normal text-muted-foreground">
                    GitHub didn't answer
                </span>
            </Status>
        );
    }

    const share =
        rateLimit.limit > 0 ? rateLimit.remaining / rateLimit.limit : 0;
    const isLow = share < 0.1;
    const resetsIn = Math.max(
        0,
        Math.round(
            (new Date(rateLimit.resets_at).getTime() - now.getTime()) / 1000,
        ),
    );

    return (
        <div className="space-y-2">
            <p className="flex flex-wrap items-baseline gap-x-2 font-mono text-[13px]">
                <span
                    className={cn('font-medium', { 'text-destructive': isLow })}
                >
                    {formatCount(rateLimit.remaining)}
                    <span className="font-normal text-muted-foreground">
                        {' / '}
                        {formatCount(rateLimit.limit)} left
                    </span>
                </span>
                <span className="basis-full text-muted-foreground sm:basis-auto">
                    <span aria-hidden className="mr-2 hidden sm:inline">
                        ·
                    </span>
                    resets in {formatAge(resetsIn)}
                </span>
            </p>
            <div
                role="meter"
                aria-label="GitHub requests left this hour"
                aria-valuemin={0}
                aria-valuemax={rateLimit.limit}
                aria-valuenow={rateLimit.remaining}
                className="h-1 max-w-56 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        isLow ? 'bg-destructive' : 'bg-muted-foreground',
                    )}
                    style={{ width: `${share * 100}%` }}
                />
            </div>
        </div>
    );
}

function GitHubRateLimitSkeleton() {
    return (
        <div
            aria-hidden
            className="animate-in space-y-2 delay-300 duration-300 fill-mode-both fade-in"
        >
            <div className="flex flex-wrap gap-x-2">
                <span className="flex h-5 items-center">
                    <Skeleton className="h-3 w-36" />
                </span>
                <span className="flex h-5 basis-full items-center sm:basis-auto">
                    <Skeleton className="h-3 w-24" />
                </span>
            </div>
            <Skeleton className="h-1 max-w-56 rounded-full" />
        </div>
    );
}

function FailedJobRow({
    failedJob,
    hasReplacement,
}: {
    failedJob: FailedJob;
    hasReplacement: boolean;
}) {
    const { formatFullDateTime, formatRelativeTime } = useDateTime();
    const now = useNow();
    const [state, setState] = useState<
        'idle' | 'confirming' | 'retrying' | 'forgetting'
    >('idle');
    const name = classBasename(failedJob.name);
    const isLeaving = state === 'retrying' || state === 'forgetting';

    const leave = (next: 'retrying' | 'forgetting') => {
        setState(next);

        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        window.setTimeout(
            () =>
                router.visit(
                    next === 'retrying'
                        ? FailedJobRetryController.store(failedJob.uuid)
                        : FailedJobController.destroy(failedJob.uuid),
                    {
                        only: TAB_PROPS['operations/show'],
                        preserveState: true,
                        preserveScroll: true,
                        onFinish: () => setState('idle'),
                        onSuccess: () =>
                            toast(
                                <span className="font-mono text-[13px]">
                                    {next === 'retrying'
                                        ? `Pushed ${name} back onto ${failedJob.queue}`
                                        : `rm ${name} → /dev/null`}
                                </span>,
                            ),
                        onError: () =>
                            toast.error(
                                <span className="font-mono text-[13px]">
                                    fatal: could not{' '}
                                    {next === 'retrying' ? 'retry' : 'forget'}{' '}
                                    {name}
                                </span>,
                            ),
                    },
                ),
            prefersReducedMotion ? 0 : LEAVE_DELAY_MS,
        );
    };

    return (
        <li
            className={cn(
                'grid transition-[grid-template-rows,opacity] duration-300 ease-out',
                isLeaving && !hasReplacement
                    ? 'grid-rows-[0fr] opacity-0 delay-400'
                    : 'grid-rows-[1fr]',
            )}
            data-test="failed-job"
        >
            <div className="min-h-0 overflow-hidden">
                <div className="px-4 py-3">
                    <p className="flex items-baseline gap-2">
                        <span
                            className="max-w-full shrink-0 truncate font-medium"
                            title={failedJob.name}
                        >
                            {name}
                        </span>
                        <span className="flex min-w-0 items-baseline font-mono text-[13px] whitespace-nowrap text-muted-foreground">
                            <span className="truncate" title={failedJob.queue}>
                                {failedJob.queue}
                            </span>
                            <span aria-hidden className="shrink-0 px-1.5">
                                ·
                            </span>
                            <time
                                className="shrink-0"
                                dateTime={failedJob.failed_at}
                                title={formatFullDateTime(failedJob.failed_at)}
                            >
                                {formatRelativeTime(failedJob.failed_at, now)}
                            </time>
                        </span>
                    </p>
                    <p
                        className="mt-1 line-clamp-2 min-h-10 font-mono text-xs leading-5 wrap-anywhere text-muted-foreground"
                        title={failedJob.exception}
                    >
                        {failedJob.exception}
                    </p>

                    <div className="mt-2 flex h-9 items-center gap-2">
                        {isLeaving && (
                            <p
                                role="status"
                                className="truncate font-mono text-[13px] text-muted-foreground"
                            >
                                {state === 'retrying'
                                    ? `retry ${name} → ${failedJob.queue}`
                                    : `rm ${name} → /dev/null`}
                            </p>
                        )}
                        {state === 'confirming' && (
                            <div
                                role="group"
                                aria-label={`Forget ${name}`}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        setState('idle');
                                    }
                                }}
                                className="flex h-full min-w-0 flex-1 items-center gap-1 rounded-md bg-destructive/8 pr-1 pl-3"
                            >
                                <p className="mr-auto min-w-0 truncate text-sm">
                                    Forget this job?
                                    <span className="hidden sm:inline">
                                        {' '}
                                        It won't run again.
                                    </span>
                                </p>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7"
                                    autoFocus
                                    onClick={() => setState('idle')}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    className="h-7"
                                    onClick={() => leave('forgetting')}
                                    data-test="confirm-forget-failed-job-button"
                                >
                                    Forget
                                </Button>
                            </div>
                        )}
                        {state === 'idle' && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => leave('retrying')}
                                    data-test="retry-failed-job-button"
                                >
                                    <RotateCcw />
                                    Retry
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setState('confirming')}
                                    className="text-muted-foreground hover:text-destructive"
                                    data-test="forget-failed-job-button"
                                >
                                    <Trash2 />
                                    Forget
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </li>
    );
}

function ForgetAllFailedJobs({ total }: { total: number }) {
    const [state, setState] = useState<'idle' | 'confirming' | 'forgetting'>(
        'idle',
    );

    const forget = () => {
        setState('forgetting');

        router.visit(FailedJobsController.destroy(), {
            only: TAB_PROPS['operations/show'],
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setState('idle'),
            onSuccess: () =>
                toast(
                    <span className="font-mono text-[13px]">
                        rm {formatCount(total)} failed jobs → /dev/null
                    </span>,
                ),
            onError: () =>
                toast.error(
                    <span className="font-mono text-[13px]">
                        fatal: could not forget failed jobs
                    </span>,
                ),
        });
    };

    if (state === 'confirming') {
        return (
            <div
                role="group"
                aria-label="Forget all failed jobs"
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        setState('idle');
                    }
                }}
                className="flex h-9 min-w-0 flex-1 items-center gap-1 rounded-md bg-destructive/8 pr-1 pl-3"
            >
                <p className="mr-auto min-w-0 truncate text-sm">
                    Forget all {formatCount(total)} jobs?
                    <span className="hidden sm:inline">
                        {' '}
                        None of them will run again.
                    </span>
                </p>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7"
                    autoFocus
                    onClick={() => setState('idle')}
                >
                    Cancel
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    className="h-7"
                    onClick={forget}
                    data-test="confirm-forget-all-failed-jobs-button"
                >
                    Forget all
                </Button>
            </div>
        );
    }

    return (
        <Button
            variant="ghost"
            size="sm"
            disabled={state === 'forgetting'}
            onClick={() => setState('confirming')}
            className="ml-auto text-muted-foreground hover:text-destructive"
            data-test="forget-all-failed-jobs-button"
        >
            <Trash2 />
            Forget all
        </Button>
    );
}

export default function Show({
    queues,
    failedJobs,
    connectivity,
    linkPreviews,
    pendingModerationCount,
    githubRateLimit,
}: {
    queues: QueueBacklog[];
    failedJobs: FailedJobs;
    connectivity: Connectivity;
    linkPreviews: LinkPreviewHealth;
    pendingModerationCount: number;
    githubRateLimit?: GitHubRateLimit | null;
}) {
    const { deferredProps } = usePage();

    const isDeferred = (prop: string) =>
        Object.values(deferredProps ?? {})
            .flat()
            .includes(prop);

    const tracksGitHub =
        githubRateLimit !== undefined || isDeferred('githubRateLimit');

    return (
        <>
            <Head title="Operations" />

            <Section title="Health">
                <dl className="divide-y rounded-lg border text-sm">
                    <Field label="database">
                        <Status isUp={connectivity.database}>
                            {connectivity.database
                                ? 'connected'
                                : 'unreachable'}
                        </Status>
                    </Field>
                    <Field label="cache">
                        <Status isUp={connectivity.cache}>
                            {connectivity.cache ? 'connected' : 'unreachable'}
                        </Status>
                    </Field>
                    {tracksGitHub && (
                        <Field label="github api">
                            <Deferred
                                data="githubRateLimit"
                                fallback={<GitHubRateLimitSkeleton />}
                            >
                                <GitHubRateLimitValue
                                    rateLimit={githubRateLimit}
                                />
                            </Deferred>
                        </Field>
                    )}
                </dl>
            </Section>

            <Section title="Queues" meta="database">
                <div className="rounded-lg border px-4">
                    <table className="w-full table-fixed text-sm">
                        <thead>
                            <tr className="font-mono text-[13px] text-muted-foreground">
                                <th
                                    scope="col"
                                    className="py-2.5 text-left font-normal"
                                >
                                    queue
                                </th>
                                <th
                                    scope="col"
                                    className="w-20 py-2.5 text-right font-normal sm:w-24"
                                >
                                    waiting
                                </th>
                                <th
                                    scope="col"
                                    className="w-20 py-2.5 text-right font-normal sm:w-24"
                                >
                                    oldest
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y border-t">
                            {queues.map((queue) => {
                                const isStale =
                                    (queue.oldest_job_age ?? 0) >=
                                    STALE_JOB_SECONDS;

                                return (
                                    <tr key={queue.queue}>
                                        <th
                                            scope="row"
                                            className="truncate py-2.5 pr-3 text-left font-mono text-[13px] font-normal"
                                            title={queue.queue}
                                        >
                                            {queue.queue}
                                        </th>
                                        <td className="py-2.5 text-right font-mono text-[13px] font-medium tabular-nums">
                                            {formatCount(queue.size)}
                                        </td>
                                        <td
                                            className={cn(
                                                'py-2.5 text-right font-mono text-[13px] tabular-nums',
                                                isStale
                                                    ? 'font-medium text-destructive'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {queue.oldest_job_age === null
                                                ? '–'
                                                : formatAge(
                                                      queue.oldest_job_age,
                                                  )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </Section>

            <Section
                title="Failed jobs"
                meta={`${formatCount(failedJobs.total)} total`}
            >
                {failedJobs.recent.length === 0 ? (
                    <div className="rounded-lg border px-4 py-8 text-center">
                        <EmptyFeed command="$ php artisan queue:failed">
                            No failed jobs. Everything ran.
                        </EmptyFeed>
                    </div>
                ) : (
                    <>
                        <ul className="divide-y rounded-lg border">
                            {failedJobs.recent.map((failedJob) => (
                                <FailedJobRow
                                    key={failedJob.uuid}
                                    failedJob={failedJob}
                                    hasReplacement={
                                        failedJobs.total >
                                        failedJobs.recent.length
                                    }
                                />
                            ))}
                        </ul>
                        <div className="mt-3 flex min-h-9 items-center gap-3">
                            {failedJobs.total > failedJobs.recent.length && (
                                <p className="text-sm text-muted-foreground">
                                    Showing the {failedJobs.recent.length} most
                                    recent of {formatCount(failedJobs.total)}.
                                </p>
                            )}
                            <ForgetAllFailedJobs total={failedJobs.total} />
                        </div>
                    </>
                )}
            </Section>

            <Section title="Backlog">
                <dl className="divide-y rounded-lg border text-sm">
                    <Field label="moderation">
                        <span className="flex flex-wrap items-baseline gap-x-3">
                            <span>
                                <span className="font-mono text-[13px] font-medium">
                                    {formatCount(pendingModerationCount)}
                                </span>{' '}
                                {pendingModerationCount === 1
                                    ? 'commit waiting'
                                    : 'commits waiting'}
                            </span>
                            {pendingModerationCount > 0 && (
                                <Link
                                    href={ModerationController.show()}
                                    className="text-primary underline-offset-4 hover:underline"
                                >
                                    Review
                                </Link>
                            )}
                        </span>
                    </Field>
                    <Field label="link previews">
                        <span className="font-mono text-[13px] font-medium">
                            {formatCount(linkPreviews.pending)}
                        </span>{' '}
                        waiting to be fetched
                    </Field>
                    <Field label="preview errors">
                        <span className="font-mono text-[13px] font-medium">
                            {formatCount(linkPreviews.failed)}
                        </span>{' '}
                        fetched without metadata in 24h
                    </Field>
                </dl>
            </Section>
        </>
    );
}

Show.layout = [AppLayout, OperationsLayout];
