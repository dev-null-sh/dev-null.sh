import { router, useHttp, usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useCallback, useContext, useEffect, useRef, useState } from 'react';
import type { Ref } from 'react';
import { toast } from 'sonner';
import CommitStarController from '@/actions/App/Http/Controllers/CommitStarController';
import { ActionTooltip } from '@/components/action-tooltip';
import {
    CommitReactionPicker,
    type ReactionDestinations,
} from '@/components/commit-reaction-picker';
import { CommitStarPollingContext } from '@/components/commit-star-polling';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { refreshSharedState, useSharedState } from '@/hooks/use-shared-state';
import { signIn } from '@/lib/auth';
import { createCommitStarMutations } from '@/lib/commit-star-mutations';
import { commitStateKey } from '@/lib/commit-state';
import { formatCount } from '@/lib/numbers';
import type { StarFlavor } from '@/lib/star-flavors';
import { flavorEmoji } from '@/lib/star-flavors';
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
    const commitNotFound = useCommitNotFound();
    const isOwn = commit.user_id === auth.user?.id;
    const [starred, setStarred] = useSharedState(
        commitStateKey('starred', commit.id),
        commit.starred,
    );
    const [count, setCount] = useSharedState(
        commitStateKey('stars_count', commit.id),
        commit.stars_count,
    );
    const [flavor, setFlavor] = useSharedState(
        commitStateKey('star_flavor', commit.id),
        commit.star_flavor ?? null,
    );
    const [flavorsJson, setFlavorsJson] = useSharedState(
        commitStateKey('star_flavors_json', commit.id),
        JSON.stringify(commit.star_flavors ?? []),
    );
    const flavors = JSON.parse(flavorsJson) as string[];
    const request = useHttp<
        { flavor?: StarFlavor; public: boolean; repositories: string[] },
        {
            starred: boolean;
            stars_count: number;
            star_flavor: StarFlavor | null;
            star_flavors: StarFlavor[];
        }
    >({ public: true, repositories: [] });
    const [isAnimating, setIsAnimating] = useState(false);
    const [localMutations] = useState(createCommitStarMutations);
    const polling = useContext(CommitStarPollingContext);
    const mutations = polling?.mutations ?? localMutations;
    const canPoll =
        (isOwn || commit.status === 'approved') && auth.user !== null;
    const element = useRef<HTMLElement>(null);
    const setElement = useCallback((node: HTMLElement | null) => {
        element.current = node;
    }, []);

    useEffect(() => {
        if (!polling || !canPoll || !element.current) {
            return;
        }

        return polling.subscribe({
            id: commit.id,
            element: element.current,
            prepare: () =>
                mutations.prepare(commit.id, (stars) => {
                    refreshSharedState(
                        new Map<string, boolean | number | string | null>([
                            [
                                commitStateKey('starred', commit.id),
                                stars.starred,
                            ],
                            [
                                commitStateKey('stars_count', commit.id),
                                stars.stars_count,
                            ],
                            [
                                commitStateKey('star_flavor', commit.id),
                                stars.star_flavor ?? null,
                            ],
                            [
                                commitStateKey('star_flavors_json', commit.id),
                                JSON.stringify(stars.star_flavors ?? []),
                            ],
                        ]),
                    );
                }),
        });
    }, [polling, mutations, canPoll, commit.id, isOwn]);

    if (isOwn) {
        return (
            <StarCount
                ref={setElement}
                count={count}
                flavors={flavors}
                className={className}
            />
        );
    }

    if (commit.status !== 'approved') {
        return null;
    }

    const star = async (
        next: boolean,
        picked?: StarFlavor,
        destinations: ReactionDestinations = { public: true, repositories: [] },
    ) => {
        if (auth.user === null) {
            signIn();

            return;
        }

        if (request.processing) {
            return;
        }

        const finishMutation = mutations.begin(commit.id);

        if (!finishMutation) {
            return;
        }

        const previous = { starred, flavor };
        const publicNext = next && destinations.public;
        const change = publicNext === starred ? 0 : publicNext ? 1 : -1;

        setStarred(publicNext);
        setFlavor(publicNext ? (picked ?? null) : null);
        setCount((current) => Math.max(0, current + change));
        if (next) {
            setIsAnimating(true);
        }

        request.transform(() => ({
            ...(picked ? { flavor: picked } : {}),
            ...destinations,
        }));

        try {
            const response = await request.submit(
                next
                    ? CommitStarController.store(commit)
                    : CommitStarController.destroy(commit),
                {
                    onHttpException: (response) => {
                        if (!commitNotFound(commit.id, response)) {
                            toastWhenThrottled(response);
                        }
                    },
                },
            );

            setStarred(response.starred);
            setCount(response.stars_count);
            setFlavor(response.star_flavor);
            setFlavorsJson(JSON.stringify(response.star_flavors));
            if (next && destinations.repositories.length > 0)
                toast.success(
                    !destinations.public
                        ? 'Repository copies queued. Hidden on dev-null.'
                        : 'Public reaction saved. Repository copies queued.',
                );
            router.flushAll();
        } catch {
            toast.error('Could not save your reaction. Please try again.');
            setStarred(previous.starred);
            setFlavor(previous.flavor);
            setCount((current) => Math.max(0, current - change));
        } finally {
            finishMutation();
        }
    };

    return (
        <span className="inline-flex items-center">
            <button
                ref={setElement}
                aria-disabled={request.processing}
                type="button"
                onClick={() => void star(!starred)}
                aria-pressed={starred}
                aria-label={starLabel(starred, count)}
                className={cn(
                    'group/star inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[13px] text-muted-foreground transition-colors select-none [-webkit-touch-callout:none] hover:bg-accent hover:text-foreground',
                    starred && 'text-primary hover:text-primary',
                    className,
                )}
                data-test="star-commit-button"
            >
                {starred && flavor && flavor !== 'star' ? (
                    <span
                        onAnimationEnd={() => setIsAnimating(false)}
                        className={cn(
                            'text-sm leading-none',
                            isAnimating && 'motion-safe:animate-star-pop',
                        )}
                        data-test="star-flavor"
                    >
                        {flavorEmoji(flavor)}
                    </span>
                ) : (
                    <Star
                        onAnimationEnd={() => setIsAnimating(false)}
                        className={cn(
                            'size-4 transition-transform group-hover/star:scale-110 active:scale-95',
                            starred && 'fill-current',
                            isAnimating && 'motion-safe:animate-star-pop',
                        )}
                    />
                )}
                <TopFlavors
                    flavors={flavors.filter(
                        (given) => !(starred && given === flavor),
                    )}
                />
                <span data-test="star-count" className="min-w-[1ch] text-left">
                    {count > 0 ? formatCount(count) : ''}
                </span>
            </button>
            {auth.user !== null && (
                <CommitReactionPicker
                    commitId={commit.id}
                    flavor={flavor}
                    starred={starred}
                    disabled={request.processing}
                    onPick={(emoji, destination) =>
                        void star(true, emoji, destination)
                    }
                    onClear={() => void star(false)}
                />
            )}
        </span>
    );
}

/**
 * The two flavors given most, next to the count.
 */
function TopFlavors({ flavors }: { flavors: StarFlavor[] }) {
    if (flavors.length === 0) {
        return null;
    }

    return (
        <span
            aria-hidden
            className="text-xs leading-none"
            data-test="star-flavors"
        >
            {flavors.map(flavorEmoji).join('')}
        </span>
    );
}

/**
 * Authors can't star their own commits, so they only see how many stars others gave.
 */
function StarCount({
    ref,
    count,
    flavors,
    className,
}: {
    ref?: Ref<HTMLSpanElement>;
    count: number;
    flavors: StarFlavor[];
    className?: string;
}) {
    const label =
        count === 0
            ? 'No stars yet'
            : `${count} ${count === 1 ? 'star' : 'stars'}`;

    return (
        <ActionTooltip label={label}>
            <span
                ref={ref}
                role="img"
                aria-label={label}
                className={cn(
                    'inline-flex cursor-default items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[13px] text-muted-foreground',
                    count === 0 && 'text-muted-foreground/50',
                    className,
                )}
                data-test="star-count-readonly"
            >
                <Star aria-hidden className="size-4" />
                <TopFlavors flavors={flavors} />
                <span data-test="star-count" className="min-w-[1ch] text-left">
                    {count > 0 ? formatCount(count) : ''}
                </span>
            </span>
        </ActionTooltip>
    );
}
