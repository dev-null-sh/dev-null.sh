import { router, useHttp, usePage } from '@inertiajs/react';
import { LockKeyhole, Star } from 'lucide-react';
import { useCallback, useContext, useEffect, useRef, useState } from 'react';
import type { Ref } from 'react';
import { toast } from 'sonner';
import CommitStarController from '@/actions/App/Http/Controllers/CommitStarController';
import { ActionTooltip } from '@/components/action-tooltip';
import {
    CommitReactionPicker,
    type ReactionDestinations,
} from '@/components/commit-reaction-picker';
import { CommitReactionTooltip } from '@/components/commit-reaction-tooltip';
import { CommitStarPollingContext } from '@/components/commit-star-polling';
import {
    commitReactionCache,
    useCommitReactions,
} from '@/hooks/use-commit-reactions';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { refreshSharedState, useSharedState } from '@/hooks/use-shared-state';
import { signIn } from '@/lib/auth';
import { createCommitStarMutations } from '@/lib/commit-star-mutations';
import { commitStateKey } from '@/lib/commit-state';
import { emojiName } from '@/lib/emoji';
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
    const [, setFlavor] = useSharedState(
        commitStateKey('star_flavor', commit.id),
        commit.star_flavor ?? null,
    );
    const [flavorsJson, setFlavorsJson] = useSharedState(
        commitStateKey('star_flavors_json', commit.id),
        JSON.stringify(commit.star_flavors ?? []),
    );
    const flavors = JSON.parse(flavorsJson) as string[];
    const [reactionsJson, setReactionsJson] = useSharedState(
        commitStateKey('reactions_json', commit.id),
        JSON.stringify(commit.reactions ?? []),
    );
    const reactions = JSON.parse(reactionsJson) as NonNullable<
        Commit['reactions']
    >;
    const personal = useCommitReactions(
        isOwn ? undefined : auth.user?.id,
        commit.id,
        {
            repositories: auth.reactionRepositories ?? [],
            reactions: reactions
                .filter((reaction) => reaction.reacted)
                .map((reaction) => ({
                    emoji: reaction.emoji,
                    public: true,
                    repositories: [],
                })),
        },
    );
    const hasStar =
        reactions.some(
            (reaction) => reaction.emoji === '⭐' && reaction.reacted,
        ) ||
        (reactions.length === 0 && starred);
    const starCount =
        reactions.find((reaction) => reaction.emoji === '⭐')?.count ?? count;
    const request = useHttp<
        { flavor?: StarFlavor; public: boolean; repositories: string[] },
        {
            starred: boolean;
            stars_count: number;
            star_flavor: StarFlavor | null;
            star_flavors: StarFlavor[];
            reactions: NonNullable<Commit['reactions']>;
        }
    >({ public: true, repositories: [] });
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
                                commitStateKey('reactions_json', commit.id),
                                JSON.stringify(stars.reactions ?? []),
                            ],
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
            <span
                ref={setElement}
                className="inline-flex max-w-full flex-wrap items-center gap-1"
            >
                {reactions.length === 0 && (
                    <StarCount
                        count={count}
                        flavors={flavors}
                        className={className}
                    />
                )}
                {reactions.map((reaction) => (
                    <CommitReactionTooltip
                        key={reaction.emoji}
                        reaction={reaction}
                        viewerId={auth.user?.id}
                    >
                        <span
                            tabIndex={0}
                            className="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs"
                            aria-label={`${emojiName(reaction.emoji)}, ${reaction.count} reactions`}
                        >
                            {reaction.emoji} {reaction.count}
                        </span>
                    </CommitReactionTooltip>
                ))}
            </span>
        );
    }

    if (commit.status !== 'approved') {
        return null;
    }

    const star = async (
        next: boolean,
        picked?: StarFlavor,
        chosenDestinations?: ReactionDestinations,
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

        const destinations = chosenDestinations ?? {
            public: true,
            repositories:
                personal.reactions.find((reaction) => reaction.emoji === picked)
                    ?.repositories ?? [],
        };
        const finishCache = commitReactionCache.begin(
            auth.user.id,
            commit.id,
            personal,
        );
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

            finishCache({ emoji: picked, ...(next ? { destinations } : {}) });
            setReactionsJson(JSON.stringify(response.reactions));
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
            finishCache();
            toast.error('Could not save your reaction. Please try again.');
        } finally {
            finishMutation();
        }
    };

    return (
        <span
            ref={setElement}
            className="inline-flex max-w-full flex-wrap items-center gap-1"
        >
            {(reactions.length === 0 ||
                reactions.some((reaction) => reaction.emoji === '⭐')) && (
                <CommitReactionTooltip
                    reaction={
                        reactions.find(
                            (reaction) => reaction.emoji === '⭐',
                        ) ?? { emoji: '⭐', count: 0, reacted: false }
                    }
                    viewerId={auth.user?.id}
                >
                    <button
                        aria-disabled={request.processing}
                        type="button"
                        onClick={() => void star(!hasStar, '⭐')}
                        aria-pressed={hasStar}
                        aria-label={starLabel(hasStar, starCount)}
                        className={cn(
                            'group/star inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-mono text-[13px] text-muted-foreground transition-colors select-none [-webkit-touch-callout:none] hover:bg-accent hover:text-foreground',
                            hasStar && 'text-primary hover:text-primary',
                            className,
                        )}
                        data-test="star-commit-button"
                    >
                        <Star
                            className={cn('size-4', hasStar && 'fill-current')}
                        />
                        <span
                            data-test="star-count"
                            className="min-w-[1ch] text-left"
                        >
                            {count > 0
                                ? formatCount(
                                      reactions.find(
                                          (reaction) => reaction.emoji === '⭐',
                                      )?.count ?? count,
                                  )
                                : ''}
                        </span>
                    </button>
                </CommitReactionTooltip>
            )}
            {reactions
                .filter((reaction) => reaction.emoji !== '⭐')
                .map((reaction) => (
                    <CommitReactionTooltip
                        key={reaction.emoji}
                        reaction={reaction}
                        viewerId={auth.user?.id}
                    >
                        <button
                            type="button"
                            disabled={request.processing}
                            aria-label={`${reaction.reacted ? 'Remove' : 'Add'} ${emojiName(reaction.emoji)} reaction, ${reaction.count}`}
                            aria-pressed={reaction.reacted}
                            className={cn(
                                'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs hover:bg-accent disabled:opacity-50',
                                reaction.reacted &&
                                    'border-primary/40 bg-primary/10 text-primary',
                            )}
                            onClick={() =>
                                void star(!reaction.reacted, reaction.emoji)
                            }
                        >
                            <span>{reaction.emoji}</span>
                            <span className="font-mono">{reaction.count}</span>
                        </button>
                    </CommitReactionTooltip>
                ))}
            {auth.user !== null &&
                personal.reactions
                    .filter((reaction) => !reaction.public)
                    .map((reaction) => (
                        <CommitReactionTooltip
                            key={`private-${reaction.emoji}`}
                            reaction={{
                                emoji: reaction.emoji,
                                count: 1,
                                reacted: true,
                            }}
                            privateOnly
                        >
                            <button
                                type="button"
                                disabled={request.processing}
                                aria-label={`Remove private ${emojiName(reaction.emoji)} reaction`}
                                aria-pressed={true}
                                className="inline-flex items-center gap-1 rounded-md border border-primary/40 bg-primary/10 px-1.5 py-0.5 text-xs text-primary hover:bg-accent disabled:opacity-50"
                                onClick={() => void star(false, reaction.emoji)}
                            >
                                {reaction.emoji}
                                <LockKeyhole aria-hidden className="size-3" />
                            </button>
                        </CommitReactionTooltip>
                    ))}
            {auth.user !== null && (
                <CommitReactionPicker
                    commitId={commit.id}
                    userId={auth.user.id}
                    state={{
                        ...personal,
                        repositories:
                            auth.reactionRepositories ?? personal.repositories,
                    }}
                    disabled={request.processing}
                    onPick={(emoji, destination) =>
                        void star(true, emoji, destination)
                    }
                    onRemove={(emoji) => void star(false, emoji)}
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
