import { Head, Link, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { laneColor } from '@/lib/utils';
import { home } from '@/routes';
import type { Profile } from '@/types';

type Status = 403 | 404 | 429 | 500 | 503;

type ErrorCopy = {
    title: string;
    command: string;
    headline: string;
    description: string;
};

function errorCopy(
    status: Status,
    path: string,
    username: string | null,
): ErrorCopy {
    switch (status) {
        case 403:
            return {
                title: 'Forbidden',
                command: username
                    ? `remote: Permission to ${path} denied to ${username}.`
                    : `remote: Permission to ${path} denied.`,
                headline: "You don't have access to this page.",
                description:
                    "It's limited to certain accounts, and yours isn't one of them.",
            };
        case 404:
            return {
                title: 'Not found',
                command: `fatal: path '${path}' does not exist in 'HEAD'`,
                headline: "There's nothing here.",
                description:
                    'The link may be mistyped, or whatever it pointed to has been deleted.',
            };
        case 429:
            return {
                title: 'Too many requests',
                command: 'error: RPC failed; HTTP 429 Too Many Requests',
                headline: 'Slow down.',
                description:
                    "You've sent too many requests in a short time. Wait a minute, then try again.",
            };
        case 500:
            return {
                title: 'Server error',
                command: 'fatal: the remote end hung up unexpectedly',
                headline: 'Something broke on our end.',
                description:
                    "It's not you. The error has been logged; try again in a moment.",
            };
        case 503:
            return {
                title: 'Down for maintenance',
                command: `fatal: unable to access '${path}': The requested URL returned error: 503`,
                headline: 'devnull is down for maintenance.',
                description:
                    "We're making changes and will be back shortly. Try again in a few minutes.",
            };
    }
}

export default function ErrorPage({ status }: { status: Status }) {
    const page = usePage();
    const user: Profile | null = page.props.auth.user;
    const path = page.url.split('?')[0];
    const copy = errorCopy(status, path, user?.username ?? null);
    const lane = user ? laneColor(user.lane) : 'var(--rail)';
    const action =
        status === 503
            ? { label: 'Try again', href: home() }
            : user && page.props.auth.log
              ? {
                    label: 'Back to your log',
                    href: LogController.show(page.props.auth.log),
                }
              : { label: 'Go to the home page', href: home() };

    return (
        <>
            <Head title={copy.title} />

            <main
                className="mx-auto flex min-h-svh w-full max-w-3xl items-center px-4 py-16 sm:px-8"
                style={{ '--lane': lane } as CSSProperties}
            >
                <ol className="w-full">
                    <li className="flex">
                        <div
                            aria-hidden
                            className="relative w-10 shrink-0 sm:w-16"
                        >
                            <span className="absolute top-3.5 bottom-6 left-1/2 w-0.5 -translate-x-1/2 bg-(--lane) mask-b-from-40%" />
                            <span className="absolute top-3.5 left-1/2 size-3.5 -translate-1/2 rounded-full border-[3px] border-background bg-(--lane) shadow-[0_0_0_2px_var(--lane)]" />
                        </div>
                        <div className="min-w-0 flex-1 pb-14 sm:pb-16">
                            <Link
                                href={action.href}
                                aria-label={`devnull, ${action.label.toLowerCase()}`}
                                className="inline-flex"
                            >
                                <AppLogo />
                            </Link>
                        </div>
                    </li>

                    <li className="flex">
                        <div
                            aria-hidden
                            className="relative w-10 shrink-0 sm:w-16"
                        >
                            <span className="absolute top-2.5 left-1/2 size-3.5 -translate-1/2 rounded-full border-2 border-muted-foreground bg-background" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="font-mono text-[13px] [overflow-wrap:anywhere] text-muted-foreground">
                                {copy.command}
                            </p>
                            <h1 className="mt-4 text-[clamp(1.75rem,4vw,2.5rem)] leading-tight font-semibold tracking-tight text-balance">
                                {copy.headline}
                            </h1>
                            <p className="mt-3 max-w-[42ch] text-lg text-pretty text-muted-foreground">
                                {copy.description}
                            </p>
                            <Button size="lg" className="mt-8" asChild>
                                <Link href={action.href}>{action.label}</Link>
                            </Button>
                        </div>
                    </li>
                </ol>
            </main>
        </>
    );
}
