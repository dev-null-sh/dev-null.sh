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
import FeatureController from '@/actions/App/Http/Controllers/FeatureController';
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
    Feature,
    GitHubRateLimit,
    LinkPreviewHealth,
    QueueBacklog,
    Traffic,
    TrafficRange,
    TrafficRow,
    Volume,
} from '@/types';

const REFRESH_SECONDS = 15;

const STALE_JOB_SECONDS = 300;

const PAGE_PROPS = [
    'queues',
    'failedJobs',
    'connectivity',
    'linkPreviews',
    'pendingModerationCount',
    'volume',
    'features',
    'githubRateLimit',
    'traffic',
    'trafficRange',
];

const LEAVE_DELAY_MS = 700;

const FEATURES: Record<
    Feature['name'],
    { label: string; description: string }
> = {
    translations: {
        label: 'translations',
        description:
            'The Translate control, cached translations, and background language detection. Off stops new detection jobs from being queued and skips any already waiting.',
    },
};

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
        <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] items-start gap-3 px-4 py-2.5 leading-5">
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
            className={cn('inline-flex h-5 items-center gap-1.5', {
                'font-medium text-destructive': !isUp,
            })}
        >
            <Icon
                aria-hidden
                className={cn(
                    'size-4 shrink-0',
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

const TRAFFIC_PRESETS = [
    { label: 'today', days: 1 },
    { label: '7d', days: 7 },
    { label: '30d', days: 30 },
    { label: '90d', days: 90 },
];

const BREAKDOWNS = [
    { key: 'pages', title: 'pages', empty: '(none)' },
    { key: 'referrers', title: 'referrers', empty: 'direct' },
    { key: 'countries', title: 'countries', empty: 'unknown' },
    { key: 'devices', title: 'devices', empty: 'unknown' },
    { key: 'browsers', title: 'browsers', empty: 'unknown' },
] as const;

const regions = new Intl.DisplayNames(['en'], { type: 'region' });

function utcDate(daysAgo = 0): string {
    return new Date(Date.now() - daysAgo * 86_400_000)
        .toISOString()
        .slice(0, 10);
}

function countryName(code: string): string {
    try {
        return regions.of(code) ?? code;
    } catch {
        return code;
    }
}

function TrafficRangePicker({ range }: { range: TrafficRange }) {
    const today = utcDate();

    const select = (next: TrafficRange) =>
        router.reload({
            data: next,
            only: ['traffic', 'trafficRange'],
        });

    return (
        <div className="mb-3 flex flex-wrap items-center gap-2 font-mono text-[13px]">
            {TRAFFIC_PRESETS.map((preset) => {
                const from = utcDate(preset.days - 1);
                const isActive = range.from === from && range.to === today;

                return (
                    <Button
                        key={preset.label}
                        variant={isActive ? 'secondary' : 'ghost'}
                        size="sm"
                        className="h-7 px-2 font-mono text-[13px]"
                        aria-pressed={isActive}
                        onClick={() => select({ from, to: today })}
                    >
                        {preset.label}
                    </Button>
                );
            })}
            <span className="flex items-center gap-1.5 sm:ml-auto">
                <input
                    type="date"
                    aria-label="From"
                    value={range.from}
                    max={range.to}
                    onChange={(event) =>
                        event.target.value &&
                        select({ ...range, from: event.target.value })
                    }
                    className="h-7 rounded-md border bg-transparent px-2 text-[13px]"
                />
                <span className="text-muted-foreground">→</span>
                <input
                    type="date"
                    aria-label="To"
                    value={range.to}
                    min={range.from}
                    max={today}
                    onChange={(event) =>
                        event.target.value &&
                        select({ ...range, to: event.target.value })
                    }
                    className="h-7 rounded-md border bg-transparent px-2 text-[13px]"
                />
            </span>
        </div>
    );
}

function Breakdown({
    title,
    empty,
    rows,
    formatName = (name) => name,
}: {
    title: string;
    empty: string;
    rows: TrafficRow[];
    formatName?: (name: string) => string;
}) {
    const top = rows[0]?.visitors ?? 0;

    return (
        <div className="rounded-lg border px-4">
            <table className="w-full table-fixed text-sm">
                <thead>
                    <tr className="font-mono text-[13px] text-muted-foreground">
                        <th
                            scope="col"
                            className="py-2.5 text-left font-normal"
                        >
                            {title}
                        </th>
                        <th
                            scope="col"
                            className="w-20 py-2.5 text-right font-normal"
                        >
                            visitors
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y border-t">
                    {rows.length === 0 && (
                        <tr>
                            <td
                                colSpan={2}
                                className="py-2.5 font-mono text-[13px] text-muted-foreground"
                            >
                                nothing yet
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => {
                        const name =
                            row.name === '' ? empty : formatName(row.name);

                        return (
                            <tr key={row.name}>
                                <th
                                    scope="row"
                                    className="relative py-2.5 pr-3 text-left font-mono text-[13px] font-normal"
                                >
                                    <span
                                        aria-hidden
                                        className="absolute inset-y-1.5 left-0 -ml-2 rounded bg-muted"
                                        style={{
                                            width: `calc(${top > 0 ? (row.visitors / top) * 100 : 0}% + 0.5rem)`,
                                        }}
                                    />
                                    <span
                                        className={cn(
                                            'relative block truncate',
                                            row.name === '' &&
                                                'text-muted-foreground',
                                        )}
                                        title={name}
                                    >
                                        {name}
                                    </span>
                                </th>
                                <td className="py-2.5 text-right font-mono text-[13px] font-medium tabular-nums">
                                    {formatCount(row.visitors)}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function TrafficValue({ traffic }: { traffic: Traffic | null | undefined }) {
    if (!traffic) {
        return (
            <div className="rounded-lg border px-4 py-2.5 text-sm">
                <Status isUp={false}>
                    unavailable
                    <span className="font-normal text-muted-foreground">
                        Fathom didn't answer
                    </span>
                </Status>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <dl className="grid grid-cols-2 divide-x divide-y rounded-lg border text-sm sm:grid-cols-4 sm:divide-y-0">
                {[
                    ['visitors', formatCount(traffic.totals.visitors)],
                    ['pageviews', formatCount(traffic.totals.pageviews)],
                    ['avg time', formatAge(traffic.totals.avg_duration)],
                    [
                        'bounce rate',
                        `${Math.round(traffic.totals.bounce_rate * 100)}%`,
                    ],
                ].map(([label, value]) => (
                    <div key={label} className="px-4 py-2.5">
                        <dt className="font-mono text-[13px] text-muted-foreground">
                            {label}
                        </dt>
                        <dd className="font-mono text-base font-medium tabular-nums">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            <div className="grid gap-3 sm:grid-cols-2">
                {BREAKDOWNS.map((breakdown) => (
                    <Breakdown
                        key={breakdown.key}
                        title={breakdown.title}
                        empty={breakdown.empty}
                        rows={traffic.breakdowns[breakdown.key]}
                        formatName={
                            breakdown.key === 'countries'
                                ? countryName
                                : undefined
                        }
                    />
                ))}
            </div>
        </div>
    );
}

function TrafficSkeleton() {
    return (
        <div
            aria-hidden
            className="animate-in space-y-3 delay-300 duration-300 fill-mode-both fade-in"
        >
            <Skeleton className="h-16 w-full rounded-lg" />
            <div className="grid gap-3 sm:grid-cols-2">
                {BREAKDOWNS.map((breakdown) => (
                    <Skeleton
                        key={breakdown.key}
                        className="h-48 w-full rounded-lg"
                    />
                ))}
            </div>
        </div>
    );
}

function FeatureRow({ feature }: { feature: Feature }) {
    const [processing, setProcessing] = useState(false);
    const labelId = useId();
    const descriptionId = useId();
    const { label, description } = FEATURES[feature.name];

    const toggle = () => {
        if (processing) {
            return;
        }

        const enabled = !feature.enabled;

        router.put(
            FeatureController.update.url(feature.name),
            { enabled },
            {
                only: ['features'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () =>
                    toast(
                        <span className="font-mono text-[13px]">
                            {enabled
                                ? `Switched ${label} on for everyone`
                                : `Switched ${label} off for everyone`}
                        </span>,
                    ),
                onError: () =>
                    toast.error(
                        <span className="font-mono text-[13px]">
                            fatal: could not switch {label}{' '}
                            {enabled ? 'on' : 'off'}
                        </span>,
                    ),
            },
        );
    };

    return (
        <li
            className="flex items-start justify-between gap-6 px-4 py-3"
            data-test="feature"
        >
            <div className="min-w-0 space-y-1">
                <p id={labelId} className="font-mono text-[13px] font-medium">
                    {label}
                </p>
                <p id={descriptionId} className="text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={feature.enabled}
                aria-labelledby={labelId}
                aria-describedby={descriptionId}
                aria-disabled={processing}
                onClick={toggle}
                className={cn(
                    'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    feature.enabled ? 'bg-primary' : 'bg-muted-foreground/30',
                )}
                data-test={`feature-${feature.name}-switch`}
            >
                <span
                    className={cn(
                        'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                        feature.enabled && 'translate-x-5',
                    )}
                />
            </button>
        </li>
    );
}

function FailedJobRow({
    failedJob,
    hasReplacement,
}: {
    failedJob: FailedJob;
    hasReplacement: boolean;
}) {
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
                        only: PAGE_PROPS,
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

export default function Show({
    queues,
    failedJobs,
    connectivity,
    linkPreviews,
    pendingModerationCount,
    volume,
    features,
    githubRateLimit,
    traffic,
    trafficRange,
}: {
    queues: QueueBacklog[];
    failedJobs: FailedJobs;
    connectivity: Connectivity;
    linkPreviews: LinkPreviewHealth;
    pendingModerationCount: number;
    volume: Volume;
    features: Feature[];
    githubRateLimit?: GitHubRateLimit | null;
    traffic?: Traffic | null;
    trafficRange?: TrafficRange;
}) {
    const { deferredProps } = usePage();
    const [isRefreshing, setIsRefreshing] = useState(false);

    usePoll(REFRESH_SECONDS * 1000, { only: PAGE_PROPS });

    const refresh = () => {
        if (isRefreshing) {
            return;
        }

        const startedAt = Date.now();

        router.reload({
            only: PAGE_PROPS,
            onStart: () => setIsRefreshing(true),
            onFinish: () => {
                const elapsed = Date.now() - startedAt;

                window.setTimeout(
                    () => setIsRefreshing(false),
                    Math.ceil(elapsed / 1000) * 1000 - elapsed,
                );
            },
        });
    };

    const isDeferred = (prop: string) =>
        Object.values(deferredProps ?? {})
            .flat()
            .includes(prop);

    const tracksGitHub =
        githubRateLimit !== undefined || isDeferred('githubRateLimit');

    const tracksTraffic = traffic !== undefined || isDeferred('traffic');

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
                    aria-disabled={isRefreshing}
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

            <Section
                title="Features"
                meta={`${formatCount(features.filter((feature) => !feature.enabled).length)} off`}
            >
                <ul className="divide-y rounded-lg border">
                    {features.map((feature) => (
                        <FeatureRow key={feature.name} feature={feature} />
                    ))}
                </ul>
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
                    <table className="w-full table-fixed text-sm">
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
                                    className="w-20 py-2.5 text-right font-normal sm:w-24"
                                >
                                    24h
                                </th>
                                <th
                                    scope="col"
                                    className="w-20 py-2.5 text-right font-normal sm:w-24"
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

            {tracksTraffic && (
                <Section
                    title="Traffic"
                    meta={
                        traffic
                            ? `${formatCount(traffic.current)} on the site now`
                            : 'fathom'
                    }
                >
                    {trafficRange && (
                        <TrafficRangePicker range={trafficRange} />
                    )}
                    <Deferred data="traffic" fallback={<TrafficSkeleton />}>
                        <TrafficValue traffic={traffic} />
                    </Deferred>
                </Section>
            )}
        </>
    );
}

Show.layout = AppLayout;
