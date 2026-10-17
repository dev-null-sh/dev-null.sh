import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * The indicator's view transition name comes from `name`, falling back to the label. Pass `name` when the label is
 * translated, so the name stays the same in every language.
 */
export function PageTabs({
    label,
    name = label,
    tabs,
    preserveScroll = false,
    prefetch = true,
    only,
    reset,
}: {
    label: string;
    name?: string;
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
    const indicatorName = `tab-indicator-${name.toLowerCase().replaceAll(/[^a-z0-9]+/gu, '-')}`;

    return (
        <nav aria-label={label} className="flex border-b">
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
                        'flex flex-1 justify-center text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        { 'text-foreground': tab.isActive },
                    )}
                >
                    <span className="relative py-3.5">
                        {tab.title}
                        {tab.isActive && (
                            <span
                                aria-hidden
                                className="absolute inset-x-0 bottom-0 h-[3px] bg-primary [view-transition-class:tab-indicator]"
                                style={{ viewTransitionName: indicatorName }}
                            />
                        )}
                    </span>
                </Link>
            ))}
        </nav>
    );
}
