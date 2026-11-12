import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowUpRight } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import AppearanceToggleTab from '@/components/appearance-tabs';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

export type ConsoleNavItem = {
    key: string;
    title: string;
    description: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    external?: boolean;
};

export type ConsoleNavGroup = {
    label: string;
    items: ConsoleNavItem[];
};

const itemClassName =
    'flex h-9 shrink-0 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:bg-accent hover:text-foreground';

function NavItem({
    item,
    isActive,
    prefetch,
}: {
    item: ConsoleNavItem;
    isActive: boolean;
    prefetch: boolean;
}) {
    if (item.external) {
        return (
            <a
                href={typeof item.href === 'string' ? item.href : item.href.url}
                className={itemClassName}
            >
                <item.icon aria-hidden className="size-4 shrink-0" />
                {item.title}
                <ArrowUpRight aria-hidden className="ml-auto size-3.5" />
            </a>
        );
    }

    return (
        <Link
            href={item.href}
            prefetch={prefetch}
            preserveScroll={false}
            aria-current={isActive ? 'page' : undefined}
            className={cn(
                itemClassName,
                isActive &&
                    'bg-primary-soft text-primary hover:bg-primary-soft hover:text-primary',
            )}
        >
            <item.icon aria-hidden className="size-4 shrink-0" />
            {item.title}
        </Link>
    );
}

function Brand({
    chip,
    label,
    href,
    prefetch,
}: {
    chip: string;
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    prefetch: boolean;
}) {
    return (
        <Link
            href={href}
            prefetch={prefetch}
            className="flex items-center gap-2 rounded-md text-lg font-bold tracking-[-0.03em]"
            aria-label={label}
        >
            <AppLogoIcon className="size-[1.1em] fill-current" />
            <AppWordmark />
            <span className="rounded-sm border px-1.5 font-mono text-[11px] leading-5 font-medium tracking-normal text-muted-foreground">
                {chip}
            </span>
        </Link>
    );
}

/**
 * The shell of a standalone console beside the log, such as Operations: a sidebar of grouped pages, a header naming
 * the current one, and a way back to /dev/null. Its pages prefetch on hover only when the console asks for it.
 */
export default function ConsoleLayout({
    chip,
    label,
    navLabel,
    brandHref,
    prefetch = false,
    groups,
    current,
    backHref,
    user,
    headerActions,
    children,
}: {
    chip: string;
    label: string;
    navLabel: string;
    brandHref: NonNullable<InertiaLinkProps['href']>;
    prefetch?: boolean;
    groups: ConsoleNavGroup[];
    current: string;
    backHref: string;
    user: User | null;
    headerActions?: ReactNode;
    children: ReactNode;
}) {
    const items = groups.flatMap((group) => group.items);
    const page = items.find((item) => item.key === current && !item.external);
    const mobileNav = useRef<HTMLElement>(null);

    useEffect(() => {
        mobileNav.current
            ?.querySelector('[aria-current="page"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }, [current]);

    return (
        <div className="min-h-svh bg-background lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]">
            <aside className="sticky top-0 hidden h-svh flex-col border-r bg-card lg:flex">
                <div className="flex h-14 shrink-0 items-center px-4">
                    <Brand
                        chip={chip}
                        label={label}
                        href={brandHref}
                        prefetch={prefetch}
                    />
                </div>

                <nav
                    aria-label={navLabel}
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
                                    key={item.key}
                                    item={item}
                                    isActive={item === page}
                                    prefetch={prefetch}
                                />
                            ))}
                        </div>
                    ))}
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
                                    title={`@${user.handle}`}
                                >
                                    @{user.handle}
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
                            <Brand
                                chip={chip}
                                label={label}
                                href={brandHref}
                                prefetch={prefetch}
                            />
                        </div>
                        <div className="hidden min-w-0 lg:block">
                            <h1 className="truncate leading-5 font-semibold tracking-tight">
                                {page?.title ?? navLabel}
                            </h1>
                            <p className="truncate text-xs text-muted-foreground">
                                {page?.description}
                            </p>
                        </div>

                        <div className="ml-auto flex items-center gap-1">
                            {headerActions}
                            <a
                                href={backHref}
                                className="ml-2 flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:text-foreground lg:hidden"
                                aria-label="Back to /dev/null"
                                title="Back to /dev/null"
                            >
                                {user ? (
                                    <UserAvatar
                                        user={user}
                                        className="size-7"
                                        pixels={56}
                                    />
                                ) : (
                                    <ArrowUpRight
                                        aria-hidden
                                        className="size-4"
                                    />
                                )}
                            </a>
                        </div>
                    </div>

                    <nav
                        ref={mobileNav}
                        aria-label={navLabel}
                        className="flex scroll-px-3 scrollbar-none gap-1 overflow-x-auto border-t px-3 py-2 lg:hidden"
                    >
                        {items.map((item) => (
                            <NavItem
                                key={item.key}
                                item={item}
                                isActive={item === page}
                                prefetch={prefetch}
                            />
                        ))}
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-5 sm:px-6 sm:py-6 [:root:has([data-chat-dock])_&]:pb-18">
                    <div className="mb-5 lg:hidden">
                        <h1 className="text-lg font-semibold tracking-tight">
                            {page?.title ?? navLabel}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {page?.description}
                        </p>
                    </div>
                    {children}
                </main>
            </div>
        </div>
    );
}
