import { Link, router, usePage, usePoll } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    ArrowUpRight,
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
import { useEffect, useRef, useState } from 'react';
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
import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import AppearanceToggleTab from '@/components/appearance-tabs';
import { ChatDock } from '@/components/chat/chat-dock';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { useRemoteAlerts } from '@/hooks/use-chat';
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

type NavEntry = {
    page: OperationsPage;
    title: string;
    description: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

function navGroups(
    hasTraffic: boolean,
): { label: string; items: NavEntry[] }[] {
    return [
        {
            label: 'Monitor',
            items: [
                {
                    page: 'operations/show',
                    title: 'Overview',
                    description: 'What needs attention, and what is fine',
                    href: OperationsController.show(),
                    icon: LayoutDashboard,
                },
                {
                    page: 'operations/health',
                    title: 'Health',
                    description: 'Services, queues, failed jobs and backlogs',
                    href: SystemHealthController.show(),
                    icon: HeartPulse,
                },
                {
                    page: 'operations/events',
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
                    page: 'operations/forecasts',
                    title: 'Forecasts',
                    description: 'Where the trends are heading next',
                    href: ForecastController.show(),
                    icon: TrendingUp,
                },
                {
                    page: 'operations/metrics',
                    title: 'Growth',
                    description: 'Acquisition, activation and retention',
                    href: ProductMetricsController.show(),
                    icon: ChartNoAxesCombined,
                },
                {
                    page: 'operations/activity',
                    title: 'Activity',
                    description: 'Commits, replies and signups over time',
                    href: SiteActivityController.show(),
                    icon: Activity,
                },
                ...(hasTraffic
                    ? [
                          {
                              page: 'operations/traffic' as const,
                              title: 'Traffic',
                              description: 'Visitors and where they come from',
                              href: TrafficController.show(),
                              icon: Globe,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: 'Control',
            items: [
                {
                    page: 'operations/features',
                    title: 'Features',
                    description: 'Turn features on and off for everyone',
                    href: FeatureController.index(),
                    icon: Flag,
                },
                {
                    page: 'operations/emails',
                    title: 'Emails',
                    description: 'Write to everyone, or the people you pick',
                    href: EmailController.create(),
                    icon: Mail,
                },
            ],
        },
    ];
}

function Poll({ only }: { only: string[] }) {
    usePoll(REFRESH_SECONDS * 1000, backgroundPollOptions({ only }));

    return null;
}

function NavItem({ item, isActive }: { item: NavEntry; isActive: boolean }) {
    return (
        <Link
            href={item.href}
            prefetch={false}
            preserveScroll={false}
            aria-current={isActive ? 'page' : undefined}
            className={cn(
                'flex h-9 shrink-0 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                isActive &&
                    'bg-primary-soft text-primary hover:bg-primary-soft hover:text-primary',
            )}
        >
            <item.icon aria-hidden className="size-4 shrink-0" />
            {item.title}
        </Link>
    );
}

function Brand() {
    return (
        <Link
            href={OperationsController.show()}
            className="flex items-center gap-2 rounded-md text-lg font-bold tracking-[-0.03em]"
            aria-label="/dev/null operations, go to the overview"
        >
            <AppLogoIcon className="size-[1.1em] fill-current" />
            <AppWordmark />
            <span className="rounded-sm border px-1.5 font-mono text-[11px] leading-5 font-medium tracking-normal text-muted-foreground">
                ops
            </span>
        </Link>
    );
}

export default function OperationsLayout({ children }: PropsWithChildren) {
    const { component, props } = usePage();
    const page = component as OperationsPage;
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isLive, setIsLive] = useState(true);
    const only = PAGE_PROPS[page] ?? [];
    const polls = !ON_DEMAND.includes(page);
    const groups = navGroups(props.hasTraffic === true);
    const items = groups.flatMap((group) => group.items);
    const current = items.find((item) => item.page === page);
    const user = props.auth.user;
    const hasRemotes = user !== null && props.auth.remotes !== null;
    const backHref =
        props.auth.log === null
            ? home().url
            : LogController.show(props.auth.log).url;
    const mobileNav = useRef<HTMLElement>(null);

    useRemoteAlerts(hasRemotes ? user.id : null);

    useEffect(() => {
        mobileNav.current
            ?.querySelector('[aria-current="page"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }, [page]);

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
        <div className="min-h-svh bg-background lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]">
            {polls && isLive && (
                <Poll key={component} only={POLL_PROPS[page] ?? []} />
            )}

            <aside className="sticky top-0 hidden h-svh flex-col border-r bg-card lg:flex">
                <div className="flex h-14 shrink-0 items-center px-4">
                    <Brand />
                </div>

                <nav
                    aria-label="Operations"
                    className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-3 py-4"
                >
                    {groups.map((group) => (
                        <div
                            key={group.label}
                            className="flex flex-col gap-0.5"
                        >
                            <p className="px-2.5 pb-1 font-mono text-xs text-muted-foreground">
                                {group.label.toLowerCase()}
                            </p>
                            {group.items.map((item) => (
                                <NavItem
                                    key={item.page}
                                    item={item}
                                    isActive={item.page === page}
                                />
                            ))}
                        </div>
                    ))}

                    <div className="flex flex-col gap-0.5">
                        <p className="px-2.5 pb-1 font-mono text-xs text-muted-foreground">
                            elsewhere
                        </p>
                        <a
                            href={ModerationController.show().url}
                            className="flex h-9 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            <ShieldCheck aria-hidden className="size-4" />
                            Moderation
                            <ArrowUpRight
                                aria-hidden
                                className="ml-auto size-3.5"
                            />
                        </a>
                    </div>
                </nav>

                <div className="space-y-3 border-t p-3">
                    <AppearanceToggleTab
                        iconOnly
                        className="grid w-full grid-cols-3 [&>button>span]:justify-center"
                    />
                    <a
                        href={backHref}
                        className="flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-sm transition-colors hover:bg-accent"
                    >
                        {user && (
                            <UserAvatar
                                user={user}
                                className="size-6"
                                pixels={48}
                            />
                        )}
                        <span className="min-w-0 flex-1">
                            <span className="block truncate font-medium">
                                Back to /dev/null
                            </span>
                            {user && (
                                <span
                                    className="block truncate font-mono text-xs text-muted-foreground"
                                    title={`@${user.username}`}
                                >
                                    @{user.username}
                                </span>
                            )}
                        </span>
                        <ArrowUpRight
                            aria-hidden
                            className="size-3.5 text-muted-foreground"
                        />
                    </a>
                </div>
            </aside>

            <div className="flex min-w-0 flex-col">
                <header className="sticky top-0 z-20 border-b bg-card/95 backdrop-blur supports-[backdrop-filter]:bg-card/85">
                    <div className="flex h-14 items-center gap-3 px-4 sm:px-6">
                        <div className="lg:hidden">
                            <Brand />
                        </div>
                        <div className="hidden min-w-0 lg:block">
                            <h1 className="truncate leading-5 font-semibold tracking-tight">
                                {current?.title ?? 'Operations'}
                            </h1>
                            <p className="truncate text-xs text-muted-foreground">
                                {current?.description}
                            </p>
                        </div>

                        <div className="ml-auto flex items-center gap-1">
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
                                        {isLive
                                            ? `${REFRESH_SECONDS}s`
                                            : 'paused'}
                                    </span>
                                    {isLive ? (
                                        <Pause
                                            aria-hidden
                                            className="size-3.5"
                                        />
                                    ) : (
                                        <Play
                                            aria-hidden
                                            className="size-3.5"
                                        />
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
                            {user && (
                                <a
                                    href={backHref}
                                    className="ml-2 flex size-8 shrink-0 items-center justify-center rounded-full lg:hidden"
                                    aria-label="Back to /dev/null"
                                    title="Back to /dev/null"
                                >
                                    <UserAvatar
                                        user={user}
                                        className="size-7"
                                        pixels={56}
                                    />
                                </a>
                            )}
                        </div>
                    </div>

                    <nav
                        ref={mobileNav}
                        aria-label="Operations"
                        className="flex scroll-px-3 scrollbar-none gap-1 overflow-x-auto border-t px-3 py-2 lg:hidden"
                    >
                        {items.map((item) => (
                            <NavItem
                                key={item.page}
                                item={item}
                                isActive={item.page === page}
                            />
                        ))}
                        <a
                            href={ModerationController.show().url}
                            className="flex h-9 shrink-0 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            <ShieldCheck aria-hidden className="size-4" />
                            Moderation
                            <ArrowUpRight aria-hidden className="size-3.5" />
                        </a>
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-5 sm:px-6 sm:py-6 [:root:has([data-chat-dock])_&]:pb-18">
                    <div className="mb-5 lg:hidden">
                        <h1 className="text-lg font-semibold tracking-tight">
                            {current?.title ?? 'Operations'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {current?.description}
                        </p>
                    </div>
                    {children}
                </main>
            </div>

            {hasRemotes && <ChatDock user={user} />}
        </div>
    );
}
