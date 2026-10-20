import { router, useHttp, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/CommitPastelController';
import { ActionTooltip } from '@/components/action-tooltip';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { useNow } from '@/hooks/use-now';
import { refreshSharedState, useSharedState } from '@/hooks/use-shared-state';
import { commitStateKey } from '@/lib/commit-state';
import { toastWhenThrottled } from '@/lib/throttle';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

const exhaustedMessage = 'you ran out of pastéis. wait until tomorrow.';
// One award per user at a time, even across midnight, so a response is never
// reconciled against another award still in flight.
const awarding = new Set<string>();

export function CommitPastelButton({
    commit,
    className,
}: {
    commit: Commit;
    className?: string;
}) {
    const { auth } = usePage().props;
    const commitNotFound = useCommitNotFound();
    const day = useNow().toISOString().slice(0, 10);
    const [count, setCount] = useSharedState(
        commitStateKey('pasteis_count', commit.id),
        commit.pasteis_count,
    );
    const [remaining, setRemaining] = useSharedState(
        `pasteis-allowance:${auth.user?.id}:${day}`,
        auth.pasteis?.day === day ? auth.pasteis.remaining : 10,
    );
    const request = useHttp<
        Record<string, never>,
        {
            pasteis_count: number;
            remaining: number;
            day: string;
            recipient: { id: string; pasteis_received: number };
        }
    >({});
    const busy = useRef(false);
    const emoji = useRef<HTMLImageElement>(null);

    if (!auth.pasteis || !auth.user) {
        return null;
    }

    const userId = auth.user.id;
    const isOwn = commit.user_id === userId;
    const readOnly = isOwn || commit.status !== 'approved' || !commit.user_id;
    const label = `${count} ${count === 1 ? 'pastel' : 'pastéis'}`;

    const award = async () => {
        if (busy.current || awarding.has(userId)) {
            return;
        }

        busy.current = true;
        awarding.add(userId);
        let errorShown = false;
        let exhausted = false;

        let reserved = 0;
        let optimistic = 0;

        setCount((current) => (optimistic = current + 1));
        setRemaining((current) => {
            reserved = Math.min(1, Math.max(0, current));

            return current - reserved;
        });
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            emoji.current
                ?.getAnimations()
                .forEach((animation) => animation.cancel());
            emoji.current?.animate(
                { transform: ['scale(1)', 'scale(1.25)', 'scale(1)'] },
                { duration: 300, easing: 'ease-out' },
            );
        }

        try {
            const response = await request.submit(store(commit), {
                onError: () => {
                    errorShown = true;
                    exhausted = true;
                    // A refresh since the click already holds the server count.
                    setCount((current) =>
                        current === optimistic
                            ? Math.max(0, current - 1)
                            : current,
                    );
                    refreshSharedState(
                        new Map([[`pasteis-allowance:${userId}:${day}`, 0]]),
                    );
                    toast(exhaustedMessage, { id: 'pasteis-exhausted' });
                },
                onHttpException: (response) => {
                    errorShown =
                        commitNotFound(commit.id, response) ||
                        toastWhenThrottled(response);
                    return true;
                },
            });

            setCount((current) => Math.max(current, response.pasteis_count));
            refreshSharedState(
                new Map([
                    [
                        `pasteis-allowance:${userId}:${response.day}`,
                        response.remaining,
                    ],
                    [
                        commitStateKey(
                            'pasteis_received',
                            response.recipient.id,
                        ),
                        response.recipient.pasteis_received,
                    ],
                ]),
            );
            router.flushAll();

            if (response.remaining === 0) {
                toast(exhaustedMessage, { id: 'pasteis-exhausted' });
            }
        } catch {
            if (!exhausted) {
                setCount((current) =>
                    current === optimistic ? Math.max(0, current - 1) : current,
                );
                setRemaining((current) => current + reserved);
            }

            if (!errorShown) {
                toast.error('Could not award a pastel. Please try again.');
            }
        } finally {
            awarding.delete(userId);
            busy.current = false;
        }
    };

    const content = (
        <>
            <img
                ref={emoji}
                src="/pastel-de-nata.svg"
                alt=""
                width={20}
                height={20}
                className="size-5 shrink-0 object-contain"
            />
            <span className="min-w-[1ch] text-left tabular-nums">{count}</span>
        </>
    );
    const classes = cn(
        'inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[13px] text-muted-foreground',
        className,
    );

    return (
        <ActionTooltip
            label={
                readOnly
                    ? label
                    : `Give a pastel · ${remaining} left today · resets at 00:00 UTC`
            }
        >
            {readOnly ? (
                <span
                    aria-label={label}
                    className={classes}
                    data-test="pastel-count-readonly"
                >
                    {content}
                </span>
            ) : (
                <button
                    type="button"
                    onClick={award}
                    aria-disabled={request.processing}
                    aria-label={`Give a pastel, ${label}, ${remaining} left today`}
                    className={cn(
                        classes,
                        'transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring',
                    )}
                    data-test="award-pastel-button"
                >
                    {content}
                </button>
            )}
        </ActionTooltip>
    );
}
