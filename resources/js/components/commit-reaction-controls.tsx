import { router, useHttp, usePage } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import { useContext, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitReactionController from '@/actions/App/Http/Controllers/CommitReactionController';
import { CommitReactionPicker } from '@/components/commit-reaction-picker';
import { CommitReactionTooltip } from '@/components/commit-reaction-tooltip';
import { CommitStarPollingContext } from '@/components/commit-star-polling';
import {
    commitReactionCache,
    useCommitReactions,
} from '@/hooks/use-commit-reactions';
import { useSharedState } from '@/hooks/use-shared-state';
import { signIn } from '@/lib/auth';
import type { ReactionDestinations } from '@/lib/commit-reaction-cache';
import { createCommitStarMutations } from '@/lib/commit-star-mutations';
import { commitStateKey } from '@/lib/commit-state';
import { emojiName } from '@/lib/emoji';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

type Reactions = NonNullable<Commit['reactions']>;

export function CommitReactionControls({ commit }: { commit: Commit }) {
    const { auth } = usePage().props;
    const isOwn = commit.user_id === auth.user?.id;
    const [json, setJson] = useSharedState(
        commitStateKey('reactions_json', commit.id),
        JSON.stringify(commit.reactions ?? []),
    );
    const reactions = JSON.parse(json) as Reactions;
    const personal = useCommitReactions(
        isOwn ? undefined : auth.user?.id,
        commit.id,
        {
            repositories: auth.reactionRepositories ?? [],
            reactions: reactions
                .filter((r) => r.reacted)
                .map((r) => ({
                    emoji: r.emoji,
                    public: true,
                    repositories: [],
                })),
        },
    );
    const request = useHttp<
        { flavor?: string; public: boolean; repositories: string[] },
        { reactions: Reactions }
    >({ public: true, repositories: [] });
    const polling = useContext(CommitStarPollingContext);
    const [localMutations] = useState(createCommitStarMutations);
    const mutations = polling?.mutations ?? localMutations;
    const element = useRef<HTMLSpanElement>(null);
    useEffect(() => {
        if (
            !polling ||
            !element.current ||
            !auth.user ||
            commit.status !== 'approved'
        )
            return;
        return polling.subscribe({
            id: commit.id,
            element: element.current,
            prepare: () =>
                mutations.prepare(commit.id, (state) =>
                    setJson(JSON.stringify(state.reactions ?? [])),
                ),
        });
    }, [polling, mutations, auth.user, commit.id, commit.status, setJson]);

    if (commit.status !== 'approved') return null;

    const react = async (
        add: boolean,
        emoji?: string,
        chosen?: ReactionDestinations,
    ) => {
        if (!auth.user) {
            signIn();
            return;
        }
        if (request.processing) return;
        const finish = mutations.begin(commit.id);
        if (!finish) return;
        const destinations = chosen ?? {
            public: true,
            repositories:
                personal.reactions.find((r) => r.emoji === emoji)
                    ?.repositories ?? [],
        };
        const change = { emoji, ...(add ? { destinations } : {}) };
        const finishCache = commitReactionCache.begin(
            auth.user.id,
            commit.id,
            personal,
            change,
        );
        const previous = json;
        const next = reactions.flatMap((r) => {
            if (emoji !== undefined && r.emoji !== emoji) return [r];
            const selected = add && destinations.public;
            const count = r.count + Number(selected) - Number(r.reacted);
            if (count === 0) return [];
            const users = (r.users ?? []).filter(
                (user) => user.id !== auth.user!.id,
            );
            if (selected)
                users.unshift({
                    id: auth.user!.id,
                    name: auth.user!.name,
                    username: auth.user!.username,
                });
            return [{ ...r, count, reacted: selected, users }];
        });
        if (
            add &&
            emoji &&
            destinations.public &&
            !reactions.some((r) => r.emoji === emoji)
        ) {
            next.push({
                emoji,
                count: 1,
                reacted: true,
                users: [
                    {
                        id: auth.user.id,
                        name: auth.user.name,
                        username: auth.user.username,
                    },
                ],
            });
        }
        setJson(JSON.stringify(next));
        request.transform(() => ({
            ...(emoji ? { flavor: emoji } : {}),
            ...destinations,
        }));
        try {
            const response = await request.submit(
                add
                    ? CommitReactionController.store(commit)
                    : CommitReactionController.destroy(commit),
            );
            finishCache(change);
            setJson(JSON.stringify(response.reactions));
            router.flushAll();
        } catch {
            finishCache();
            setJson(previous);
            toast.error(
                'Could not save your reaction. Your previous reactions have been restored.',
            );
        } finally {
            finish();
        }
    };

    return (
        <span
            ref={element}
            className="inline-flex max-w-full min-w-0 flex-1 flex-wrap items-center gap-1"
            data-test="commit-reactions"
        >
            {!isOwn && auth.user && (
                <CommitReactionPicker
                    commitId={commit.id}
                    userId={auth.user.id}
                    state={{
                        ...personal,
                        repositories:
                            auth.reactionRepositories ?? personal.repositories,
                    }}
                    disabled={request.processing}
                    onPick={(emoji, destinations) =>
                        void react(true, emoji, destinations)
                    }
                    onRemove={(emoji) => void react(false, emoji)}
                    onClear={() => void react(false)}
                />
            )}
            {reactions.map((reaction) => (
                <CommitReactionTooltip
                    key={reaction.emoji}
                    reaction={reaction}
                    viewerId={auth.user?.id}
                >
                    {isOwn ? (
                        <span
                            tabIndex={0}
                            className="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs"
                        >
                            {reaction.emoji} {reaction.count}
                        </span>
                    ) : (
                        <button
                            type="button"
                            disabled={request.processing}
                            aria-label={`${reaction.reacted ? 'Remove' : 'Add'} ${emojiName(reaction.emoji)} reaction, ${reaction.count}`}
                            aria-pressed={reaction.reacted}
                            className={cn(
                                'inline-flex shrink-0 items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs hover:bg-accent disabled:opacity-50',
                                reaction.reacted &&
                                    'border-primary/40 bg-primary/10 text-primary',
                            )}
                            onClick={() =>
                                void react(!reaction.reacted, reaction.emoji)
                            }
                        >
                            <span>{reaction.emoji}</span>
                            <span className="font-mono">{reaction.count}</span>
                        </button>
                    )}
                </CommitReactionTooltip>
            ))}
            {auth.user &&
                personal.reactions
                    .filter((r) => !r.public)
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
                                className="inline-flex shrink-0 items-center gap-1 rounded-md border border-primary/40 bg-primary/10 px-1.5 py-0.5 text-xs text-primary hover:bg-accent disabled:opacity-50"
                                onClick={() =>
                                    void react(false, reaction.emoji)
                                }
                            >
                                {reaction.emoji}
                                <LockKeyhole aria-hidden className="size-3" />
                            </button>
                        </CommitReactionTooltip>
                    ))}
        </span>
    );
}
