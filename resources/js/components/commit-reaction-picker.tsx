import { Link } from '@inertiajs/react';
import { LockKeyhole, Settings2, SmilePlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    EmojiPicker,
    preloadEmojiGroups,
} from '@/components/chat/message-reactions';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { commitReactionCache } from '@/hooks/use-commit-reactions';
import type {
    ReactionDestinations,
    ReactionState,
} from '@/lib/commit-reaction-cache';
export type { ReactionDestinations } from '@/lib/commit-reaction-cache';

export function CommitReactionPicker({
    commitId,
    userId,
    state,
    disabled,
    onPick,
    onRemove,
    onClear,
}: {
    commitId: string;
    userId: string;
    state: ReactionState;
    disabled: boolean;
    onPick: (emoji: string, destinations: ReactionDestinations) => void;
    onRemove: (emoji: string) => void;
    onClear: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [destinationsChanged, setDestinationsChanged] = useState(false);
    const [destinations, setDestinations] = useState<ReactionDestinations>({
        public: true,
        repositories: [],
    });
    useEffect(() => {
        void preloadEmojiGroups();
    }, []);
    const hasDestination =
        destinations.public || destinations.repositories.length > 0;
    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    const recent = state.reactions[0];
                    setDestinations(
                        recent
                            ? {
                                  public: recent.public,
                                  repositories: recent.repositories,
                              }
                            : { public: true, repositories: [] },
                    );
                    void commitReactionCache.refresh(userId, commitId);
                    setDestinationsChanged(false);
                }
            }}
        >
            <PopoverTrigger asChild>
                <button
                    type="button"
                    aria-label="Choose emoji and visibility"
                    disabled={disabled}
                    className="inline-flex items-center rounded-md px-1.5 py-1 text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-50"
                    data-test="reaction-picker-trigger"
                >
                    <SmilePlus aria-hidden className="size-4" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                side="top"
                collisionPadding={12}
                className="flex h-[540px] max-h-[min(85dvh,var(--radix-popover-content-available-height))] w-[336px] max-w-[calc(100vw-24px)] flex-col overflow-hidden p-0"
                aria-label="Choose a reaction"
            >
                <div className="shrink-0 border-b p-3">
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Save to</h2>
                        <div className="flex items-center gap-1">
                            <Link
                                href="/settings/profile#repository"
                                aria-label="Manage repositories"
                                title="Manage repositories"
                                className="inline-flex size-6 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                            >
                                <Settings2 aria-hidden className="size-3.5" />
                            </Link>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-6 px-1 text-xs text-muted-foreground"
                                disabled={
                                    disabled || state.reactions.length === 0
                                }
                                onClick={() => {
                                    setOpen(false);
                                    onClear();
                                }}
                            >
                                Clear all
                            </Button>
                        </div>
                    </div>
                    <fieldset
                        aria-label="Save reaction to"
                        className="max-h-40 space-y-1 overflow-y-auto"
                    >
                        <label className="flex min-h-8 cursor-pointer items-center gap-2 rounded-md px-1 text-sm hover:bg-accent">
                            <Checkbox
                                checked={destinations.public}
                                onCheckedChange={(checked) => {
                                    setDestinationsChanged(true);
                                    setDestinations((current) => ({
                                        ...current,
                                        public: checked === true,
                                    }));
                                }}
                            />
                            <span>Public</span>
                            <span className="ml-auto text-xs text-muted-foreground">
                                dev-null
                            </span>
                        </label>
                        {state.repositories.map((repo) => (
                            <label
                                key={repo.id}
                                className="flex min-h-8 cursor-pointer items-center gap-2 rounded-md px-1 py-1 text-sm hover:bg-accent"
                            >
                                <Checkbox
                                    checked={destinations.repositories.includes(
                                        repo.id,
                                    )}
                                    onCheckedChange={(checked) => {
                                        setDestinationsChanged(true);
                                        setDestinations((current) => ({
                                            ...current,
                                            repositories:
                                                checked === true
                                                    ? [
                                                          ...current.repositories,
                                                          repo.id,
                                                      ]
                                                    : current.repositories.filter(
                                                          (id) =>
                                                              id !== repo.id,
                                                      ),
                                        }));
                                    }}
                                />
                                <span className="min-w-0 font-mono text-xs break-all">
                                    {repo.repository}
                                </span>
                                {repo.private && (
                                    <>
                                        <LockKeyhole
                                            aria-hidden
                                            className="ml-auto size-3.5 shrink-0 text-muted-foreground"
                                        />
                                        <span className="sr-only">
                                            Private repository
                                        </span>
                                    </>
                                )}
                            </label>
                        ))}
                    </fieldset>
                </div>
                <>
                    {hasDestination ? (
                        <EmojiPicker
                            showPreview={false}
                            usage={[]}
                            reacted={
                                new Set(
                                    state.reactions.map(
                                        (reaction) => reaction.emoji,
                                    ),
                                )
                            }
                            onPick={(emoji) => {
                                if (!disabled) {
                                    setOpen(false);
                                    if (
                                        !destinationsChanged &&
                                        state.reactions.some(
                                            (reaction) =>
                                                reaction.emoji === emoji,
                                        )
                                    )
                                        onRemove(emoji);
                                    else onPick(emoji, destinations);
                                }
                            }}
                        />
                    ) : (
                        <p className="flex-1 p-4 text-sm text-muted-foreground">
                            Select Public or a repository above to choose an
                            emoji.
                        </p>
                    )}
                </>
            </PopoverContent>
        </Popover>
    );
}
