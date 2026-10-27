import { GitCommitVertical, UsersRound, X } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { GitHubIcon } from '@/components/github-icon';
import { Button } from '@/components/ui/button';
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
 * button where the thumb is. It stays out of the server render and opens once the page has loaded, unless the guest
 * closed it earlier in this browser session. Signing in leaves for GitHub, so the button is a plain link.
 */
export function GuestJoinSheet() {
    const isHidden = useSyncExternalStore(subscribe, isDismissed, () => true);

    if (isHidden) {
        return null;
    }

    return (
        <section
            aria-labelledby="guest-join-sheet"
            className="fixed inset-x-0 bottom-0 z-30 flex flex-col gap-3.5 rounded-t-2xl border-t border-input bg-card px-5 pt-4 pb-[calc(1.75rem+env(safe-area-inset-bottom))] shadow-[0_-16px_40px_rgb(0_0_0/0.35)] duration-300 motion-safe:animate-in motion-safe:slide-in-from-bottom md:hidden [:root[data-keyboard-open]_&]:hidden"
            data-test="guest-join-sheet"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex flex-col gap-1.5">
                    <p className="font-mono text-[13px] text-muted-foreground">
                        you&rsquo;re reading /dev/null
                    </p>
                    <h2
                        id="guest-join-sheet"
                        className="text-[22px] leading-tight font-semibold tracking-tight"
                    >
                        Only developers post here.
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

            <ul className="flex flex-col gap-3.5 text-[15px] leading-snug">
                <li className="flex items-start gap-3">
                    <GitHubIcon className="mt-px size-5 shrink-0 text-primary" />
                    Every account is a GitHub account.
                </li>
                <li className="flex items-start gap-3">
                    <UsersRound className="mt-px size-5 shrink-0 text-primary" />
                    You start out following the people you already follow on
                    GitHub.
                </li>
                <li className="flex items-start gap-3">
                    <GitCommitVertical className="mt-px size-5 shrink-0 text-primary" />
                    Posts are commits: plain text about what you&rsquo;re
                    building.
                </li>
            </ul>

            <Button className="mt-1.5 h-12 rounded-[10px] text-base" asChild>
                <a href={login().url} data-test="guest-join-sheet-login-button">
                    <GitHubIcon className="size-[18px]" />
                    Join with GitHub
                </a>
            </Button>
        </section>
    );
}
