import { Link, usePage } from '@inertiajs/react';
import { Slot } from '@radix-ui/react-slot';
import type { CSSProperties, ReactElement, ReactNode } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { cn, laneColor } from '@/lib/utils';
import { home } from '@/routes';
import type { RouteDefinition } from '@/wayfinder';

export type ErrorAction = {
    label: string;
    href: RouteDefinition<'get'>;
};

export function useHomeAction(): ErrorAction {
    const { auth } = usePage().props;

    return auth.user && auth.log
        ? { label: 'Back to your log', href: LogController.show(auth.log) }
        : { label: 'Go to the home page', href: home() };
}

/**
 * The page-sized error on the viewer's own lane: the logo leads home, then the git-flavoured line and what happened.
 * Children take the place of the button, for pages that offer something else to do. Without an action, the logo
 * leads nowhere, for pages the user can't leave.
 */
export function ErrorMessage({
    command,
    headline,
    description,
    action,
    logoClassName,
    className,
    children,
}: {
    command: string;
    headline: ReactElement;
    description: ReactElement;
    action?: ErrorAction;
    logoClassName?: string;
    className?: string;
    children?: ReactNode;
}) {
    const { auth } = usePage().props;
    const lane = auth.user ? laneColor(auth.user.lane) : 'var(--rail)';

    return (
        <ol
            className={cn('w-full', className)}
            style={{ '--lane': lane } as CSSProperties}
        >
            <li className="flex">
                <div aria-hidden className="relative w-10 shrink-0 sm:w-16">
                    <span className="absolute top-3.5 bottom-6 left-1/2 w-0.5 -translate-x-1/2 bg-(--lane) mask-b-from-40%" />
                    <span className="absolute top-3.5 left-1/2 size-3.5 -translate-1/2 rounded-full border-[3px] border-background bg-(--lane) shadow-[0_0_0_2px_var(--lane)]" />
                </div>
                <div className="min-w-0 flex-1 pb-14 sm:pb-16">
                    {action ? (
                        <Link
                            href={action.href}
                            aria-label={`/dev/null, ${action.label.toLowerCase()}`}
                            className="inline-flex"
                        >
                            <AppLogo className={logoClassName} />
                        </Link>
                    ) : (
                        <AppLogo className={logoClassName} />
                    )}
                </div>
            </li>

            <li className="flex">
                <div aria-hidden className="relative w-10 shrink-0 sm:w-16">
                    <span className="absolute top-2.5 left-1/2 size-3.5 -translate-1/2 rounded-full border-2 border-muted-foreground bg-background" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="font-mono text-[13px] wrap-anywhere text-muted-foreground">
                        {command}
                    </p>
                    <Slot className="mt-4 text-[clamp(1.75rem,4vw,2.5rem)] leading-tight font-semibold tracking-tight text-balance">
                        {headline}
                    </Slot>
                    <Slot className="mt-3 max-w-[42ch] text-lg text-pretty text-muted-foreground">
                        {description}
                    </Slot>
                    {children ??
                        (action && (
                            <Button size="lg" className="mt-8" asChild>
                                <Link href={action.href}>{action.label}</Link>
                            </Button>
                        ))}
                </div>
            </li>
        </ol>
    );
}
