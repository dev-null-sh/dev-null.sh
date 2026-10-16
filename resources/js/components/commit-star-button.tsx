import { router, useHttp, usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useState } from 'react';
import CommitStarController from '@/actions/App/Http/Controllers/CommitStarController';
import { useSharedState } from '@/hooks/use-shared-state';
import { signIn } from '@/lib/auth';
import { commitStateKey } from '@/lib/commit-state';
import { toastWhenThrottled } from '@/lib/throttle';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

function starLabel(starred: boolean, count: number): string {
    const verb = starred ? 'Unstar' : 'Star';

    if (count === 0) {
        return `${verb} commit`;
    }

    const noun = count === 1 ? 'star' : 'stars';

    return `${verb} commit, ${count} ${noun}`;
}

export function CommitStarButton({
    commit,
    className,
}: {
    commit: Commit;
    className?: string;
}) {
    const { auth } = usePage().props;
    const isOwn = commit.user_id === auth.user?.id;
    const [starred, setStarred] = useSharedState(
        commitStateKey('starred', commit.id),
        commit.starred,
    );
    const [count, setCount] = useSharedState(
        commitStateKey('stars_count', commit.id),
        commit.stars_count,
    );
    const request = useHttp<
        Record<string, never>,
        { starred: boolean; stars_count: number }
    >({});
    const [isAnimating, setIsAnimating] = useState(false);

    if (isOwn) {
        return <StarCount count={count} className={className} />;
    }

    if (commit.status !== 'approved') {
        return null;
    }

    const toggle = async () => {
        if (auth.user === null) {
            signIn();

            return;
        }

        if (request.processing) {
            return;
        }

        const next = !starred;

        setStarred(next);
        setCount((current) => Math.max(0, current + (next ? 1 : -1)));
        if (next) {
            setIsAnimating(true);
        }

        try {
            const response = await request.submit(
                next
                    ? CommitStarController.store(commit)
                    : CommitStarController.destroy(commit),
                {
                    onHttpException: (response) => {
                        toastWhenThrottled(response);
                    },
                },
            );

            setStarred(response.starred);
            setCount(response.stars_count);
            router.flushAll();
        } catch {
            setStarred(!next);
            setCount((current) => Math.max(0, current + (next ? -1 : 1)));
        }
    };

    return (
        <button
            type="button"
            onClick={toggle}
            aria-pressed={starred}
            aria-label={starLabel(starred, count)}
            title={starred ? 'Unstar' : 'Star'}
            className={cn(
                'group/star inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                starred && 'text-primary hover:text-primary',
                className,
            )}
            data-test="star-commit-button"
        >
            <Star
                onAnimationEnd={() => setIsAnimating(false)}
                className={cn(
                    'size-4 transition-transform group-hover/star:scale-110 active:scale-95',
                    starred && 'fill-current',
                    isAnimating && 'motion-safe:animate-star-pop',
                )}
            />
            <span data-test="star-count" className="min-w-[1ch] text-left">
                {count > 0 ? count : ''}
            </span>
        </button>
    );
}

/**
 * Authors can't star their own commits, so they only see how many stars others gave.
 */
function StarCount({
    count,
    className,
}: {
    count: number;
    className?: string;
}) {
    const label =
        count === 0
            ? 'No stars yet'
            : `${count} ${count === 1 ? 'star' : 'stars'}`;

    return (
        <span
            role="img"
            aria-label={label}
            title={label}
            className={cn(
                'inline-flex cursor-default items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[13px] text-muted-foreground',
                count === 0 && 'text-muted-foreground/50',
                className,
            )}
            data-test="star-count-readonly"
        >
            <Star aria-hidden className="size-4" />
            <span data-test="star-count" className="min-w-[1ch] text-left">
                {count > 0 ? count : ''}
            </span>
        </span>
    );
}
