import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { useLayoutEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

export function PageTabs({
    label,
    tabs,
    preserveScroll = false,
    prefetch = true,
    only,
    reset,
    isScrollable = false,
}: {
    label: string;
    // Scrolls sideways once the tabs don't fit, instead of cutting their titles: for tabs people add themselves.
    isScrollable?: boolean;
    preserveScroll?: boolean;
    prefetch?: boolean;
    only?: InertiaLinkProps['only'];
    reset?: string[];
    tabs: {
        key?: string;
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
    const navRef = useRef<HTMLElement>(null);
    const activeTab = tabs.find((tab) => tab.isActive);

    // Tabs share the width while they fit and scroll sideways once they don't, so the active one is brought into view.
    useLayoutEffect(() => {
        const nav = isScrollable ? navRef.current : null;
        const active = nav?.querySelector<HTMLElement>('[aria-current="page"]');

        if (!nav || !active || nav.scrollWidth <= nav.clientWidth) {
            return;
        }

        nav.scrollLeft =
            active.offsetLeft - (nav.clientWidth - active.offsetWidth) / 2;
    }, [isScrollable, activeTab?.key, activeTab?.title]);

    return (
        <nav
            ref={navRef}
            aria-label={label}
            className={cn('flex border-b', {
                '[scrollbar-width:none] overflow-x-auto': isScrollable,
            })}
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.key ?? tab.title}
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
                        isScrollable ? 'min-w-max px-4' : 'min-w-0 px-2',
                        { 'text-foreground': tab.isActive },
                    )}
                >
                    <span
                        className={cn('truncate py-3.5', {
                            'max-w-40': isScrollable,
                        })}
                    >
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
