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
    scrollable = false,
}: {
    label: string;
    preserveScroll?: boolean;
    prefetch?: boolean;
    only?: InertiaLinkProps['only'];
    reset?: string[];
    scrollable?: boolean;
    tabs: {
        title: string;
        /**
         * Shown instead of the title on phones, where a long title would wrap.
         */
        shortTitle?: string;
        href: NonNullable<InertiaLinkProps['href']>;
        isActive: boolean;
    }[];
}) {
    const indicatorName = `tab-indicator-${label.toLowerCase().replaceAll(/[^a-z0-9]+/gu, '-')}`;

    return (
        <nav
            aria-label={label}
            className={cn('flex border-b', scrollable && 'overflow-x-auto')}
            ref={(nav) => {
                const active = nav?.querySelector<HTMLElement>(
                    '[aria-current="page"]',
                );
                if (scrollable && nav && active) {
                    nav.scrollLeft =
                        active.offsetLeft -
                        nav.offsetLeft -
                        (nav.clientWidth - active.offsetWidth) / 2;
                }
            }}
        >
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
                        'relative flex flex-1 justify-center text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        scrollable && 'min-w-max shrink-0 px-3',
                        { 'text-foreground': tab.isActive },
                    )}
                >
                    <span className="py-3.5">
                        {tab.shortTitle ? (
                            <>
                                <span className="sm:hidden">
                                    {tab.shortTitle}
                                </span>
                                <span className="hidden sm:inline">
                                    {tab.title}
                                </span>
                            </>
                        ) : (
                            tab.title
                        )}
                    </span>
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
