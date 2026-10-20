import { router, usePage, usePoll } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import type { PropsWithChildren } from 'react';
import FeatureController from '@/actions/App/Http/Controllers/FeatureController';
import OperationsController from '@/actions/App/Http/Controllers/OperationsController';
import ProductMetricsController from '@/actions/App/Http/Controllers/ProductMetricsController';
import SiteActivityController from '@/actions/App/Http/Controllers/SiteActivityController';
import TrafficController from '@/actions/App/Http/Controllers/TrafficController';
import { PageTabs } from '@/components/page-tabs';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { backgroundPollOptions } from '@/lib/background-poll';
import { cn } from '@/lib/utils';

const REFRESH_SECONDS = 15;

type OperationsTab =
    | 'operations/show'
    | 'operations/features'
    | 'operations/activity'
    | 'operations/traffic'
    | 'operations/metrics';

/**
 * The props each tab polls and refreshes, listed since a plain reload would skip the deferred ones.
 */
export const TAB_PROPS: Record<OperationsTab, string[]> = {
    'operations/show': [
        'connectivity',
        'githubRateLimit',
        'queues',
        'failedJobs',
        'pendingModerationCount',
        'linkPreviews',
    ],
    'operations/features': ['features'],
    'operations/activity': ['today', 'overall', 'activity', 'breakdowns'],
    'operations/traffic': ['traffic', 'trafficRange'],
    'operations/metrics': [
        'funnel',
        'activeUsers',
        'responses',
        'newcomerFollows',
        'activeUserCounts',
    ],
};

function Poll({ only }: { only: string[] }) {
    usePoll(REFRESH_SECONDS * 1000, backgroundPollOptions({ only }));

    return null;
}

export default function OperationsLayout({ children }: PropsWithChildren) {
    const { component, props } = usePage();
    const { isCurrentUrl } = useCurrentUrl();
    const [isRefreshing, setIsRefreshing] = useState(false);
    const only =
        component in TAB_PROPS ? TAB_PROPS[component as OperationsTab] : [];
    // Metrics move by the day and take a while to count, so that tab only refreshes on demand...
    const polls = component !== ('operations/metrics' satisfies OperationsTab);

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

    const tabs = [
        {
            title: 'Health',
            href: OperationsController.show(),
            isActive: isCurrentUrl(OperationsController.show()),
        },
        {
            title: 'Features',
            href: FeatureController.index(),
            isActive: isCurrentUrl(FeatureController.index()),
        },
        {
            title: 'Activity',
            href: SiteActivityController.show(),
            isActive: isCurrentUrl(SiteActivityController.show()),
        },
    ];

    if (props.hasTraffic === true) {
        tabs.push({
            title: 'Traffic',
            href: TrafficController.show(),
            isActive: isCurrentUrl(TrafficController.show()),
        });
    }

    tabs.push({
        title: 'Metrics',
        href: ProductMetricsController.show(),
        isActive: isCurrentUrl(ProductMetricsController.show()),
    });

    return (
        <>
            {polls && <Poll key={component} only={only} />}

            <div className="sticky top-0 z-10 bg-card [view-transition-class:tab-header] [view-transition-name:operations-header]">
                <div className="flex h-14 items-center gap-3 border-b px-4 sm:px-6">
                    <h1 className="font-semibold">Operations</h1>
                    <p className="ml-auto font-mono text-[13px] text-muted-foreground">
                        {polls && `every ${REFRESH_SECONDS}s`}
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
                <PageTabs label="Operations" prefetch={false} tabs={tabs} />
            </div>

            <div className="[view-transition-class:tab-content] [view-transition-name:operations-content]">
                {children}
            </div>
        </>
    );
}
