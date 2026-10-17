import { useLang } from '@erag/lang-sync-inertia/react';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Bell,
    Bookmark,
    GitCommitVertical,
    Search,
    Settings2,
    ShieldCheck,
    SquarePen,
    UserRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import BookmarkController from '@/actions/App/Http/Controllers/BookmarkController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import OperationsController from '@/actions/App/Http/Controllers/OperationsController';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import { GitHubIcon } from '@/components/github-icon';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useKeyLabels } from '@/lib/keyboard';
import { cn, toUrl } from '@/lib/utils';
import { home, login } from '@/routes';
import { show } from '@/routes/user';
import { edit } from '@/routes/user-profile';
import type { Log, NavItem, Profile } from '@/types';

/**
 * Guests see the same items, each taking them to sign in.
 */
function useNavItems(): (NavItem & { label: string; isActive: boolean })[] {
    const { auth, hasUnmoderatedCommits } = usePage().props;
    const { __ } = useLang();
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { user, log } = auth;

    const hrefFor = (
        href: (user: Profile, log: Log) => NavItem['href'],
    ): NavItem['href'] =>
        user === null || log === null ? login() : href(user, log);

    const items: (NavItem & { label: string })[] = [
        {
            title: 'Log',
            label: __('nav.items.log'),
            href: log === null ? home() : LogController.show(log),
            icon: GitCommitVertical,
            prefetch: false,
        },
        {
            title: 'Notifications',
            label: __('nav.items.notifications'),
            href: hrefFor(() => NotificationController.index()),
            icon: Bell,
            badge: auth.unreadNotificationsCount,
            prefetch: false,
        },
        {
            title: 'Profile',
            label: __('nav.items.profile'),
            href: hrefFor((user) => show(user)),
            icon: UserRound,
        },
        {
            title: 'Saved',
            label: __('nav.items.saved'),
            href: hrefFor(() => BookmarkController.index()),
            icon: Bookmark,
            prefetch: false,
        },
        ...(auth.isAdmin
            ? [
                  {
                      title: 'Moderation',
                      label: __('nav.items.moderation'),
                      href: ModerationController.show(),
                      icon: ShieldCheck,
                      hasIndicator: hasUnmoderatedCommits,
                  },
                  {
                      title: 'Operations',
                      label: __('nav.items.operations'),
                      href: OperationsController.show(),
                      icon: Activity,
                      prefetch: false,
                  },
              ]
            : []),
        {
            title: 'Settings',
            label: __('nav.items.settings'),
            href: hrefFor(() => edit()),
            icon: Settings2,
        },
    ];

    return items.map((item) => ({
        ...item,
        isActive:
            user !== null &&
            (item.title === 'Settings'
                ? isCurrentOrParentUrl('/settings')
                : item.title === 'Log'
                  ? isCurrentOrParentUrl('/log')
                  : isCurrentUrl(item.href)),
    }));
}

/**
 * Signing in leaves for GitHub, which an Inertia visit cannot follow, so guests get a plain link instead.
 */
function NavLink({
    item,
    className,
    children,
}: {
    item: NavItem & { label: string; isActive: boolean };
    className: string;
    children: ReactNode;
}) {
    const { auth } = usePage().props;

    if (auth.user === null) {
        return (
            <a href={toUrl(item.href)} className={className}>
                {children}
            </a>
        );
    }

    return (
        <Link
            href={item.href}
            prefetch={item.prefetch ?? true}
            viewTransition
            aria-current={item.isActive ? 'page' : undefined}
            className={className}
        >
            {children}
        </Link>
    );
}

function SignInButton({ className }: { className?: string }) {
    const { __ } = useLang();

    return (
        <Button asChild className={className}>
            <a href={login().url} data-test="nav-login-button">
                <GitHubIcon className="size-4" />
                <span className="sr-only lg:not-sr-only">
                    {__('nav.sign_in_with_github')}
                </span>
            </a>
        </Button>
    );
}

function NavIcon({
    item,
    indicatorClassName,
}: {
    item: NavItem & { label: string; isActive: boolean };
    indicatorClassName?: string;
}) {
    const { __ } = useLang();

    if (!item.icon) {
        return null;
    }

    const count = item.isActive ? 0 : (item.badge ?? 0);

    return (
        <span className="relative">
            <item.icon className="size-5" />
            {item.hasIndicator && (
                <span
                    className={cn(
                        'absolute -top-0.5 -right-0.5 size-2 rounded-full bg-primary',
                        indicatorClassName,
                    )}
                    data-test="notification-indicator"
                />
            )}
            {count > 0 && (
                <span className="absolute -top-1.5 -right-2 min-w-4 rounded-full bg-primary px-1 text-center text-[10px] leading-4 font-semibold text-primary-foreground tabular-nums">
                    {count > 99 ? '99+' : count}
                    <span className="sr-only"> {__('nav.unread')}</span>
                </span>
            )}
        </span>
    );
}

export function AppNav({
    onSearch,
    onCompose,
}: {
    onSearch: () => void;
    onCompose: () => void;
}) {
    const { auth } = usePage().props;
    const items = useNavItems();
    const keys = useKeyLabels();
    const { __ } = useLang();

    return (
        <header className="sticky top-0 hidden h-svh w-20 shrink-0 [scrollbar-width:none] flex-col items-center overflow-y-auto py-6 md:flex lg:w-64 lg:items-stretch lg:px-4">
            <Link
                href={auth.log === null ? home() : LogController.show(auth.log)}
                viewTransition
                className="mb-8 rounded-md px-3 lg:px-3"
                aria-label={
                    auth.log === null ? __('nav.go_home') : __('nav.go_to_log')
                }
            >
                <AppLogoIcon className="size-7 fill-current lg:hidden" />
                <AppLogo className="hidden text-[1.75rem] font-bold tracking-[-0.03em] lg:inline-flex" />
                <span className="mt-1 hidden text-sm text-muted-foreground lg:block">
                    {__('nav.tagline')}
                </span>
            </Link>

            <nav aria-label={__('nav.main')} className="flex flex-col gap-1">
                {items.map((item) => (
                    <NavLink
                        key={item.title}
                        item={item}
                        className={cn(
                            'relative isolate block rounded-lg p-3 text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                            {
                                'text-primary hover:bg-transparent hover:text-primary':
                                    item.isActive,
                            },
                        )}
                    >
                        {item.isActive && (
                            <span
                                aria-hidden
                                className="absolute inset-0 -z-10 rounded-lg bg-primary-soft [view-transition-name:nav-active-item]"
                            />
                        )}
                        <span
                            className="flex items-center gap-3 [view-transition-class:indicator-label]"
                            style={{
                                viewTransitionName: `nav-${item.title.toLowerCase()}`,
                            }}
                        >
                            <NavIcon
                                item={item}
                                indicatorClassName="lg:hidden"
                            />
                            <span className="sr-only lg:not-sr-only">
                                {item.label}
                            </span>
                            {item.hasIndicator && (
                                <span
                                    className="ml-auto hidden size-2 rounded-full bg-primary lg:inline-block"
                                    data-test="notification-indicator"
                                />
                            )}
                        </span>
                    </NavLink>
                ))}
                <button
                    type="button"
                    onClick={onSearch}
                    className="flex items-center gap-3 rounded-lg p-3 text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground xl:hidden"
                >
                    <Search className="size-5" />
                    <span className="sr-only lg:not-sr-only">
                        {__('nav.search.title')}
                    </span>
                    <kbd className="ml-auto hidden rounded border border-b-2 bg-muted px-1.5 font-mono text-xs lg:block">
                        {keys.combo(keys.mod, 'K')}
                    </kbd>
                </button>
            </nav>

            <Button
                type="button"
                onClick={onCompose}
                aria-label={__('nav.write_commit')}
                aria-keyshortcuts="c"
                title={__('nav.write_commit_title')}
                className="mt-4 h-[52px] w-[52px] shrink-0 rounded-lg text-[15px] lg:w-auto"
                data-test="nav-commit-button"
            >
                <SquarePen className="size-5" />
                <span className="sr-only lg:not-sr-only">
                    {__('nav.commit')}
                </span>
            </Button>

            {auth.user === null ? (
                <SignInButton className="mt-auto h-[52px] w-[52px] rounded-lg lg:w-auto" />
            ) : (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        className="mt-auto flex items-center gap-3 rounded-lg p-2 text-left transition-colors hover:bg-accent data-[state=open]:bg-accent"
                        data-test="user-menu-button"
                    >
                        <UserAvatar user={auth.user} className="size-9" />
                        <span className="hidden min-w-0 flex-1 lg:block">
                            <span className="block truncate text-sm font-medium">
                                {auth.user.name}
                            </span>
                            <span className="block truncate font-mono text-xs text-muted-foreground">
                                @{auth.user.username}
                            </span>
                        </span>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-60"
                        side="top"
                        align="start"
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </header>
    );
}

export function MobileHeader({ onSearch }: { onSearch: () => void }) {
    const { auth } = usePage().props;
    const { __ } = useLang();

    return (
        <div className="flex h-14 items-center justify-between border-b px-4 md:hidden">
            <Link
                href={auth.log === null ? home() : LogController.show(auth.log)}
                viewTransition
                aria-label={
                    auth.log === null ? __('nav.go_home') : __('nav.go_to_log')
                }
            >
                <AppLogo className="text-lg" />
            </Link>

            <div className="flex items-center gap-1">
                <button
                    type="button"
                    onClick={onSearch}
                    aria-label={__('nav.search.title')}
                    className="rounded-full p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <Search className="size-5" />
                </button>
                {auth.user === null ? (
                    <Button size="sm" className="h-8" asChild>
                        <a href={login().url} data-test="nav-login-button">
                            <GitHubIcon className="size-4" />
                            {__('nav.sign_in')}
                        </a>
                    </Button>
                ) : (
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            className="rounded-full"
                            aria-label={__('nav.account_menu')}
                        >
                            <UserAvatar user={auth.user} className="size-8" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-60" align="end">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </div>
    );
}

export function MobileNav() {
    const items = useNavItems().filter(
        (item) => !['Saved', 'Operations'].includes(item.title),
    );
    const { __ } = useLang();

    return (
        <nav
            aria-label={__('nav.main')}
            className="fixed inset-x-0 bottom-0 z-20 flex border-t bg-card pb-[env(safe-area-inset-bottom)] [view-transition-name:mobile-nav] md:hidden"
        >
            {items.map((item) => (
                <NavLink
                    key={item.title}
                    item={item}
                    className={cn(
                        'flex min-w-0 flex-1 flex-col items-center gap-1 py-1 text-[11px] font-medium tracking-tight text-muted-foreground',
                        { 'text-primary': item.isActive },
                    )}
                >
                    <span className="relative isolate flex h-8 w-14 max-w-full items-center justify-center">
                        {item.isActive && (
                            <span
                                aria-hidden
                                className="absolute inset-0 -z-10 rounded-full bg-primary-soft [view-transition-name:mobile-nav-active-item]"
                            />
                        )}
                        <span
                            className="flex [view-transition-class:mobile-nav-icon]"
                            style={{
                                viewTransitionName: `mobile-nav-${item.title.toLowerCase()}`,
                            }}
                        >
                            <NavIcon item={item} />
                        </span>
                    </span>
                    <span className="max-w-full truncate">{item.label}</span>
                </NavLink>
            ))}
        </nav>
    );
}
