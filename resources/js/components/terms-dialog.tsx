import { Link, router, usePage } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserTermsController';
import { TermsOfService } from '@/components/terms-of-service';
import { Button } from '@/components/ui/button';
import { WordmarkText } from '@/components/wordmark-text';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { clearRecentSearches } from '@/hooks/use-recent-searches';
import { clearStashes } from '@/hooks/use-stash';
import { settingsTabs } from '@/layouts/settings/tabs';
import { TERMS_EFFECTIVE_DATE } from '@/lib/legal';
import { logout } from '@/routes';
import { edit } from '@/routes/user-profile';
import type { User } from '@/types';

const pagesThatShowTheTerms = new Set(['terms/show', 'privacy/show']);

/**
 * Covers every page until the signed-in user accepts the terms in force. It can't be dismissed: the terms scroll inside
 * it, and the ways out are to accept them, to sign out, or to go to settings, where they can export their data or
 * delete their account. The terms, privacy, and every settings tab stay open without it.
 */
export function TermsDialog({ user }: { user: User }) {
    const {
        component,
        props: { auth },
    } = usePage();
    const [isAccepting, setIsAccepting] = useState(false);
    const { isCurrentUrl } = useCurrentUrl();
    const terms = useRef<HTMLDivElement>(null);
    const version = auth.termsToAccept;

    const accept = () => {
        if (version === null) {
            return;
        }

        router.put(
            update.url(),
            { version },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['auth'],
                onStart: () => setIsAccepting(true),
                onFinish: () => setIsAccepting(false),
                onError: () =>
                    toast.error(
                        'Could not accept the terms. Please try again.',
                    ),
            },
        );
    };

    const signOut = () => {
        clearStashes(user.id);
        clearRecentSearches(user.id);
        router.flushAll();
    };

    return (
        <DialogPrimitive.Root
            open={
                version !== null &&
                !pagesThatShowTheTerms.has(component) &&
                !settingsTabs.some((tab) => isCurrentUrl(tab.href))
            }
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-60 bg-black/80 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    data-test="terms-dialog"
                    onEscapeKeyDown={(event) => event.preventDefault()}
                    onInteractOutside={(event) => event.preventDefault()}
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        terms.current?.focus();
                    }}
                    className="fixed top-[calc(var(--visual-viewport-top,0px)+var(--visual-viewport-height,100dvh)/2)] left-1/2 z-60 flex max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] w-full max-w-[calc(100%-2rem)] -translate-1/2 flex-col overflow-hidden rounded-lg border bg-background shadow-lg duration-200 outline-none data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 sm:max-w-2xl"
                >
                    <div className="grid shrink-0 gap-2 border-b px-4 py-5 sm:px-6">
                        <DialogPrimitive.Title className="text-lg leading-none font-semibold">
                            We updated our terms
                        </DialogPrimitive.Title>
                        <DialogPrimitive.Description className="text-sm text-pretty text-muted-foreground">
                            <WordmarkText>
                                {`Our terms of service changed on ${TERMS_EFFECTIVE_DATE}. Read them below, and accept them to keep using /dev/null. If you don't accept them, you can export your data or delete your account in settings, or sign out and keep reading /dev/null as a guest.`}
                            </WordmarkText>
                        </DialogPrimitive.Description>
                    </div>

                    <div
                        ref={terms}
                        tabIndex={0}
                        aria-label="Terms of service"
                        className="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-card outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                    >
                        <article className="divide-y text-[15px] leading-relaxed">
                            <TermsOfService />
                        </article>
                    </div>

                    <div className="flex shrink-0 flex-col-reverse gap-2 border-t px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                        <Button variant="outline" asChild>
                            <Link
                                href={logout()}
                                as="button"
                                onClick={signOut}
                                data-test="terms-sign-out-button"
                            >
                                Sign out
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={edit()}
                                data-test="terms-settings-button"
                            >
                                Settings
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            onClick={accept}
                            disabled={isAccepting}
                            data-test="accept-terms-button"
                        >
                            Accept the terms
                        </Button>
                    </div>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
