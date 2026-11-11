import { Link, usePage } from '@inertiajs/react';
import type { RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';
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
import { WordmarkText } from '@/components/wordmark-text';
import { loadTurnstile } from '@/lib/turnstile';

const rules = [
    [
        'No engagement bait',
        '"Like if you agree", "Follow for more", "Reply with your stack": posts that fish for stars, replies, or follows instead of saying something.',
    ],
    [
        'No low-effort posts',
        'One-word commits, "gm", "test", copy-pasted memes, and the same post over and over. Commit something a developer would want to read.',
    ],
    [
        'No AI-generated posts',
        "Write your own commits. Generated threads, hot takes, and summaries passed off as your own don't belong here. Sharing code or output you got from a tool is fine; say so.",
    ],
    [
        'No spam or self-promotion',
        'Promote your work by talking about it, not by dropping links, referral codes, or the same launch in every thread.',
    ],
    ['Be decent', 'No harassment, hate, or private information about anyone.'],
] as const;

// The server learns the user read the rules from the commit that sends them. Replies don't reload the page, so the
// shared prop can lag behind: until it does, the first commit sent is remembered here.
const acceptedThisVisit = new Map<string, string>();

/**
 * Until a first-time poster sends their first commit, the composer stops at every attempt to show what doesn't belong
 * on /dev/null. Keep editing brings it back on the next one; only sending with it ends it.
 */
export function useCommitRules() {
    const { auth } = usePage().props;
    const [showing, setShowing] = useState(false);

    const hasAccepted = () =>
        auth.user !== null &&
        (!auth.mustReadCommitRules || acceptedThisVisit.has(auth.user.id));

    const beforeSubmit = () => {
        if (auth.user === null || hasAccepted()) {
            return true;
        }

        setShowing(true);

        return false;
    };

    const agree = (humanVerificationToken: string) => {
        if (auth.user !== null) {
            acceptedThisVisit.set(auth.user.id, humanVerificationToken);
        }

        setShowing(false);
    };

    const humanVerificationToken = () =>
        auth.user === null ? '' : (acceptedThisVisit.get(auth.user.id) ?? '');

    return {
        showing,
        setShowing,
        beforeSubmit,
        agree,
        hasAccepted,
        humanVerificationToken,
    };
}

export function CommitRulesDialog({
    rules: commitRules,
    textarea,
}: {
    rules: ReturnType<typeof useCommitRules>;
    textarea: RefObject<HTMLTextAreaElement | null>;
}) {
    return (
        <Dialog
            open={commitRules.showing}
            onOpenChange={commitRules.setShowing}
        >
            <DialogContent
                className="max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] overflow-y-auto"
                data-test="commit-rules-dialog"
            >
                <CommitRulesContent rules={commitRules} textarea={textarea} />
            </DialogContent>
        </Dialog>
    );
}

function CommitRulesContent({
    rules: commitRules,
    textarea,
}: {
    rules: ReturnType<typeof useCommitRules>;
    textarea: RefObject<HTMLTextAreaElement | null>;
}) {
    const [humanVerificationToken, setHumanVerificationToken] = useState('');

    return (
        <>
            <DialogTitle>Before your first commit</DialogTitle>
            <DialogDescription>
                <WordmarkText>
                    {
                        "Welcome! /dev/null is for developers talking to developers, so here's a quick heads-up on what doesn't fit here."
                    }
                </WordmarkText>
            </DialogDescription>

            <ul className="grid gap-3 border-y py-4">
                {rules.map(([title, description]) => (
                    <li key={title} className="flex gap-3">
                        <span
                            aria-hidden
                            className="mt-2 size-1.5 shrink-0 rounded-full bg-muted-foreground"
                        />
                        <div className="min-w-0 text-sm">
                            <p className="font-medium">{title}</p>
                            <p className="text-pretty text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </li>
                ))}
            </ul>

            <p className="text-sm text-muted-foreground">
                The full rules are in the{' '}
                <Link
                    href={`${TermsController.show.url()}#rules`}
                    target="_blank"
                    className="text-foreground underline underline-offset-4"
                >
                    terms
                </Link>
                .
            </p>

            <HumanVerification onToken={setHumanVerificationToken} />

            <DialogFooter className="gap-2">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Keep editing
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    disabled={humanVerificationToken === ''}
                    onClick={() => {
                        commitRules.agree(humanVerificationToken);
                        textarea.current?.form?.requestSubmit();
                    }}
                    data-test="agree-commit-rules-button"
                >
                    Got it, commit
                </Button>
            </DialogFooter>
        </>
    );
}

function HumanVerification({ onToken }: { onToken: (token: string) => void }) {
    const { humanVerificationSiteKey } = usePage().props;
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const element = container.current;

        if (element === null) {
            return;
        }

        const widget = loadTurnstile().then((turnstile) => ({
            turnstile,
            widgetId: turnstile.render(element, {
                sitekey: humanVerificationSiteKey,
                size: 'flexible',
                theme: document.documentElement.classList.contains('dark')
                    ? 'dark'
                    : 'light',
                callback: onToken,
                'expired-callback': () => onToken(''),
                'error-callback': () => onToken(''),
            }),
        }));

        return () => {
            void widget.then(({ turnstile, widgetId }) =>
                turnstile.remove(widgetId),
            );
        };
    }, [humanVerificationSiteKey, onToken]);

    return (
        <div
            ref={container}
            className="h-[65px]"
            data-test="human-verification"
        />
    );
}
