import { Link } from '@inertiajs/react';
import { Hourglass } from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import TermsController from '@/actions/App/Http/Controllers/TermsController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { useDateTime } from '@/hooks/use-date-time';
import type { TimeoutFlash } from '@/types/ui';

let current: TimeoutFlash | null = null;
const listeners = new Set<() => void>();

function setTimeoutShown(timeout: TimeoutFlash | null): void {
    current = timeout;
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Opens the dialog that tells a user in timeout why their post didn't go through, wherever they tried to post.
 */
export function showTimeout(timeout: TimeoutFlash): void {
    setTimeoutShown(timeout);
}

/**
 * The timeout details in a refused request's JSON, from the reply composer or remotes, if that's why it was refused.
 * The body may still be raw text, which isn't JSON when a proxy answers with an error page.
 */
export function timeoutFrom(
    status: number,
    body: unknown,
): TimeoutFlash | null {
    if (status !== 403) {
        return null;
    }

    if (typeof body === 'string') {
        try {
            body = JSON.parse(body);
        } catch {
            return null;
        }
    }

    if (typeof body !== 'object' || body === null) {
        return null;
    }

    const { timeout } = body as { timeout?: TimeoutFlash };

    return timeout ?? null;
}

/**
 * Mounted once in the app layout, so any composer can open it.
 */
export function TimeoutDialog() {
    const { formatFullDateTime } = useDateTime();
    const shown = useSyncExternalStore(
        subscribe,
        () => current,
        () => null,
    );
    // The last timeout stays rendered while the dialog fades out...
    const [timeout, setRendered] = useState(shown);

    if (shown !== null && shown !== timeout) {
        setRendered(shown);
    }

    return (
        <Dialog
            open={shown !== null}
            onOpenChange={(open) => !open && setTimeoutShown(null)}
        >
            <DialogContent data-test="timeout-dialog">
                <span
                    aria-hidden
                    className="flex size-10 items-center justify-center rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400"
                >
                    <Hourglass className="size-5" />
                </span>
                <DialogTitle>You're in timeout</DialogTitle>
                {timeout && (
                    <DialogDescription asChild>
                        <div className="grid gap-3">
                            <p>
                                A moderator paused your posting. Reason:{' '}
                                <span className="text-foreground">
                                    {timeout.reason}
                                </span>
                                . You can still read, star, and follow, but you
                                can't commit, reply, or send remotes for{' '}
                                <span className="text-foreground">
                                    {timeout.remaining}
                                </span>
                                .
                            </p>
                            <p>
                                You can post again on{' '}
                                {formatFullDateTime(timeout.ends_at)}. Nothing
                                you wrote was lost. Until then, have a look at
                                the{' '}
                                <Link
                                    href={`${TermsController.show.url()}#rules`}
                                    className="text-foreground underline underline-offset-4"
                                    onClick={() => setTimeoutShown(null)}
                                >
                                    terms
                                </Link>
                                .
                            </p>
                        </div>
                    </DialogDescription>
                )}
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button">Got it</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
