import { Link, router } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

type Tab = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    isActive: boolean;
    prefetch?: boolean;
    component?: InertiaLinkProps['component'];
    pageProps?: InertiaLinkProps['pageProps'];
    onHttpException?: InertiaLinkProps['onHttpException'];
    onNetworkError?: InertiaLinkProps['onNetworkError'];
};

export function PageTabs({
    label,
    tabs,
    preserveScroll = false,
    prefetch = true,
    only,
    reset,
}: {
    label: string;
    preserveScroll?: boolean;
    prefetch?: boolean;
    only?: InertiaLinkProps['only'];
    reset?: string[];
    tabs: Tab[];
}) {
    const [pending, setPending] = useState<string | null>(null);
    const active = pending ?? tabs.find((tab) => tab.isActive)?.title;
    const finish = (title: string) => {
        setPending((current) => (current === title ? null : current));
    };
    const headers = reset?.length
        ? { 'X-Inertia-Reset': reset.join(',') }
        : undefined;
    const indicatorName = `tab-indicator-${label.toLowerCase().replaceAll(/[^a-z0-9]+/gu, '-')}`;

    return (
        <nav
            aria-label={label}
            aria-busy={pending !== null}
            className="flex border-b"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.title}
                    href={tab.href}
                    component={tab.component}
                    pageProps={tab.pageProps}
                    onHttpException={tab.onHttpException}
                    onNetworkError={tab.onNetworkError}
                    prefetch={
                        tab.isActive || tab.prefetch === false
                            ? false
                            : prefetch
                    }
                    viewTransition
                    preserveScroll={preserveScroll}
                    only={only}
                    // Link exposes custom headers for both visits and prefetches, but no reset option.
                    headers={headers}
                    onBefore={() => {
                        setPending(tab.title);
                        // Failed in-flight prefetches do not fire the consuming visit's onFinish.
                        void router
                            .getPrefetching(tab.href, {
                                only,
                                headers,
                                preserveScroll,
                            })
                            ?.response.catch(() => finish(tab.title));
                    }}
                    onStart={() => setPending(tab.title)}
                    onFinish={() => finish(tab.title)}
                    aria-current={tab.isActive ? 'page' : undefined}
                    className={cn(
                        'relative flex flex-1 justify-center text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        {
                            'text-foreground': active === tab.title,
                            'bg-accent': pending === tab.title,
                        },
                    )}
                >
                    <span className="py-3.5">{tab.title}</span>
                    {active === tab.title && (
                        <span
                            aria-hidden
                            className="absolute inset-x-0 bottom-0 h-[3px] bg-primary [view-transition-class:tab-indicator]"
                            style={{ viewTransitionName: indicatorName }}
                        />
                    )}
                </Link>
            ))}
            <span role="status" className="sr-only">
                {pending ? `Loading ${pending}…` : ''}
            </span>
        </nav>
    );
}
