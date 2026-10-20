import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export function PageTabs({
    label,
    tabs,
    preserveScroll = false,
    prefetch = true,
    only,
    reset,
    compactOnMobile = false,
}: {
    label: string;
    compactOnMobile?: boolean;
    preserveScroll?: boolean;
    prefetch?: boolean;
    only?: InertiaLinkProps['only'];
    reset?: string[];
    tabs: {
        title: string;
        href: NonNullable<InertiaLinkProps['href']>;
        isActive: boolean;
    }[];
}) {
    const indicatorName = `tab-indicator-${label.toLowerCase().replaceAll(/[^a-z0-9]+/gu, '-')}`;

    return (
        <nav aria-label={label} className="flex overflow-x-auto border-b">
            {tabs.map((tab) => (
                <Link
                    key={tab.title}
                    href={tab.href}
                    prefetch={prefetch}
                    viewTransition
                    preserveScroll={preserveScroll}
                    only={only}
                    // Link exposes custom headers for both visits and prefetches, but no reset option.
                    headers={
                        reset?.length
                            ? { 'X-Inertia-Reset': reset.join(',') }
                            : undefined
                    }
                    aria-current={tab.isActive ? 'page' : undefined}
                    className={cn(
                        'relative flex min-w-max flex-1 justify-center font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        compactOnMobile
                            ? 'px-1.5 text-xs sm:px-4 sm:text-[15px]'
                            : 'px-4 text-[15px]',
                        { 'text-foreground': tab.isActive },
                    )}
                >
                    <span className="py-3.5">{tab.title}</span>
                    {tab.isActive && (
                        <span
                            aria-hidden
                            className="absolute inset-x-0 bottom-0 h-[3px] bg-primary [view-transition-class:tab-indicator]"
                            style={{ viewTransitionName: indicatorName }}
                        />
                    )}
                </Link>
            ))}
        </nav>
    );
}
