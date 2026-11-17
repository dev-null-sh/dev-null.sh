import { Form, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    EllipsisVertical,
    GitCommitVertical,
    Mail,
    UsersRound,
    X,
} from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import JoinLinkController from '@/actions/App/Http/Controllers/JoinLinkController';
import { GitHubIcon } from '@/components/github-icon';
import { Button } from '@/components/ui/button';
import { trackJoinLinkSent } from '@/lib/analytics';
import type { InAppBrowser } from '@/lib/in-app-browser';
import { inAppBrowser } from '@/lib/in-app-browser';
import { formatExactCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import { login } from '@/routes';

const DISMISSED = 'devnull:join-sheet-dismissed';

const listeners = new Set<() => void>();

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

function isDismissed(): boolean {
    try {
        return sessionStorage.getItem(DISMISSED) !== null;
    } catch {
        return false;
    }
}

function dismiss(): void {
    try {
        sessionStorage.setItem(DISMISSED, '1');
    } catch {
        // Without storage, it opens again on the next page.
    }

    listeners.forEach((listener) => listener());
}

/**
 * On a phone, guests don't see the aside that tells desktop visitors what /dev/null is, so this sheet does, with the
 * button where the thumb is. Its title says how many developers are here: a trending commit with two stars could
 * otherwise pass for an empty network. It stays out of the server render and opens once the page has loaded, unless the guest
 * closed it earlier in this browser session. Signing in leaves for GitHub, so the button is a plain link. In an app's
 * browser, it also shows how to open the page in the phone's browser, where the guest may already be signed in to
 * GitHub. Someone who isn't signed in to GitHub on their phone at all can get a link by email, to join from their
 * computer.
 */
export function GuestJoinSheet() {
    const isHidden = useSyncExternalStore(subscribe, isDismissed, () => true);
    const { developersCount } = usePage().props;
    const [step, setStep] = useState<'pitch' | 'email' | 'sent'>('pitch');

    if (isHidden) {
        return null;
    }

    // The sheet never renders on the server, so the user agent is there to read...
    const browser = inAppBrowser(navigator.userAgent);

    return (
        <section
            aria-labelledby="guest-join-sheet"
            className={cn(
                'fixed inset-x-0 bottom-0 z-30 flex flex-col gap-3.5 rounded-t-2xl border-t border-input bg-card px-5 pt-4 pb-[calc(1.75rem+env(safe-area-inset-bottom))] shadow-[0_-16px_40px_rgb(0_0_0/0.35)] duration-300 motion-safe:animate-in motion-safe:slide-in-from-bottom md:hidden',
                // While typing an address, the sheet sits on the keyboard, which iOS draws over the page...
                step === 'pitch'
                    ? '[:root[data-keyboard-open]_&]:hidden'
                    : 'bottom-[var(--visual-viewport-bottom,0px)]',
            )}
            data-test="guest-join-sheet"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex flex-col gap-1.5">
                    <p className="font-mono text-[13px] text-muted-foreground">
                        you&rsquo;re reading /dev/null
                    </p>
                    {/* It changes with each step, so screen readers hear that the link was sent... */}
                    <h2
                        id="guest-join-sheet"
                        aria-live="polite"
                        className="text-[22px] leading-tight font-semibold tracking-tight"
                    >
                        {step === 'email'
                            ? 'Join from your computer.'
                            : step === 'sent'
                              ? 'Sent. See you on your computer.'
                              : developersCount
                                ? `Join ${formatExactCount(developersCount)}+ developers.`
                                : 'Only developers post here.'}
                    </h2>
                </div>
                <button
                    type="button"
                    onClick={dismiss}
                    aria-label="Not now"
                    className="-mt-1.5 -mr-2.5 flex rounded-md p-2.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    data-test="guest-join-sheet-dismiss"
                >
                    <X className="size-5" />
                </button>
            </div>

            {step === 'pitch' && (
                <>
                    <ul className="flex flex-col gap-3.5 text-[15px] leading-snug">
                        <li className="flex items-start gap-3">
                            <GitHubIcon className="mt-px size-5 shrink-0 text-primary" />
                            Every account is a GitHub account.
                        </li>
                        <li className="flex items-start gap-3">
                            <UsersRound className="mt-px size-5 shrink-0 text-primary" />
                            You start out following the people you already
                            follow on GitHub.
                        </li>
                        <li className="flex items-start gap-3">
                            <GitCommitVertical className="mt-px size-5 shrink-0 text-primary" />
                            Posts are commits: plain text about what
                            you&rsquo;re building.
                        </li>
                    </ul>

                    <Button
                        className="mt-1.5 h-12 rounded-[10px] text-base"
                        asChild
                    >
                        <a
                            href={login().url}
                            data-test="guest-join-sheet-login-button"
                        >
                            <GitHubIcon className="size-[18px]" />
                            Join with GitHub
                        </a>
                    </Button>

                    <button
                        type="button"
                        onClick={() => setStep('email')}
                        className="-my-1.5 flex min-h-11 items-center justify-center gap-2 text-[15px] text-muted-foreground underline underline-offset-4"
                        data-test="guest-join-sheet-email-button"
                    >
                        <Mail className="size-[18px]" />
                        {browser === null
                            ? 'Not on your computer? Email me a link'
                            : 'Or email me a link for my computer'}
                    </button>

                    {/* Last in an app's browser, so the arrow points from the hint to the app's menu... */}
                    {browser !== null && <InAppBrowserHint browser={browser} />}
                </>
            )}

            {step === 'email' && (
                <JoinLinkForm onSent={() => setStep('sent')} />
            )}

            {step === 'sent' && (
                <>
                    <p className="text-[15px] leading-snug">
                        Open the link where you&rsquo;re signed in to GitHub:
                        joining takes a click there.
                    </p>
                    <p className="text-sm leading-snug text-muted-foreground">
                        Nothing in a few minutes? Check your spam folder, or{' '}
                        <button
                            type="button"
                            onClick={() => setStep('email')}
                            className="underline underline-offset-4"
                        >
                            try another address
                        </button>
                        .
                    </p>
                    <a
                        href={login().url}
                        className="flex min-h-11 items-center justify-center text-[15px] text-muted-foreground underline underline-offset-4"
                    >
                        Join with GitHub here anyway
                    </a>
                </>
            )}
        </section>
    );
}

/**
 * The page being read, without its query when that's longer than the server takes, since the guest can't fix it.
 */
function linkPath(): string {
    const path = window.location.pathname + window.location.search;

    return path.length <= 2000 ? path : window.location.pathname;
}

/**
 * The link leads back to the page being read. `homepage` is a field people never see, and bots fill in.
 */
function JoinLinkForm({ onSent }: { onSent: () => void }) {
    return (
        <Form
            {...JoinLinkController.store.form()}
            options={{ preserveScroll: true, preserveState: true }}
            onSuccess={() => {
                trackJoinLinkSent();
                onSent();
            }}
            className="flex flex-col gap-2"
            data-test="guest-join-sheet-email-form"
        >
            {({ errors, processing }) => (
                <>
                    <label
                        htmlFor="join-link-email"
                        className="text-[15px] leading-snug"
                    >
                        We&rsquo;ll email you a link to this page.
                    </label>
                    <div className="flex gap-2">
                        <input
                            id="join-link-email"
                            type="email"
                            name="email"
                            required
                            autoFocus
                            autoComplete="email"
                            placeholder="you@example.com"
                            aria-invalid={errors.email ? true : undefined}
                            aria-describedby={
                                errors.email ? 'join-link-error' : undefined
                            }
                            className="h-12 min-w-0 flex-1 rounded-[10px] border border-input bg-transparent px-3.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30"
                        />
                        <Button
                            type="submit"
                            variant="secondary"
                            disabled={processing}
                            className="h-12 rounded-[10px] px-5 text-base"
                        >
                            Send
                        </Button>
                    </div>
                    <input type="hidden" name="path" value={linkPath()} />
                    <input
                        type="text"
                        name="homepage"
                        tabIndex={-1}
                        autoComplete="off"
                        aria-hidden
                        className="sr-only"
                    />
                    {errors.email && (
                        <p
                            id="join-link-error"
                            className="text-sm text-destructive"
                        >
                            {errors.email}
                        </p>
                    )}
                    <p className="text-sm leading-snug text-muted-foreground">
                        One email, and we won&rsquo;t write again. No
                        newsletter.
                    </p>
                </>
            )}
        </Form>
    );
}

/**
 * Apps don't let a page open the phone's browser: X ignores Safari's undocumented scheme. Their own menu always can,
 * so the hint shows the way there, and points at it in X, whose menu sits right under the sheet.
 */
function InAppBrowserHint({ browser }: { browser: InAppBrowser }) {
    const name = browser.platform === 'ios' ? 'Safari' : 'your browser';
    const isX = browser.app === 'X' && browser.platform === 'ios';

    return (
        <div
            className="flex flex-col gap-1 rounded-[10px] bg-primary/10 px-4 py-3"
            data-test="guest-join-sheet-in-app"
        >
            <p className="text-sm leading-snug text-muted-foreground">
                {browser.app ? `${browser.app}’s` : 'This app’s'} browser
                isn&rsquo;t signed in to GitHub.{' '}
                {browser.platform === 'ios' ? 'Safari' : 'Your browser'} may be.
            </p>
            <p className="text-[17px] leading-snug font-semibold">
                {isX ? (
                    <>
                        Tap{' '}
                        <EllipsisVertical
                            aria-label="⋮"
                            className="inline size-5 -translate-y-px"
                        />{' '}
                        below, then{' '}
                        <span className="text-primary">Open in browser</span>.
                    </>
                ) : (
                    `Open this page in ${name} from the app’s menu.`
                )}
            </p>
            {isX && (
                <ArrowDown
                    aria-hidden
                    className="absolute bottom-1 left-[62%] size-6 -translate-x-1/2 text-primary motion-safe:animate-bounce"
                />
            )}
        </div>
    );
}
