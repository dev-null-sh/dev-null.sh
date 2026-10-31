import { router } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import { toast } from 'sonner';
import FailedJobController from '@/actions/App/Http/Controllers/FailedJobController';
import FailedJobRetryController from '@/actions/App/Http/Controllers/FailedJobRetryController';
import FailedJobsController from '@/actions/App/Http/Controllers/FailedJobsController';
import { EmptyFeed } from '@/components/commit-feed';
import { Panel } from '@/components/operations/section';
import { Button } from '@/components/ui/button';
import { useDateTime } from '@/hooks/use-date-time';
import { useListSlide } from '@/hooks/use-list-slide';
import { useNow } from '@/hooks/use-now';
import { PAGE_PROPS } from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type { FailedJob, FailedJobs } from '@/types';

const LEAVE_DELAY_MS = 700;

export function classBasename(name: string): string {
    return name.split('\\').pop() ?? name;
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
    const forgetButton = useRef<HTMLButtonElement>(null);
    const name = classBasename(failedJob.name);
    const isLeaving = state === 'retrying' || state === 'forgetting';

    const cancel = () => {
        flushSync(() => setState('idle'));
        forgetButton.current?.focus();
    };

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
                        only: PAGE_PROPS['operations/health'],
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
                isLeaving && hasReplacement && 'opacity-0 delay-400',
            )}
            data-test="failed-job"
        >
            <div className="min-h-0 overflow-hidden">
                <div className="px-4 py-3 md:flex md:items-center md:gap-6">
                    <div className="min-w-0 md:flex-1">
                        <p className="flex items-baseline gap-2 whitespace-nowrap">
                            <span
                                className="min-w-0 truncate font-medium"
                                title={failedJob.name}
                            >
                                {name}
                            </span>
                            <span className="flex shrink-3 items-baseline font-mono text-[13px] text-muted-foreground">
                                <span
                                    className="truncate"
                                    title={failedJob.queue}
                                >
                                    {failedJob.queue}
                                </span>
                                <span aria-hidden className="shrink-0 px-1.5">
                                    ·
                                </span>
                                <time
                                    className="shrink-0 tabular-nums"
                                    dateTime={failedJob.failed_at}
                                    title={formatFullDateTime(
                                        failedJob.failed_at,
                                    )}
                                >
                                    {formatRelativeTime(
                                        failedJob.failed_at,
                                        now,
                                    )}
                                </time>
                            </span>
                        </p>
                        <p
                            className="mt-1 line-clamp-2 min-h-10 font-mono text-xs leading-5 wrap-anywhere text-muted-foreground"
                            title={failedJob.exception}
                        >
                            {failedJob.exception}
                        </p>
                    </div>

                    <div className="mt-2 flex h-9 items-center gap-2 md:mt-0 md:w-72 md:shrink-0 md:justify-end">
                        {isLeaving && (
                            <p
                                role="status"
                                className="min-w-0 truncate font-mono text-[13px] text-muted-foreground"
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
                                        cancel();
                                    }
                                }}
                                className="flex h-full min-w-0 flex-1 items-center gap-1 rounded-md bg-destructive/8 pr-1 pl-3"
                            >
                                <p className="mr-auto min-w-0 truncate text-sm">
                                    Forget this job?
                                    <span className="hidden sm:inline md:hidden">
                                        {' '}
                                        It won't run again.
                                    </span>
                                </p>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7"
                                    autoFocus
                                    onClick={cancel}
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
                                    ref={forgetButton}
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

function ForgetAllFailedJobs({
    shown,
    total,
}: {
    shown: number;
    total: number;
}) {
    const [state, setState] = useState<'idle' | 'confirming' | 'forgetting'>(
        'idle',
    );
    const forgetAllButton = useRef<HTMLButtonElement>(null);

    const cancel = () => {
        flushSync(() => setState('idle'));
        forgetAllButton.current?.focus();
    };

    const forget = () => {
        setState('forgetting');

        router.visit(FailedJobsController.destroy(), {
            only: PAGE_PROPS['operations/health'],
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
                        cancel();
                    }
                }}
                className="flex h-full min-w-0 flex-1 items-center gap-1 rounded-md bg-destructive/8 pr-1 pl-3"
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
                    onClick={cancel}
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
        <>
            {total > shown && (
                <p className="min-w-0 truncate text-sm text-muted-foreground">
                    The {shown} most recent of {formatCount(total)}.
                </p>
            )}
            <Button
                ref={forgetAllButton}
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
        </>
    );
}

export function FailedJobsPanel({
    failedJobs,
    className,
}: {
    failedJobs: FailedJobs;
    className?: string;
}) {
    const hasReplacement = failedJobs.total > failedJobs.recent.length;
    const list = useListSlide<HTMLUListElement>(
        failedJobs.recent.map((failedJob) => failedJob.uuid),
    );

    return (
        <Panel
            title="Failed jobs"
            meta={`${formatCount(failedJobs.total)} total`}
            className={className}
        >
            {failedJobs.recent.length === 0 ? (
                <div className="py-6 text-center">
                    <EmptyFeed command="$ php artisan queue:failed">
                        No failed jobs. Everything ran.
                    </EmptyFeed>
                </div>
            ) : (
                <>
                    <ul
                        ref={list}
                        className="relative -mx-4 -mt-4 divide-y border-b"
                    >
                        {failedJobs.recent.map((failedJob) => (
                            <FailedJobRow
                                key={failedJob.uuid}
                                failedJob={failedJob}
                                hasReplacement={hasReplacement}
                            />
                        ))}
                    </ul>
                    <div className="mt-3 -mb-1 flex h-9 items-center gap-3">
                        <ForgetAllFailedJobs
                            shown={failedJobs.recent.length}
                            total={failedJobs.total}
                        />
                    </div>
                </>
            )}
        </Panel>
    );
}
