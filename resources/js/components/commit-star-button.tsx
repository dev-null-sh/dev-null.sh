import { router, useHttp, usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useState } from 'react';
import CommitStarController from '@/actions/App/Http/Controllers/CommitStarController';
import { useSharedState } from '@/hooks/use-shared-state';
import { commitStateKey } from '@/lib/commit-state';
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
    const canStar =
        commit.status === 'approved' || commit.user_id === auth.user.id;
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

    if (!canStar) {
        return null;
    }

    const toggle = async () => {
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
