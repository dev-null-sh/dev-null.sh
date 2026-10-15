import {
    Deferred,
    Head,
    Link,
    router,
    usePage,
    usePoll,
} from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    RefreshCw,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useId, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import FailedJobController from '@/actions/App/Http/Controllers/FailedJobController';
import FailedJobRetryController from '@/actions/App/Http/Controllers/FailedJobRetryController';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import { EmptyFeed } from '@/components/commit-feed';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import { cn, formatFullDateTime, formatRelativeTime } from '@/lib/utils';
import type {
    Connectivity,
    FailedJob,
    FailedJobs,
    GitHubRateLimit,
    LinkPreviewHealth,
    QueueBacklog,
    Volume,
} from '@/types';

const REFRESH_SECONDS = 15;

const STALE_JOB_SECONDS = 300;

function formatCount(count: number): string {
    return count.toLocaleString('en-US');
}

function formatAge(seconds: number): string {
    if (seconds < 60) {
        return `${seconds}s`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
    }

    return `${Math.floor(seconds / 86400)}d ${Math.floor((seconds % 86400) / 3600)}h`;
}

function classBasename(name: string): string {
    return name.split('\\').pop() ?? name;
}

function Section({
    title,
    meta,
    children,
}: {
    title: string;
    meta?: ReactNode;
    children: ReactNode;
}) {
    const id = useId();

    return (
        <section aria-labelledby={id} className="border-b px-4 py-5 sm:px-6">
            <div className="mb-3 flex items-baseline justify-between gap-3">
                <h2 id={id} className="font-semibold tracking-tight">
                    {title}
                </h2>
                {meta && (
                    <p className="font-mono text-[13px] text-muted-foreground">
                        {meta}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] items-baseline gap-3 px-4 py-2.5">
            <dt className="font-mono text-[13px] text-muted-foreground">
                {label}
            </dt>
            <dd className="min-w-0">{children}</dd>
        </div>
    );
}

function Status({ isUp, children }: { isUp: boolean; children: ReactNode }) {
    const Icon = isUp ? CircleCheck : CircleAlert;

    return (
        <span
            className={cn('inline-flex items-center gap-1.5', {
                'font-medium text-destructive': !isUp,
            })}
        >
            <Icon
                aria-hidden
                className={cn(
                    'size-4 shrink-0 self-center',
                    isUp && 'text-muted-foreground',
                )}
            />
            {children}
        </span>
    );
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
            <p className="flex flex-wrap items-baseline gap-x-2">
                <span
                    className={cn('font-mono text-[13px] font-medium', {
                        'text-destructive': isLow,
                    })}
                >
                    {formatCount(rateLimit.remaining)}
                    <span className="font-normal text-muted-foreground">
                        {' / '}
                        {formatCount(rateLimit.limit)} left
                    </span>
                </span>
                <span className="font-mono text-[13px] text-muted-foreground">
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

function FailedJobRow({ failedJob }: { failedJob: FailedJob }) {
    const now = useNow();
    const [isConfirmingForget, setIsConfirmingForget] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);
    const name = classBasename(failedJob.name);

    const retry = () =>
        router.visit(FailedJobRetryController.store(failedJob.uuid), {
            preserveScroll: true,
            onStart: () => setIsProcessing(true),
            onFinish: () => setIsProcessing(false),
            onSuccess: () =>
                toast(
                    <span className="font-mono text-[13px]">
                        Pushed {name} back onto {failedJob.queue}
                    </span>,
                ),
            onError: () =>
                toast.error(
                    <span className="font-mono text-[13px]">
                        fatal: could not retry {name}
                    </span>,
                ),
        });

    const forget = () =>
        router.visit(FailedJobController.destroy(failedJob.uuid), {
            preserveScroll: true,
            onStart: () => setIsProcessing(true),
            onFinish: () => setIsProcessing(false),
            onSuccess: () =>
                toast(
                    <span className="font-mono text-[13px]">
                        rm {name} → /dev/null
                    </span>,
                ),
            onError: () =>
                toast.error(
                    <span className="font-mono text-[13px]">
                        fatal: could not forget {name}
                    </span>,
                ),
        });

    return (
        <li className="px-4 py-3" data-test="failed-job">
            <p className="flex flex-wrap items-baseline gap-x-2">
                <span className="font-medium" title={failedJob.name}>
                    {name}
                </span>
                <span className="font-mono text-[13px] text-muted-foreground">
                    {failedJob.queue} ·{' '}
                    <time
                        dateTime={failedJob.failed_at}
                        title={formatFullDateTime(failedJob.failed_at)}
                    >
                        {formatRelativeTime(failedJob.failed_at, now)}
                    </time>
                </span>
            </p>
            <p
                className="mt-1 line-clamp-2 font-mono text-xs leading-5 [overflow-wrap:anywhere] text-muted-foreground"
                title={failedJob.exception}
            >
                {failedJob.exception}
            </p>

            {isConfirmingForget ? (
                <div
                    role="group"
                    aria-label={`Forget ${name}`}
                    className="mt-2.5 flex flex-wrap items-center gap-2 rounded-md bg-destructive/8 px-3 py-2"
                >
                    <p className="mr-auto text-sm">
                        Forget this job? It won't run again.
                    </p>
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={isProcessing}
                        onClick={() => setIsConfirmingForget(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        disabled={isProcessing}
                        onClick={forget}
                        autoFocus
                        data-test="confirm-forget-failed-job-button"
                    >
                        Forget
                    </Button>
                </div>
            ) : (
                <div className="mt-2.5 flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={isProcessing}
                        onClick={retry}
                        data-test="retry-failed-job-button"
                    >
                        <RotateCcw />
                        {isProcessing ? 'Retrying…' : 'Retry'}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={isProcessing}
                        onClick={() => setIsConfirmingForget(true)}
                        className="text-muted-foreground hover:text-destructive"
                        data-test="forget-failed-job-button"
                    >
                        <Trash2 />
                        Forget
                    </Button>
                </div>
            )}
        </li>
    );
}

export default function Show({
    queues,
    failedJobs,
    connectivity,
    linkPreviews,
    pendingModerationCount,
    volume,
    githubRateLimit,
}: {
    queues: QueueBacklog[];
    failedJobs: FailedJobs;
    connectivity: Connectivity;
    linkPreviews: LinkPreviewHealth;
    pendingModerationCount: number;
    volume: Volume;
    githubRateLimit?: GitHubRateLimit | null;
}) {
    const { deferredProps } = usePage();
    const [isRefreshing, setIsRefreshing] = useState(false);

    usePoll(REFRESH_SECONDS * 1000);

    const refresh = () =>
        router.reload({
            onStart: () => setIsRefreshing(true),
            onFinish: () => setIsRefreshing(false),
        });

    const tracksGitHub =
        githubRateLimit !== undefined ||
        Object.values(deferredProps ?? {})
            .flat()
            .includes('githubRateLimit');

    const volumeRows = [
        { label: 'signups', counts: volume.signups },
        { label: 'commits', counts: volume.commits },
        { label: 'replies', counts: volume.replies },
    ];

    return (
        <>
            <Head title="Operations" />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Operations</h1>
                <p className="ml-auto font-mono text-[13px] text-muted-foreground">
                    every {REFRESH_SECONDS}s
                </p>
                <Button
                    variant="ghost"
                    size="icon"
                    className="-mr-2 size-8 text-muted-foreground"
                    onClick={refresh}
                    disabled={isRefreshing}
                    aria-label="Refresh now"
                    title="Refresh now"
                    data-test="refresh-operations-button"
                >
                    <RefreshCw
                        className={cn(
                            isRefreshing &&
                                'animate-spin motion-reduce:animate-none',
                        )}
                    />
                </Button>
            </div>

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
                                fallback={
                                    <Skeleton className="h-4 w-44 bg-muted" />
                                }
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
                    <table className="w-full text-sm">
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
                                    className="py-2.5 text-right font-normal"
                                >
                                    waiting
                                </th>
                                <th
                                    scope="col"
                                    className="w-24 py-2.5 text-right font-normal"
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
                                            className="max-w-0 truncate py-2.5 pr-3 text-left font-mono text-[13px] font-normal"
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
                                />
                            ))}
                        </ul>
                        {failedJobs.total > failedJobs.recent.length && (
                            <p className="mt-3 text-sm text-muted-foreground">
                                Showing the {failedJobs.recent.length} most
                                recent of {formatCount(failedJobs.total)}.
                            </p>
                        )}
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

            <Section title="Volume">
                <div className="rounded-lg border px-4">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="font-mono text-[13px] text-muted-foreground">
                                <th
                                    scope="col"
                                    className="py-2.5 text-left font-normal"
                                >
                                    <span className="sr-only">What</span>
                                </th>
                                <th
                                    scope="col"
                                    className="w-24 py-2.5 text-right font-normal"
                                >
                                    24h
                                </th>
                                <th
                                    scope="col"
                                    className="w-24 py-2.5 text-right font-normal"
                                >
                                    7d
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y border-t">
                            {volumeRows.map((row) => (
                                <tr key={row.label}>
                                    <th
                                        scope="row"
                                        className="py-2.5 text-left font-mono text-[13px] font-normal text-muted-foreground"
                                    >
                                        {row.label}
                                    </th>
                                    <td className="py-2.5 text-right font-mono text-[13px] font-medium tabular-nums">
                                        {formatCount(row.counts.day)}
                                    </td>
                                    <td className="py-2.5 text-right font-mono text-[13px] tabular-nums">
                                        {formatCount(row.counts.week)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Section>
        </>
    );
}

Show.layout = AppLayout;
