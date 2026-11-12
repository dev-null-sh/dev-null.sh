import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/ProfileLookupController';
import { GitHubIcon } from '@/components/github-icon';
import { Button } from '@/components/ui/button';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import { home, login } from '@/routes';
import { index as searchProfiles } from '@/routes/search';

type Lookup = {
    status: 'idle' | 'pending' | 'paused' | 'missing';
    resumesAt: string | null;
};

/**
 * How long the page waits for GitHub before giving up, like the job on the server.
 */
const WAIT_MS = 30_000;

/**
 * A handle nobody here has yet. Members look it up on GitHub, and the page turns into the profile it finds; guests are
 * asked to sign in first.
 */
export default function ProfileLookup({
    handle,
    lookup,
}: {
    handle: string;
    lookup: Lookup | null;
}) {
    return (
        <>
            <Head title={`@${handle}`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <p className="min-w-0 truncate font-semibold">@{handle}</p>
            </div>

            <div className="flex min-h-[calc(100dvh-3.5rem)] flex-col items-center px-4 pt-16 pb-24 text-center sm:px-6">
                {lookup === null ? (
                    <GuestPrompt handle={handle} />
                ) : (
                    <MemberLookup
                        key={handle}
                        handle={handle}
                        lookup={lookup}
                    />
                )}
            </div>
        </>
    );
}

ProfileLookup.layout = AppLayout;

function GuestPrompt({ handle }: { handle: string }) {
    return (
        <>
            <div
                aria-hidden
                className="flex size-22 items-center justify-center rounded-full border-2 border-dashed border-rail font-mono text-2xl text-muted-foreground"
            >
                @?
            </div>
            <h1 className="mt-5 text-2xl font-semibold tracking-tight text-balance">
                Who's @{handle}?
            </h1>
            <p className="mt-1.5 max-w-md text-[15px] text-pretty text-muted-foreground">
                /dev/null hasn't looked up this GitHub handle yet. Sign in and
                we'll fetch their profile from GitHub.
            </p>
            <Button size="lg" className="mt-5" asChild>
                <a href={login().url}>
                    <GitHubIcon className="size-4" />
                    Sign in with GitHub
                </a>
            </Button>
            <p className="mt-3 text-sm text-muted-foreground">
                Are you @{handle}? Signing in creates your profile.
            </p>
        </>
    );
}

function MemberLookup({ handle, lookup }: { handle: string; lookup: Lookup }) {
    const { flash } = usePage();
    const [failed, setFailed] = useState(false);
    // Each lookup waits its own half minute, even when the page already showed it pending.
    const [attempt, setAttempt] = useState(0);
    const [previousStatus, setPreviousStatus] = useState(lookup.status);
    const { start, stop } = usePoll(
        2_000,
        { only: ['lookup'] },
        { autoStart: false },
    );

    // A lookup that stops being pending without an answer failed, whoever started it.
    if (lookup.status !== previousStatus) {
        setPreviousStatus(lookup.status);

        if (previousStatus === 'pending' && lookup.status === 'idle') {
            setFailed(true);
        }
    }

    const lookUp = () => {
        setFailed(false);
        router.post(
            store.url(),
            { handle },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['lookup'],
                onSuccess: (page) => {
                    // Done, and failed, before the page could show it pending.
                    if (
                        (page.props.lookup as Lookup | null)?.status === 'idle'
                    ) {
                        setFailed(true);
                    }

                    setAttempt((attempt) => attempt + 1);
                },
            },
        );
    };

    // Opening the page is what looks the handle up, once: trying again is the member's call.
    useEffect(() => {
        if (lookup.status === 'idle') {
            lookUp();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // The job ends with the profile, which replaces this page, or with an answer this page shows.
    useEffect(() => {
        if (lookup.status !== 'pending' || failed) {
            return;
        }

        start();
        const timeout = window.setTimeout(() => setFailed(true), WAIT_MS);

        return () => {
            stop();
            window.clearTimeout(timeout);
        };
    }, [lookup.status, failed, attempt, start, stop]);

    if (flash.lookupThrottled) {
        return (
            <Answer
                command={`gh api users/${handle}`}
                output="rate limited"
                title="You've looked up a lot of profiles"
            >
                Each lookup asks GitHub, which /dev/null shares with everyone.
                Try again in an hour.
            </Answer>
        );
    }

    if (lookup.status === 'missing') {
        return (
            <Answer
                command={`gh api users/${handle}`}
                output="gh: Not Found (HTTP 404)"
                title={`GitHub has no @${handle}`}
                actions={
                    <>
                        <Button asChild>
                            <Link href={searchProfiles()} prefetch>
                                <Search />
                                Search profiles
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={home()} prefetch>
                                Back to the log
                            </Link>
                        </Button>
                    </>
                }
            >
                No GitHub account or organization uses this handle. Check the
                spelling, or search /dev/null.
            </Answer>
        );
    }

    if (lookup.status === 'paused') {
        return (
            <Answer
                command={`gh api users/${handle}`}
                output="waiting for GitHub's rate limit"
                title="GitHub needs a break"
            >
                /dev/null asked GitHub a lot this hour. Lookups resume{' '}
                <ResumesIn resumesAt={lookup.resumesAt} />: come back then to
                look @{handle} up.
            </Answer>
        );
    }

    if (failed) {
        return (
            <Answer
                command={`gh api users/${handle}`}
                output="no answer"
                title="GitHub isn't answering right now"
                actions={<Button onClick={lookUp}>Try again</Button>}
            >
                The lookup of @{handle} didn't come back. It's usually
                temporary.
            </Answer>
        );
    }

    return (
        <>
            <div className="relative size-24" aria-hidden>
                <div className="absolute -inset-1.5 animate-spin rounded-full bg-[conic-gradient(var(--primary)_0_25%,var(--border)_25%_100%)] [animation-duration:1.4s]" />
                <div className="absolute -inset-0.5 rounded-full bg-card" />
                <div className="absolute inset-0.5 flex items-center justify-center rounded-full bg-muted">
                    <GitHubIcon className="size-10 text-muted-foreground" />
                </div>
            </div>
            <h1 className="mt-6 text-2xl font-semibold tracking-tight text-balance">
                Looking into this GitHub profile
            </h1>
            <p className="mt-1.5 max-w-md text-[15px] text-pretty text-muted-foreground">
                @{handle} isn't on /dev/null yet. Asking GitHub who they are.
            </p>
            <Terminal
                command={`gh api users/${handle}`}
                output="queued, waiting for GitHub…"
                waiting
            />
            <p role="status" className="mt-4 text-sm text-muted-foreground">
                This page updates by itself. It usually takes a few seconds.
            </p>
        </>
    );
}

function Answer({
    command,
    output,
    title,
    actions,
    children,
}: {
    command: string;
    output: string;
    title: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <>
            <Terminal command={command} output={output} />
            <h1 className="mt-6 text-2xl font-semibold tracking-tight text-balance">
                {title}
            </h1>
            <p className="mt-1.5 max-w-md text-[15px] text-pretty text-muted-foreground">
                {children}
            </p>
            {actions && (
                <div className="mt-5 flex flex-wrap justify-center gap-2">
                    {actions}
                </div>
            )}
        </>
    );
}

function Terminal({
    command,
    output,
    waiting = false,
}: {
    command: string;
    output: string;
    waiting?: boolean;
}) {
    return (
        <div className="mt-6 w-full max-w-lg rounded-lg border bg-muted/50 px-4 py-3 text-left font-mono text-[13px] leading-relaxed">
            <p className="truncate">
                <span className="text-primary">$</span> {command}
            </p>
            <p className="truncate text-muted-foreground">{output}</p>
            {waiting && (
                <p aria-hidden>
                    <span className="text-primary">$</span>{' '}
                    <span className="animate-pulse">▍</span>
                </p>
            )}
        </div>
    );
}

function ResumesIn({ resumesAt }: { resumesAt: string | null }) {
    const now = useNow();
    const minutes = Math.max(
        1,
        Math.ceil(
            (new Date(resumesAt ?? now).getTime() - now.getTime()) / 60_000,
        ),
    );

    return (
        <span className="font-mono tabular-nums">
            in {minutes} {minutes === 1 ? 'minute' : 'minutes'}
        </span>
    );
}
