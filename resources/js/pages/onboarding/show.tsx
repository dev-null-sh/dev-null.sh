import {
    Form,
    Head,
    Link,
    router,
    usePage,
    usePoll,
    useRemember,
} from '@inertiajs/react';
import { ArrowUpRight, Check, Copy, RefreshCw, UserPlus } from 'lucide-react';
import type { CSSProperties, ReactNode, RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import OnboardingController from '@/actions/App/Http/Controllers/OnboardingController';
import UserLaneController from '@/actions/App/Http/Controllers/UserLaneController';
import UserProfileController from '@/actions/App/Http/Controllers/UserProfileController';
import AppLogo from '@/components/app-logo';
import { GitHubProfile } from '@/components/github-profile';
import InputError from '@/components/input-error';
import { KeyboardShortcutsDialog } from '@/components/keyboard-shortcuts-dialog';
import { LaneOptions, LanePreview } from '@/components/lane-picker';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { SuggestedUserRow } from '@/components/who-to-follow';
import { WordmarkText } from '@/components/wordmark-text';
import { useClipboard } from '@/hooks/use-clipboard';
import { useUser } from '@/hooks/use-user';
import { useKeyLabels } from '@/lib/keyboard';
import { formatCount } from '@/lib/numbers';
import { cn, laneColor } from '@/lib/utils';
import type { Lane, SuggestedUser, User } from '@/types';

const laneNames = ['orange', 'green', 'violet', 'teal', 'rose', 'ochre'];

type GitHubImport = {
    following: User[];
    followingCount: number;
    followers: User[];
    followersCount: number;
    pendingCount: number;
};

function people(count: number): string {
    return count === 1 ? '1 person' : `${formatCount(count)} people`;
}

function developers(count: number): string {
    return count === 1 ? '1 developer' : `${formatCount(count)} developers`;
}

const countUpDuration = 900;

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Counts from 0 to the target, fast then settling, after the given delay.
 */
function useCountUp(target: number, delay: number): number {
    const [value, setValue] = useState(0);

    useEffect(() => {
        if (prefersReducedMotion()) {
            return;
        }

        let frame = 0;
        const start = performance.now() + delay;
        const tick = (now: number) => {
            const progress = Math.min(
                1,
                Math.max(0, (now - start) / countUpDuration),
            );
            setValue(Math.round(target * (1 - (1 - progress) ** 3)));

            if (progress < 1) {
                frame = requestAnimationFrame(tick);
            }
        };
        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [target, delay]);

    return prefersReducedMotion() ? target : value;
}

/**
 * A count the import brought over, ticking up while the faces behind it pop onto the card like nodes onto the rail,
 * each pulsing in its own lane.
 */
function ImportStat({
    users,
    count,
    label,
    delay,
    children,
}: {
    users: User[];
    count: number;
    label: string;
    delay: number;
    children: ReactNode;
}) {
    const value = useCountUp(count, delay);
    const shown = users.slice(0, 5);
    const stagger = countUpDuration / Math.max(shown.length, 1);

    return (
        <div className="min-w-0 p-4">
            <p className="flex items-baseline gap-2">
                <span
                    className="font-mono text-3xl font-semibold tracking-tight tabular-nums"
                    style={{ minWidth: `${formatCount(count).length}ch` }}
                >
                    {formatCount(value)}
                </span>
                <span className="text-[15px] font-semibold">{label}</span>
            </p>
            <div className="mt-3 flex flex-wrap items-center gap-2.5 p-0.5">
                {shown.map((user, index) => (
                    <span
                        key={user.id}
                        className="rounded-full motion-safe:animate-node-in"
                        style={
                            {
                                '--lane': laneColor(user.lane),
                                animationDelay: `${delay + index * stagger}ms`,
                            } as CSSProperties
                        }
                    >
                        <UserAvatar
                            user={user}
                            className="size-8 ring-2 ring-(--lane) ring-offset-2 ring-offset-card"
                            pixels={64}
                        />
                    </span>
                ))}
                {count > shown.length && (
                    <span
                        className="font-mono text-xs text-muted-foreground motion-safe:animate-in motion-safe:fill-mode-both motion-safe:fade-in"
                        style={{
                            animationDelay: `${delay + countUpDuration}ms`,
                        }}
                    >
                        +{formatCount(count - shown.length)}
                    </span>
                )}
            </div>
            <p className="mt-3 text-sm text-pretty text-muted-foreground">
                {children}
            </p>
        </div>
    );
}

function ImportRow({
    visual,
    delay,
    children,
}: {
    visual: ReactNode;
    delay: number;
    children: ReactNode;
}) {
    return (
        <li
            className="flex items-center gap-3 p-4 motion-safe:animate-in motion-safe:duration-500 motion-safe:fill-mode-both motion-safe:fade-in motion-safe:slide-in-from-bottom-1"
            style={{ animationDelay: `${delay}ms` }}
        >
            <div className="hidden w-[104px] shrink-0 sm:flex">{visual}</div>
            <div className="min-w-0 flex-1 text-[15px] text-pretty">
                {children}
            </div>
        </li>
    );
}

function InviteActions() {
    const [, copy] = useClipboard();
    const invite = `I'm on /dev/null, a social network for developers. Sign in with GitHub and you'll follow the people you already follow there: ${window.location.origin}`;

    const copyInvite = async () => {
        if (await copy(invite)) {
            toast(
                <span className="font-mono text-[13px]">
                    Copied an invite, paste it anywhere
                </span>,
            );

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: could not copy the invite
            </span>,
        );
    };

    return (
        <span className="mt-2 flex flex-wrap gap-2">
            <Button variant="outline" size="sm" onClick={copyInvite}>
                <Copy />
                Copy an invite
            </Button>
            <Button variant="ghost" size="sm" asChild>
                <a
                    href={`https://x.com/intent/post?text=${encodeURIComponent(invite)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Post on X
                    <ArrowUpRight />
                </a>
            </Button>
        </span>
    );
}

/**
 * What the GitHub import brought over, or a line saying it is still running.
 */
function GitHubImportCard({
    githubImport,
    hasGivenUp,
    handle,
}: {
    githubImport: GitHubImport | null;
    hasGivenUp: boolean;
    handle: string;
}) {
    if (githubImport === null) {
        return hasGivenUp ? null : (
            <div className="mb-6 flex items-center gap-3 rounded-lg border bg-card p-4 font-mono text-[13px] text-muted-foreground">
                <Spinner />
                Looking for your GitHub network on /dev/null…
            </div>
        );
    }

    const { following, followingCount, followers, followersCount } =
        githubImport;
    const { pendingCount } = githubImport;

    if (followingCount === 0 && followersCount === 0 && pendingCount === 0) {
        return null;
    }

    const hasImported = followingCount > 0 || followersCount > 0;
    const followersDelay = followingCount > 0 ? 450 : 150;

    return (
        <div className="mb-6 overflow-hidden rounded-lg border bg-card">
            <p className="flex items-center gap-2 border-b px-4 py-2 font-mono text-xs text-muted-foreground">
                <span className="rounded-full motion-safe:animate-node-in">
                    <Check className="size-3.5 text-success" />
                </span>
                {hasImported ? 'imported from' : 'checked'} github.com/{handle}
            </p>
            {hasImported && (
                <div
                    className={cn('grid divide-y', {
                        'sm:grid-cols-2 sm:divide-x sm:divide-y-0':
                            followingCount > 0 && followersCount > 0,
                    })}
                >
                    {followingCount > 0 && (
                        <ImportStat
                            users={following}
                            count={followingCount}
                            label="following"
                            delay={150}
                        >
                            {followingCount === 1
                                ? 'Someone you follow on GitHub is already here. You follow them now.'
                                : 'People you follow on GitHub who are already here. You follow them now.'}
                        </ImportStat>
                    )}
                    {followersCount > 0 && (
                        <ImportStat
                            users={followers}
                            count={followersCount}
                            label={
                                followersCount === 1 ? 'follower' : 'followers'
                            }
                            delay={followersDelay}
                        >
                            {followersCount === 1
                                ? 'Someone following you on GitHub is already here. They follow you now.'
                                : 'People following you on GitHub who are already here. They follow you now.'}
                        </ImportStat>
                    )}
                </div>
            )}
            {pendingCount > 0 && (
                <ul className={cn({ 'border-t': hasImported })}>
                    <ImportRow
                        delay={
                            hasImported ? followersDelay + countUpDuration : 150
                        }
                        visual={
                            <span className="flex -space-x-2">
                                {[0, 1, 2].map((placeholder) => (
                                    <span
                                        key={placeholder}
                                        className="flex size-8 items-center justify-center rounded-full border-2 border-dashed border-rail bg-card text-muted-foreground"
                                    >
                                        {placeholder === 2 && (
                                            <UserPlus className="size-3.5" />
                                        )}
                                    </span>
                                ))}
                            </span>
                        }
                    >
                        <strong className="font-semibold">
                            {followingCount > 0
                                ? `${formatCount(pendingCount)} more ${pendingCount === 1 ? 'person' : 'people'} you follow on GitHub`
                                : pendingCount === 1
                                  ? 'The person you follow on GitHub'
                                  : `The ${people(pendingCount)} you follow on GitHub`}
                        </strong>{' '}
                        {pendingCount === 1 ? "isn't" : "aren't"} here yet.
                        You'll follow them automatically when they join.
                        <InviteActions />
                    </ImportRow>
                </ul>
            )}
        </div>
    );
}

type StepState = 'done' | 'current' | 'todo';

function Kbd({ children }: { children: ReactNode }) {
    return (
        <kbd className="inline-block min-w-6 rounded border border-b-2 bg-muted px-1.5 py-0.5 text-center font-mono text-xs leading-none text-foreground">
            {children}
        </kbd>
    );
}

function StepRow({
    state,
    command,
    title,
    isLast = false,
    children,
}: {
    state: StepState;
    command: string;
    title: string;
    isLast?: boolean;
    children?: ReactNode;
}) {
    return (
        <li
            className="flex"
            aria-current={state === 'current' ? 'step' : undefined}
        >
            <div aria-hidden className="relative w-10 shrink-0 sm:w-14">
                {!isLast && (
                    <span
                        className={cn(
                            'absolute top-3 bottom-0 left-1/2 -translate-x-1/2 transition-colors duration-200',
                            state === 'done'
                                ? 'w-0.5 bg-(--lane)'
                                : 'border-l-2 border-dashed border-rail',
                        )}
                    />
                )}
                <span
                    key={state}
                    className={cn(
                        'absolute top-3 left-1/2 size-3.5 -translate-1/2 rounded-full border-[3px] border-background transition-colors duration-200',
                        state === 'todo'
                            ? 'bg-background shadow-[0_0_0_2px_var(--rail)]'
                            : 'bg-(--lane) shadow-[0_0_0_2px_var(--lane)]',
                        { 'motion-safe:animate-node-in': state === 'current' },
                    )}
                />
            </div>
            <div className={cn('min-w-0 flex-1', { 'pb-8': !isLast })}>
                <p className="font-mono text-[13px] leading-6 text-muted-foreground">
                    $ {command}
                </p>
                {state === 'current' ? (
                    children
                ) : (
                    <p
                        className={cn('text-[15px]', {
                            'text-muted-foreground': state === 'todo',
                        })}
                    >
                        {title}
                        {state === 'done' && (
                            <span className="sr-only"> (done)</span>
                        )}
                    </p>
                )}
            </div>
        </li>
    );
}

function StepContent({
    headingRef,
    title,
    description,
    actions,
    children,
}: {
    headingRef: RefObject<HTMLHeadingElement | null>;
    title: string;
    description: ReactNode;
    actions: ReactNode;
    children: ReactNode;
}) {
    return (
        <section aria-labelledby="onboarding-step-title">
            <h1
                ref={headingRef}
                id="onboarding-step-title"
                tabIndex={-1}
                className="mt-1 text-2xl font-semibold tracking-tight text-balance outline-none"
            >
                {title}
            </h1>
            <p className="mt-2 max-w-prose text-[15px] text-pretty text-muted-foreground">
                {description}
            </p>
            <div className="mt-6">{children}</div>
            <div className="mt-8 flex flex-wrap items-center gap-2">
                {actions}
            </div>
        </section>
    );
}

function BackButton({ onClick }: { onClick: () => void }) {
    return (
        <Button variant="ghost" onClick={onClick}>
            Back
        </Button>
    );
}

function FinishButton({ children }: { children: ReactNode }) {
    return (
        <Button asChild>
            <Link
                href={OnboardingController.update()}
                as="button"
                data-test="finish-onboarding"
            >
                {children}
            </Link>
        </Button>
    );
}

function CommitScript({ url }: { url: string }) {
    const command = `curl -fsSL ${url} | bash -s -- 'Hello world!'`;
    const [, copy] = useClipboard();
    const [isCopied, setIsCopied] = useState(false);

    useEffect(() => {
        if (!isCopied) {
            return;
        }

        const timeout = setTimeout(() => setIsCopied(false), 2000);

        return () => clearTimeout(timeout);
    }, [isCopied]);

    const copyCommand = async () => {
        if (await copy(command)) {
            setIsCopied(true);
            toast(
                <span className="font-mono text-[13px]">
                    Copied the command
                </span>,
            );

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: could not copy the command
            </span>,
        );
    };

    const Icon = isCopied ? Check : Copy;

    return (
        <div className="mt-3 flex items-start gap-2 rounded-md border bg-background py-2 pr-2 pl-3">
            <code className="min-w-0 flex-1 py-0.5 font-mono text-[13px] leading-5 wrap-anywhere">
                {command}
            </code>
            <Button
                variant="ghost"
                size="icon"
                className="size-7 shrink-0 text-muted-foreground"
                onClick={copyCommand}
                aria-label="Copy command"
            >
                <Icon />
            </Button>
        </div>
    );
}

export default function Show({
    preferredLane,
    automaticLane,
    suggestions,
    githubImport,
    commitScriptUrl,
}: {
    preferredLane: Lane | null;
    automaticLane: number;
    suggestions: SuggestedUser[];
    githubImport: GitHubImport | null;
    commitScriptUrl: string;
}) {
    const { auth } = usePage().props;
    const { user: currentUser } = useUser();
    const [step, setStep] = useRemember(0, 'onboarding-step');
    const [selectedLane, setSelectedLane] = useState<Lane | null>(
        preferredLane,
    );
    const [isShowingShortcuts, setIsShowingShortcuts] = useState(false);
    const [hasEditedOnGitHub, setHasEditedOnGitHub] = useRemember(
        false,
        'onboarding-edited-on-github',
    );
    const keys = useKeyLabels();
    const headingRef = useRef<HTMLHeadingElement>(null);

    const importedFollowingCount = githubImport?.followingCount ?? 0;
    const isImporting = githubImport === null;
    const [hasGivenUp, setHasGivenUp] = useState(false);
    const wasImporting = useRef(isImporting);
    // The import runs in a queued job right after signing in, so it usually lands while the first steps are open,
    // before the follow step shows it...
    const { stop } = usePoll(
        1500,
        { only: ['githubImport'] },
        { autoStart: isImporting },
    );

    useEffect(() => {
        if (!isImporting) {
            stop();

            // The suggestions were picked before the import, so they may suggest people it just followed.
            if (wasImporting.current) {
                wasImporting.current = false;
                router.reload({ only: ['suggestions'] });
            }

            return;
        }

        // A failed import leaves nothing to wait for: the suggestions are enough.
        const timeout = setTimeout(() => {
            stop();
            setHasGivenUp(true);
        }, 15_000);

        return () => clearTimeout(timeout);
    }, [isImporting, stop]);

    const next = () => setStep(step + 1);
    const back = () => setStep(step - 1);

    useEffect(() => {
        headingRef.current?.focus();
    }, [step]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.defaultPrevented ||
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                document.querySelector('[role="dialog"][data-state="open"]')
            ) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                router.visit(OnboardingController.update());
            }

            if (event.key === '?') {
                event.preventDefault();
                setIsShowingShortcuts(true);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const laneName =
        selectedLane === null
            ? 'auto'
            : typeof selectedLane === 'string'
              ? selectedLane
              : laneNames[selectedLane];

    const steps = [
        {
            command: 'whoami',
            title: 'Your profile, mirrored from GitHub',
            content: (
                <StepContent
                    headingRef={headingRef}
                    title="This is you, according to GitHub."
                    description={
                        <WordmarkText>
                            /dev/null copies your GitHub profile every time you
                            sign in, so there's nothing to fill in. To change
                            any of it, change it on GitHub, then sync it here.
                        </WordmarkText>
                    }
                    actions={
                        <>
                            <Button onClick={next}>Looks right</Button>
                            <Button variant="outline" asChild>
                                <a
                                    href="https://github.com/settings/profile"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    onClick={() => setHasEditedOnGitHub(true)}
                                >
                                    Edit on GitHub
                                    <ArrowUpRight />
                                </a>
                            </Button>
                            {hasEditedOnGitHub && (
                                <Form
                                    {...UserProfileController.update.form()}
                                    options={{
                                        preserveScroll: true,
                                        preserveState: true,
                                    }}
                                    onSuccess={() => router.flushAll()}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                            aria-busy={processing}
                                            data-test="sync-profile-button"
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <RefreshCw />
                                            )}
                                            Sync from GitHub
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </>
                    }
                >
                    <GitHubProfile />
                </StepContent>
            ),
        },
        {
            command: `export LANE=${laneName}`,
            title: 'Your color on the graph',
            content: (
                <Form
                    {...UserLaneController.update.form()}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={next}
                >
                    {({ processing, errors }) => (
                        <StepContent
                            headingRef={headingRef}
                            title="Pick your lane."
                            description="Your color on the graph: the rail beside your commits, the ring around your avatar, the dot before your handle. Auto picks one from your account id. You can change it later in Settings."
                            actions={
                                <>
                                    {selectedLane === preferredLane ? (
                                        <Button type="button" onClick={next}>
                                            Continue
                                        </Button>
                                    ) : (
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="save-lane-button"
                                        >
                                            Save color
                                        </Button>
                                    )}
                                    <BackButton onClick={back} />
                                </>
                            }
                        >
                            <LanePreview
                                lane={laneColor(selectedLane ?? automaticLane)}
                            />
                            <div className="mt-5">
                                <LaneOptions
                                    selected={selectedLane}
                                    automaticLane={automaticLane}
                                    onSelect={setSelectedLane}
                                />
                            </div>
                            <InputError
                                message={errors.lane ?? errors.color}
                                className="mt-2"
                            />
                        </StepContent>
                    )}
                </Form>
            ),
        },
        {
            command: 'who --should-i-follow',
            title: 'A few developers to follow',
            content: (
                <StepContent
                    headingRef={headingRef}
                    title={
                        importedFollowingCount > 0
                            ? `You already follow ${developers(importedFollowingCount)}.`
                            : 'Follow a few developers.'
                    }
                    description={
                        importedFollowingCount > 0
                            ? "We brought your GitHub network over, so your log doesn't start empty. Follow a few more to fill it up; unfollowing is one click."
                            : 'Your log is your commits plus the commits of everyone you follow. Here are a few to start with; unfollowing is one click.'
                    }
                    actions={
                        <>
                            <Button onClick={next}>Continue</Button>
                            <BackButton onClick={back} />
                        </>
                    }
                >
                    <GitHubImportCard
                        githubImport={githubImport}
                        hasGivenUp={hasGivenUp}
                        handle={currentUser.handle}
                    />
                    {importedFollowingCount > 0 && suggestions.length > 0 && (
                        <p className="mb-3 font-mono text-[13px] text-muted-foreground">
                            # more developers you may like
                        </p>
                    )}
                    {suggestions.length > 0 ? (
                        <ul className="divide-y rounded-lg border bg-card">
                            {suggestions.map((user) => (
                                <SuggestedUserRow
                                    key={user.id}
                                    user={user}
                                    className="p-3"
                                    buttonClassName="min-w-[90px] px-2"
                                />
                            ))}
                        </ul>
                    ) : (
                        <div className="rounded-lg border bg-card px-4 py-5">
                            <p className="font-mono text-[13px] text-muted-foreground">
                                fatal: no developers to suggest yet
                            </p>
                            <p className="mt-1 text-[15px] text-pretty">
                                You're early. Once people start committing,
                                you'll find them in the Latest log.
                            </p>
                        </div>
                    )}
                </StepContent>
            ),
        },
        {
            command: 'man devnull',
            title: 'The basics',
            content: (
                <StepContent
                    headingRef={headingRef}
                    title="The basics."
                    description="Three things worth knowing. Everything else, you'll figure out."
                    actions={
                        <>
                            <FinishButton>Take me to the log</FinishButton>
                            <BackButton onClick={back} />
                        </>
                    }
                >
                    <dl className="divide-y rounded-lg border bg-card text-[15px]">
                        <div className="p-4">
                            <dt className="font-semibold">Write a commit</dt>
                            <dd className="mt-1 text-pretty text-muted-foreground">
                                The composer sits at the top of your log. Plain
                                text, up to 4,000 characters; feeds fold long
                                ones after 280. Press <Kbd>c</Kbd> to jump to
                                it, then <Kbd>{keys.mod}</Kbd> <Kbd>↵</Kbd> to
                                commit.
                            </dd>
                        </div>
                        <div className="p-4">
                            <dt className="font-semibold">Use the keyboard</dt>
                            <dd className="mt-1 text-pretty text-muted-foreground">
                                <Kbd>j</Kbd> and <Kbd>k</Kbd> move between
                                commits, <Kbd>r</Kbd> replies,{' '}
                                <Kbd>{keys.mod}</Kbd> <Kbd>K</Kbd> finds people.
                                Press <Kbd>?</Kbd> anywhere for the full list.
                                It works here too.
                                <span className="mt-3 block">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setIsShowingShortcuts(true)
                                        }
                                        aria-keyshortcuts="?"
                                    >
                                        Show shortcuts
                                    </Button>
                                </span>
                            </dd>
                        </div>
                        <div className="p-4">
                            <dt className="font-semibold">
                                Commit from the terminal
                            </dt>
                            <dd className="mt-1 text-pretty text-muted-foreground">
                                No browser, no token. The script signs your
                                commit with an SSH key that's already on your
                                GitHub account.
                                <CommitScript url={commitScriptUrl} />
                                <a
                                    href={commitScriptUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="mt-2 inline-flex items-center gap-1 text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    Read it before you pipe it into bash
                                    <ArrowUpRight className="size-3.5" />
                                </a>
                            </dd>
                        </div>
                    </dl>
                </StepContent>
            ),
        },
    ];

    const stateOf = (index: number): StepState =>
        index < step ? 'done' : index === step ? 'current' : 'todo';

    return (
        <>
            <Head title="Setup" />

            <div
                className="mx-auto flex min-h-svh w-full max-w-2xl flex-col px-4 pt-6 pb-16 sm:px-8 sm:pt-10"
                style={
                    {
                        '--lane': laneColor(selectedLane ?? automaticLane),
                    } as CSSProperties
                }
            >
                <header className="flex items-center justify-between gap-4">
                    <AppLogo />
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-muted-foreground"
                        asChild
                    >
                        <Link
                            href={OnboardingController.update()}
                            as="button"
                            aria-keyshortcuts="Escape"
                            data-test="skip-onboarding"
                        >
                            Skip setup
                            <span className="hidden sm:inline">
                                <Kbd>esc</Kbd>
                            </span>
                        </Link>
                    </Button>
                </header>

                <main className="mt-10 sm:mt-14">
                    <p className="mb-8 pl-10 text-sm text-muted-foreground sm:pl-14">
                        Welcome, @{currentUser.handle}. Four steps, then your
                        log. Skip whenever.
                    </p>

                    <ol aria-label="Setup steps">
                        {steps.map((item, index) => (
                            <StepRow
                                key={index}
                                state={stateOf(index)}
                                command={item.command}
                                title={item.title}
                            >
                                {item.content}
                            </StepRow>
                        ))}
                        <StepRow
                            state="todo"
                            command="git log"
                            title="Then your log"
                            isLast
                        />
                    </ol>
                </main>
            </div>

            <KeyboardShortcutsDialog
                open={isShowingShortcuts}
                onOpenChange={setIsShowingShortcuts}
                canModerate={auth.isAdmin}
                hasRemotes={auth.remotes !== null}
            />
        </>
    );
}
