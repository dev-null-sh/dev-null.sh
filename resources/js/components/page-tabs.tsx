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
    onSelect,
}: {
    label: string;
    preserveScroll?: boolean;
    prefetch?: boolean;
    only?: InertiaLinkProps['only'];
    reset?: string[];
    /**
     * Shows the tab as selected the moment its visit starts, before its data arrives. A page that does this animates
     * the selection itself, so the visit skips the page transition.
     */
    onSelect?: (index: number) => void;
    tabs: {
        title: string;
        href: NonNullable<InertiaLinkProps['href']>;
        isActive: boolean;
    }[];
}) {
    const indicatorName = `tab-indicator-${label.toLowerCase().replaceAll(/[^a-z0-9]+/gu, '-')}`;

    return (
        <nav aria-label={label} className="flex border-b">
            {tabs.map((tab, index) => (
                <Link
                    key={tab.title}
                    href={tab.href}
                    prefetch={prefetch}
                    viewTransition={onSelect === undefined}
                    onBefore={() => onSelect?.(index)}
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
                        'relative flex flex-1 justify-center text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
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
