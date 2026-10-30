import { Head, Link } from '@inertiajs/react';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import { FailedJobsPanel } from '@/components/operations/health-failed-jobs';
import { FailuresPanel } from '@/components/operations/health-failures';
import { ServicesPanel } from '@/components/operations/health-services';
import { Field, Panel } from '@/components/operations/section';
import { SignalIcon } from '@/components/operations/signal-icon';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import { formatAge } from '@/lib/operations';
import { cn } from '@/lib/utils';
import type {
    FailedJobs,
    FailureTrend,
    Connectivity,
    GitHubRateLimit,
    LinkPreviewHealth,
    ModerationBacklog,
    QueueBacklog,
    Runtime,
} from '@/types';

const STALE_JOB_SECONDS = 300;

function Count({ value }: { value: number }) {
    return (
        <span className="font-mono text-[13px] font-medium tabular-nums">
            {formatCount(value)}
        </span>
    );
}

function QueuesPanel({
    queues,
    driver,
}: {
    queues: QueueBacklog[];
    driver: string;
}) {
    return (
        <Panel
            title="Queues"
            meta={`${formatCount(queues.reduce((total, queue) => total + queue.size, 0))} waiting · ${driver}`}
        >
            <table className="-m-4 w-[calc(100%+2rem)] table-fixed text-sm">
                <thead>
                    <tr className="font-mono text-[13px] text-muted-foreground">
                        <th
                            scope="col"
                            className="py-2.5 pl-4 text-left font-normal"
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
                            className="w-28 py-2.5 pr-4 text-right font-normal"
                        >
                            oldest
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y border-t">
                    {queues.map((queue) => {
                        const isStale =
                            (queue.oldest_job_age ?? 0) >= STALE_JOB_SECONDS;

                        return (
                            <tr key={queue.queue}>
                                <th
                                    scope="row"
                                    className="truncate py-2.5 pr-3 pl-4 text-left font-mono text-[13px] font-normal"
                                    title={queue.queue}
                                >
                                    {queue.queue}
                                </th>
                                <td className="py-2.5 text-right font-mono text-[13px] font-medium tabular-nums">
                                    {formatCount(queue.size)}
                                </td>
                                <td
                                    className={cn(
                                        'py-2.5 pr-4 text-right font-mono text-[13px] tabular-nums',
                                        isStale
                                            ? 'font-medium text-destructive'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    <span className="inline-flex items-center justify-end gap-1.5">
                                        {isStale && (
                                            <SignalIcon status="fail" />
                                        )}
                                        {queue.oldest_job_age === null
                                            ? '–'
                                            : formatAge(queue.oldest_job_age)}
                                    </span>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </Panel>
    );
}

function BacklogsPanel({
    moderation,
    linkPreviews,
}: {
    moderation: ModerationBacklog;
    linkPreviews: LinkPreviewHealth;
}) {
    return (
        <Panel title="Backlogs">
            <dl className="-m-4 divide-y text-sm">
                <Field label="moderation">
                    <span className="flex flex-wrap items-baseline gap-x-3">
                        <span>
                            <Count value={moderation.pending} />{' '}
                            {moderation.pending === 1
                                ? 'commit waiting'
                                : 'commits waiting'}
                        </span>
                        {moderation.oldest_pending_age !== null && (
                            <span className="font-mono text-[13px] text-muted-foreground tabular-nums">
                                oldest{' '}
                                {formatAge(moderation.oldest_pending_age)}
                            </span>
                        )}
                        {moderation.pending > 0 && (
                            <Link
                                href={ModerationController.show()}
                                className="rounded-sm text-primary underline-offset-4 hover:underline"
                            >
                                Review
                            </Link>
                        )}
                    </span>
                </Field>
                <Field label="reports">
                    <Count value={moderation.open_reports} /> open
                </Field>
                <Field label="timeouts">
                    <Count value={moderation.active_timeouts} /> active
                </Field>
                <Field label="link previews">
                    <Count value={linkPreviews.pending} /> waiting to be fetched
                </Field>
                <Field label="preview errors">
                    <Count value={linkPreviews.failed} /> fetched without
                    metadata in 24h
                </Field>
            </dl>
        </Panel>
    );
}

function RuntimePanel({ runtime }: { runtime: Runtime }) {
    const rows: [string, string][] = [
        ['environment', runtime.environment],
        ['php', runtime.php],
        ['laravel', runtime.laravel],
        ['database', runtime.database],
        ['cache', runtime.cache],
        ['queue', runtime.queue],
    ];

    return (
        <Panel title="Runtime">
            <dl className="-m-4 divide-y text-sm">
                {rows.map(([label, value]) => (
                    <Field key={label} label={label}>
                        <span
                            className="block truncate font-mono text-[13px]"
                            title={value}
                        >
                            {value}
                        </span>
                    </Field>
                ))}
            </dl>
        </Panel>
    );
}

export default function Health({
    queues,
    failedJobs,
    failureTrend,
    connectivity,
    linkPreviews,
    moderation,
    runtime,
    githubRateLimit,
}: {
    queues: QueueBacklog[];
    failedJobs: FailedJobs;
    failureTrend: FailureTrend;
    connectivity: Connectivity;
    linkPreviews: LinkPreviewHealth;
    moderation: ModerationBacklog;
    runtime: Runtime;
    githubRateLimit?: GitHubRateLimit | null;
}) {
    return (
        <>
            <Head title="Health" />

            <div className="grid grid-flow-row-dense gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <ServicesPanel
                    connectivity={connectivity}
                    githubRateLimit={githubRateLimit}
                />
                <QueuesPanel queues={queues} driver={runtime.queue} />
                <BacklogsPanel
                    moderation={moderation}
                    linkPreviews={linkPreviews}
                />
                <FailuresPanel
                    failureTrend={failureTrend}
                    className="lg:col-span-2"
                />
                <FailedJobsPanel
                    failedJobs={failedJobs}
                    className="lg:col-span-2 xl:col-span-3"
                />
                <RuntimePanel runtime={runtime} />
            </div>
        </>
    );
}

Health.layout = OperationsLayout;
