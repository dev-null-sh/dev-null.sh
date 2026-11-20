import { router, useHttp, usePage } from '@inertiajs/react';
import { useEffect, useEffectEvent, useRef } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/CommitPastelController';
import { PastelCollection } from '@/components/pastel-collection';
import { PastelIcon } from '@/components/pastel-icon';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { useNow } from '@/hooks/use-now';
import {
    refreshSharedState,
    sharedValue,
    useSharedState,
} from '@/hooks/use-shared-state';
import { commitStateKey } from '@/lib/commit-state';
import { formatCount, formatExactCount } from '@/lib/numbers';
import { toastWhenThrottled } from '@/lib/throttle';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

const exhaustedMessage = 'you ran out of pastéis. wait until tomorrow.';
// One award per user at a time, even across midnight, so a response is never
// reconciled against another award still in flight.
const awarding = new Map<string, Map<string, number>>();

export function CommitPastelButton({
    commit,
    className,
}: {
    commit: Commit;
    className?: string;
}) {
    const { auth } = usePage().props;
    const commitNotFound = useCommitNotFound();
    const now = useNow();
    const day = now.toISOString().slice(0, 10);
    const [count, setCount] = useSharedState(
        commitStateKey('pasteis_count', commit.id),
        commit.pasteis_count,
    );
    const [remaining, setRemaining] = useSharedState(
        `pasteis-allowance:${auth.user?.id}:${day}`,
        auth.pasteis?.day === day ? auth.pasteis.remaining : 10,
    );
    // What is left to give is refreshed live when pastéis go out from another device. It only goes down within a day,
    // so an older count never brings back what was spent since.
    const liveRemaining =
        auth.pasteis?.day === day ? auth.pasteis.remaining : null;

    const spendElsewhere = useEffectEvent((left: number) => {
        if (auth.user && !awarding.has(auth.user.id)) {
            setRemaining((current) => Math.min(current, left));
        }
    });

    useEffect(() => {
        if (liveRemaining !== null) {
            spendElsewhere(liveRemaining);
        }
    }, [liveRemaining]);

    const week = new Date(
        Date.UTC(
            now.getUTCFullYear(),
            now.getUTCMonth(),
            now.getUTCDate() + ((8 - now.getUTCDay()) % 7 || 7),
        ),
    )
        .toISOString()
        .replace('.000Z', 'Z');
    const liveDiamondRemaining =
        auth.pasteis?.diamond_resets_at === week
            ? (auth.pasteis.diamond_remaining ?? 0)
            : 1;
    const [diamondRemaining, setDiamondRemaining] = useSharedState(
        `diamond-allowance:${auth.user?.id}:${week}`,
        liveDiamondRemaining,
    );
    const [diamondCount, setDiamondCount] = useSharedState(
        commitStateKey('pasteis_diamond_count', commit.id),
        commit.pasteis_diamond_count ?? 0,
    );
    const [frostedCount, setFrostedCount] = useSharedState(
        commitStateKey('pasteis_frosted_count', commit.id),
        commit.pasteis_frosted_count ?? 0,
    );
    const spendDiamondElsewhere = useEffectEvent((left: number) => {
        if (!auth.user) return;
        const pending = awarding.get(auth.user.id);
        if (pending) {
            pending.set(week, Math.min(pending.get(week) ?? left, left));
        } else {
            setDiamondRemaining((current) => Math.min(current, left));
        }
    });
    useEffect(() => {
        spendDiamondElsewhere(liveDiamondRemaining);
    }, [liveDiamondRemaining, week]);

    const request = useHttp<
        { variant: 'original' | 'diamond' },
        {
            pasteis_count: number;
            remaining: number;
            pasteis_diamond_count: number;
            pasteis_frosted_count: number;
            diamond_remaining: number;
            diamond_resets_at: string;
            day: string;
            recipient: {
                id: string;
                pasteis_received: number;
                pasteis_diamond_received: number;
                pasteis_frosted_received: number;
            };
        }
    >({ variant: 'original' });
    const busy = useRef(false);
    const emoji = useRef<HTMLSpanElement>(null);

    // An organization's commits take no pastéis: they would go to whoever posted them.
    if (!auth.pasteis || !auth.user || commit.author.type === 'organization') {
        return null;
    }

    const userId = auth.user.id;
    const isOwn = commit.author.id === userId;
    const readOnly = isOwn || commit.status !== 'approved';
    const label = `${formatExactCount(count)} ${count === 1 ? 'pastel' : 'pastéis'}`;

    const award = async (variant: 'original' | 'diamond') => {
        if (busy.current || awarding.has(userId)) {
            return;
        }

        busy.current = true;
        const pendingDiamonds = new Map<string, number>();
        awarding.set(userId, pendingDiamonds);
        let errorShown = false;
        let exhausted = false;

        let reserved = 0;
        let optimistic = 0;

        setCount((current) => (optimistic = current + 1));
        if (variant === 'diamond') setDiamondRemaining(0);
        else
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

        request.transform(() => ({ variant }));
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
                    if (variant === 'diamond') {
                        setDiamondRemaining(0);
                        toast(
                            'Your next Diamond arrives on Monday at 00:00 UTC.',
                            { id: 'diamond-exhausted' },
                        );
                    } else {
                        refreshSharedState(
                            new Map([
                                [`pasteis-allowance:${userId}:${day}`, 0],
                            ]),
                        );
                        toast(exhaustedMessage, { id: 'pasteis-exhausted' });
                    }
                },
                onHttpException: (response) => {
                    errorShown =
                        commitNotFound(commit.id, response) ||
                        toastWhenThrottled(response);
                    return true;
                },
            });

            setCount((current) => Math.max(current, response.pasteis_count));
            setDiamondCount((current) =>
                Math.max(current, response.pasteis_diamond_count),
            );
            setFrostedCount((current) =>
                Math.max(current, response.pasteis_frosted_count),
            );
            setDiamondRemaining(response.diamond_remaining);
            refreshSharedState(
                new Map([
                    [
                        `diamond-allowance:${userId}:${response.diamond_resets_at}`,
                        response.diamond_remaining,
                    ],
                    [
                        commitStateKey(
                            'pasteis_diamond_received',
                            response.recipient.id,
                        ),
                        response.recipient.pasteis_diamond_received,
                    ],
                    [
                        commitStateKey(
                            'pasteis_frosted_received',
                            response.recipient.id,
                        ),
                        response.recipient.pasteis_frosted_received,
                    ],
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

            if (variant === 'original' && response.remaining === 0) {
                toast(exhaustedMessage, { id: 'pasteis-exhausted' });
            }
        } catch {
            if (!exhausted) {
                setCount((current) =>
                    current === optimistic ? Math.max(0, current - 1) : current,
                );
                if (variant === 'diamond') setDiamondRemaining(1);
                else setRemaining((current) => current + reserved);
            }

            if (!errorShown) {
                toast.error('Could not award a pastel. Please try again.');
            }
        } finally {
            // Apply live updates after the response or rollback, without mixing weekly allowances.
            pendingDiamonds.forEach((left, reset) => {
                const key = `diamond-allowance:${userId}:${reset}`;
                const current = sharedValue(key);
                refreshSharedState(
                    new Map([
                        [
                            key,
                            Math.min(
                                typeof current === 'number' ? current : left,
                                left,
                            ),
                        ],
                    ]),
                );
            });
            awarding.delete(userId);
            busy.current = false;
        }
    };

    const content = (
        <>
            <span ref={emoji}>
                <PastelIcon glow={diamondCount > 0} />
            </span>
            <span className="min-w-[1ch] text-left tabular-nums">
                {formatCount(count)}
            </span>
        </>
    );
    const classes = cn(
        'inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[13px] text-muted-foreground',
        className,
    );

    return (
        <PastelCollection
            total={count}
            diamond={diamondCount}
            frosted={frostedCount}
            trigger={
                <button
                    type="button"
                    aria-label={`View ${label} and available gifts`}
                    className={cn(
                        classes,
                        'transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring',
                    )}
                    data-test={
                        readOnly
                            ? 'pastel-count-readonly'
                            : 'award-pastel-button'
                    }
                >
                    {content}
                </button>
            }
        >
            {!readOnly && (
                <div className="mt-2 grid grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)] items-center gap-1 border-t pt-2">
                    <span className="mr-auto text-xs text-muted-foreground">
                        Give
                    </span>
                    <button
                        type="button"
                        disabled={request.processing || remaining === 0}
                        onClick={() => award('original')}
                        aria-label={`Give an Original pastel, ${remaining} left today`}
                        title={`${remaining} Originals left today · resets at 00:00 UTC`}
                        className="inline-flex min-h-11 min-w-0 items-center justify-center gap-0.5 rounded-md border px-2 text-xs hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-50"
                        data-test="give-original-pastel"
                    >
                        <PastelIcon />
                        <span className="font-mono tabular-nums">
                            {remaining}
                        </span>
                        <span className="text-muted-foreground">today</span>
                    </button>
                    {diamondRemaining > 0 && (
                        <button
                            type="button"
                            disabled={request.processing}
                            onClick={() => award('diamond')}
                            aria-label="Give your weekly Diamond pastel"
                            title="1 Diamond left this week · resets Monday at 00:00 UTC"
                            className="inline-flex min-h-11 min-w-0 items-center justify-center gap-0.5 rounded-md border px-2 text-xs hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-50"
                            data-test="give-diamond-pastel"
                        >
                            <PastelIcon variant="diamond" />
                            <span className="font-mono tabular-nums">1</span>
                            <span className="text-muted-foreground">week</span>
                        </button>
                    )}
                </div>
            )}
        </PastelCollection>
    );
}
