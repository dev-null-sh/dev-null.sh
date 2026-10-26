import {
    Form,
    Head,
    Link,
    router,
    usePage,
    useRemember,
} from '@inertiajs/react';
import { ArrowUpRight, Check, Copy, RefreshCw } from 'lucide-react';
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
import { SuggestedUserRow } from '@/components/who-to-follow';
import { WordmarkText } from '@/components/wordmark-text';
import { useClipboard } from '@/hooks/use-clipboard';
import { useUser } from '@/hooks/use-user';
import { useKeyLabels } from '@/lib/keyboard';
import { cn, laneColor } from '@/lib/utils';
import type { Lane, SuggestedUser } from '@/types';

const laneNames = ['orange', 'green', 'violet', 'teal', 'rose', 'ochre'];

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
    commitScriptUrl,
}: {
    preferredLane: Lane | null;
    automaticLane: number;
    suggestions: SuggestedUser[];
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
                    title="Follow a few developers."
                    description="Your log is your commits plus the commits of everyone you follow. Here are a few to start with; unfollowing is one click."
                    actions={
                        <>
                            <Button onClick={next}>Continue</Button>
                            <BackButton onClick={back} />
                        </>
                    }
                >
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
            />
        </>
    );
}
