import { Deferred } from '@inertiajs/react';
import { Field, Panel } from '@/components/operations/section';
import { SignalIcon } from '@/components/operations/signal-icon';
import { Skeleton } from '@/components/ui/skeleton';
import { useNow } from '@/hooks/use-now';
import { formatCount } from '@/lib/numbers';
import { formatAge } from '@/lib/operations';
import { cn } from '@/lib/utils';
import type { Connectivity, GitHubRateLimit } from '@/types';

function Reachable({ isUp }: { isUp: boolean }) {
    return (
        <span
            className={cn('inline-flex h-5 items-center gap-1.5', {
                'font-medium text-destructive': !isUp,
            })}
        >
            <SignalIcon status={isUp ? 'ok' : 'fail'} />
            {isUp ? 'connected' : 'unreachable'}
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
            <div className="space-y-1.5">
                <p className="flex h-5 items-center gap-1.5 font-medium text-destructive">
                    <SignalIcon status="fail" />
                    unavailable
                </p>
                <p className="truncate text-muted-foreground">
                    GitHub didn't answer
                </p>
            </div>
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
        <div className="space-y-1.5">
            <p className="flex h-5 items-center gap-1.5 font-mono text-[13px] tabular-nums">
                <SignalIcon status={isLow ? 'warn' : 'ok'} />
                <span
                    className={cn('truncate font-medium', {
                        'text-warning': isLow,
                    })}
                >
                    {formatCount(rateLimit.remaining)}
                    <span className="font-normal text-muted-foreground">
                        {' / '}
                        {formatCount(rateLimit.limit)} left
                    </span>
                </span>
            </p>
            <div className="flex h-5 items-center gap-3">
                <div
                    role="meter"
                    aria-label="GitHub requests left this hour"
                    aria-valuemin={0}
                    aria-valuemax={rateLimit.limit}
                    aria-valuenow={rateLimit.remaining}
                    className="h-1 max-w-32 min-w-12 flex-1 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        className={cn(
                            'h-full rounded-full',
                            isLow ? 'bg-warning' : 'bg-muted-foreground',
                        )}
                        style={{ width: `${share * 100}%` }}
                    />
                </div>
                <span className="shrink-0 font-mono text-[13px] text-muted-foreground tabular-nums">
                    resets in {formatAge(resetsIn)}
                </span>
            </div>
        </div>
    );
}

function GitHubRateLimitSkeleton() {
    return (
        <div
            aria-hidden
            className="animate-in space-y-1.5 delay-300 duration-300 fill-mode-both fade-in"
        >
            <div className="flex h-5 items-center gap-1.5">
                <Skeleton className="size-4 rounded-full" />
                <Skeleton className="h-3 w-32" />
            </div>
            <div className="flex h-5 items-center gap-3">
                <Skeleton className="h-1 max-w-32 min-w-12 flex-1 rounded-full" />
                <Skeleton className="h-3 w-24" />
            </div>
        </div>
    );
}

/**
 * Whether the app can reach what it depends on: the database, the cache and, when a token is configured, GitHub's API
 * with how much of its hourly allowance is left. GitHub answers slowly, so it is deferred behind a skeleton.
 */
export function ServicesPanel({
    connectivity,
    githubRateLimit,
    tracksGitHub,
}: {
    connectivity: Connectivity;
    githubRateLimit: GitHubRateLimit | null | undefined;
    tracksGitHub: boolean;
}) {
    return (
        <Panel title="Services">
            <dl className="-m-4 divide-y text-sm">
                <Field label="database">
                    <Reachable isUp={connectivity.database} />
                </Field>
                <Field label="cache">
                    <Reachable isUp={connectivity.cache} />
                </Field>
                {tracksGitHub && (
                    <Field label="github api">
                        <Deferred
                            data="githubRateLimit"
                            fallback={<GitHubRateLimitSkeleton />}
                        >
                            <GitHubRateLimitValue rateLimit={githubRateLimit} />
                        </Deferred>
                    </Field>
                )}
            </dl>
        </Panel>
    );
}
