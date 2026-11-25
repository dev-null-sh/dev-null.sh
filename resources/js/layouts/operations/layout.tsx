import { router, usePage, usePoll } from '@inertiajs/react';
import {
    Activity,
    ChartNoAxesCombined,
    Flag,
    Gauge,
    Globe,
    HeartPulse,
    LayoutDashboard,
    Mail,
    Pause,
    Play,
    RefreshCw,
    ScrollText,
    ShieldCheck,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import type { PropsWithChildren } from 'react';
import EmailController from '@/actions/App/Http/Controllers/EmailController';
import FeatureController from '@/actions/App/Http/Controllers/FeatureController';
import ForecastController from '@/actions/App/Http/Controllers/ForecastController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import OperationsController from '@/actions/App/Http/Controllers/OperationsController';
import OperationsEventController from '@/actions/App/Http/Controllers/OperationsEventController';
import ProductMetricsController from '@/actions/App/Http/Controllers/ProductMetricsController';
import SiteActivityController from '@/actions/App/Http/Controllers/SiteActivityController';
import SystemHealthController from '@/actions/App/Http/Controllers/SystemHealthController';
import TrafficController from '@/actions/App/Http/Controllers/TrafficController';
import { ChatDock } from '@/components/chat/chat-dock';
import { Button } from '@/components/ui/button';
import { useRemoteAlerts, useRemoteUpdates } from '@/hooks/use-chat';
import ConsoleLayout from '@/layouts/console/layout';
import type { ConsoleNavGroup } from '@/layouts/console/layout';
import { backgroundPollOptions } from '@/lib/background-poll';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

const REFRESH_SECONDS = 15;

export type OperationsPage =
    | 'operations/show'
    | 'operations/health'
    | 'operations/events'
    | 'operations/forecasts'
    | 'operations/metrics'
    | 'operations/activity'
    | 'operations/traffic'
    | 'operations/features'
    | 'operations/emails';

/**
 * The props each page polls and refreshes, listed since a plain reload would skip the deferred ones. Pages that move
 * by the day, and take a while to count, are left out of polling and only refresh on demand.
 */
export const PAGE_PROPS: Record<OperationsPage, string[]> = {
    'operations/show': ['signals', 'daily', 'forecasts', 'recentEvents'],
    'operations/health': [
        'connectivity',
        'githubRateLimit',
        'queues',
        'failedJobs',
        'failureTrend',
        'moderation',
        'linkPreviews',
    ],
    'operations/events': ['events', 'counts', 'histogram'],
    'operations/forecasts': ['forecasts', 'milestones', 'month'],
    'operations/metrics': [
        'funnel',
        'activeUsers',
        'responses',
        'newcomerFollows',
        'activeUserCounts',
        'appUsage',
        'retentionByDay',
        'retentionCohorts',
    ],
    'operations/activity': ['today', 'overall', 'activity', 'breakdowns'],
    'operations/traffic': ['traffic', 'trafficRange'],
    'operations/features': ['features'],
    'operations/emails': ['recipients'],
};

const ON_DEMAND: OperationsPage[] = [
    'operations/forecasts',
    'operations/metrics',
    'operations/emails',
];

/**
 * What each page polls: everything it refreshes except forecasts, which only move by the day.
 */
const POLL_PROPS = Object.fromEntries(
    Object.entries(PAGE_PROPS).map(([page, props]) => [
        page,
        props.filter((prop) => prop !== 'forecasts'),
    ]),
) as Record<OperationsPage, string[]>;

function navGroups(): ConsoleNavGroup[] {
    return [
        {
            label: 'Monitor',
            items: [
                {
                    key: 'operations/show',
                    title: 'Overview',
                    description: 'What needs attention, and what is fine',
                    href: OperationsController.show(),
                    icon: LayoutDashboard,
                },
                {
                    key: 'operations/health',
                    title: 'Health',
                    description: 'Services, queues, failed jobs and backlogs',
                    href: SystemHealthController.show(),
                    icon: HeartPulse,
                },
                {
                    key: 'operations/events',
                    title: 'Events',
                    description: 'Everything that happened, newest first',
                    href: OperationsEventController.index(),
                    icon: ScrollText,
                },
            ],
        },
        {
            label: 'Product',
            items: [
                {
                    key: 'operations/forecasts',
                    title: 'Forecasts',
                    description: 'Where the trends are heading next',
                    href: ForecastController.show(),
                    icon: TrendingUp,
                },
                {
                    key: 'operations/metrics',
                    title: 'Growth',
                    description: 'Acquisition, activation and retention',
                    href: ProductMetricsController.show(),
                    icon: ChartNoAxesCombined,
                },
                {
                    key: 'operations/activity',
                    title: 'Activity',
                    description: 'Commits, replies and signups over time',
                    href: SiteActivityController.show(),
                    icon: Activity,
                },
                {
                    key: 'operations/traffic',
                    title: 'Traffic',
                    description: 'Visitors and where they come from',
                    href: TrafficController.show(),
                    icon: Globe,
                },
            ],
        },
        {
            label: 'Control',
            items: [
                {
                    key: 'operations/features',
                    title: 'Features',
                    description: 'Choose who can use each feature',
                    href: FeatureController.index(),
                    icon: Flag,
                },
                {
                    key: 'operations/emails',
                    title: 'Emails',
                    description: 'Write to everyone, or the people you pick',
                    href: EmailController.create(),
                    icon: Mail,
                },
            ],
        },
        {
            label: 'Elsewhere',
            items: [
                {
                    key: 'moderation',
                    title: 'Moderation',
                    description: 'Commits waiting for review',
                    href: ModerationController.show().url,
                    icon: ShieldCheck,
                    external: true,
                },
            ],
        },
    ];
}

function Poll({ only }: { only: string[] }) {
    usePoll(REFRESH_SECONDS * 1000, backgroundPollOptions({ only }));

    return null;
}

export default function OperationsLayout({ children }: PropsWithChildren) {
    const { component, props } = usePage();
    const page = component as OperationsPage;
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isLive, setIsLive] = useState(true);
    const only = PAGE_PROPS[page] ?? [];
    const polls = !ON_DEMAND.includes(page);
    const user = props.auth.user;
    const hasRemotes = user !== null && props.auth.remotes !== null;
    const backHref =
        props.auth.log === null
            ? home().url
            : LogController.show(props.auth.log).url;

    useRemoteAlerts(hasRemotes ? user.id : null);
    useRemoteUpdates(hasRemotes ? user.id : null);

    const refresh = () => {
        if (isRefreshing) {
            return;
        }

        const startedAt = Date.now();

        router.reload({
            only,
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

    return (
        <>
            {polls && isLive && (
                <Poll key={component} only={POLL_PROPS[page] ?? []} />
            )}

            <ConsoleLayout
                chip="ops"
                label="/dev/null operations, go to the overview"
                navLabel="Operations"
                brandHref={OperationsController.show()}
                groups={navGroups()}
                current={page}
                backHref={backHref}
                user={user}
                headerActions={
                    <>
                        {polls ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 gap-2 px-2 font-mono text-[13px] text-muted-foreground"
                                onClick={() => setIsLive(!isLive)}
                                aria-pressed={isLive}
                                title={
                                    isLive
                                        ? `Refreshing every ${REFRESH_SECONDS}s. Pause`
                                        : 'Paused. Resume refreshing'
                                }
                                data-test="toggle-live-operations-button"
                            >
                                <span
                                    aria-hidden
                                    className={cn(
                                        'size-2 rounded-full',
                                        isLive
                                            ? 'bg-success motion-safe:animate-pulse'
                                            : 'bg-rail',
                                    )}
                                />
                                <span className="w-[6ch] text-left tabular-nums">
                                    {isLive ? `${REFRESH_SECONDS}s` : 'paused'}
                                </span>
                                {isLive ? (
                                    <Pause aria-hidden className="size-3.5" />
                                ) : (
                                    <Play aria-hidden className="size-3.5" />
                                )}
                            </Button>
                        ) : (
                            <span className="flex items-center gap-1.5 px-2 font-mono text-[13px] whitespace-nowrap text-muted-foreground">
                                <Gauge aria-hidden className="size-3.5" />
                                on demand
                            </span>
                        )}
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
                    </>
                }
            >
                {children}
            </ConsoleLayout>

            {hasRemotes && <ChatDock user={user} />}
        </>
    );
}
