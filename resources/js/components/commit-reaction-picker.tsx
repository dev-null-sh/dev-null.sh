import { Link } from '@inertiajs/react';
import { LockKeyhole, SmilePlus } from 'lucide-react';
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
import { flavorEmoji } from '@/lib/star-flavors';

export type ReactionDestinations = { public: boolean; repositories: string[] };
type ReactionState = {
    public: boolean;
    repositories: {
        id: string;
        repository: string;
        private: boolean;
        selected: boolean;
        flavor: string | null;
    }[];
};

export function CommitReactionPicker({
    commitId,
    flavor,
    starred,
    disabled,
    onPick,
    onClear,
}: {
    commitId: string;
    flavor: string | null;
    starred: boolean;
    disabled: boolean;
    onPick: (emoji: string, destinations: ReactionDestinations) => void;
    onClear: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [state, setState] = useState<ReactionState | null>(null);
    const [error, setError] = useState(false);
    const [destinations, setDestinations] = useState<ReactionDestinations>({
        public: true,
        repositories: [],
    });
    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        void preloadEmojiGroups();
        void fetch(`/commits/${commitId}/my-reaction`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) throw new Error('Could not load reaction');
                const next = (await response.json()) as ReactionState;
                if (!controller.signal.aborted) {
                    setState(next);
                    const repositories = next.repositories
                        .filter((repo) => repo.selected)
                        .map((repo) => repo.id);
                    setDestinations({
                        public: next.public || repositories.length === 0,
                        repositories,
                    });
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) setError(true);
            });
        return () => controller.abort();
    }, [open, commitId]);
    const selected =
        flavor ?? state?.repositories.find((repo) => repo.selected)?.flavor;
    const hasDestination =
        destinations.public || destinations.repositories.length > 0;
    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    setState(null);
                    setError(false);
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
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 px-1 text-xs text-muted-foreground"
                            disabled={
                                disabled ||
                                !state ||
                                (!starred &&
                                    !state.repositories.some(
                                        (repo) => repo.selected,
                                    ))
                            }
                            onClick={() => {
                                setOpen(false);
                                onClear();
                            }}
                        >
                            Clear reaction
                        </Button>
                    </div>
                    {state && (
                        <fieldset
                            aria-label="Save reaction to"
                            className="max-h-40 space-y-1 overflow-y-auto"
                        >
                            <label className="flex min-h-8 cursor-pointer items-center gap-2 rounded-md px-1 text-sm hover:bg-accent">
                                <Checkbox
                                    checked={destinations.public}
                                    onCheckedChange={(checked) =>
                                        setDestinations((current) => ({
                                            ...current,
                                            public: checked === true,
                                        }))
                                    }
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
                                        onCheckedChange={(checked) =>
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
                                                                  id !==
                                                                  repo.id,
                                                          ),
                                            }))
                                        }
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
                    )}
                    {state && (
                        <p
                            className="mt-2 text-xs leading-relaxed text-muted-foreground"
                            aria-live="polite"
                        >
                            {!hasDestination
                                ? 'Choose at least one destination.'
                                : destinations.public
                                  ? 'Shown on dev-null. Pick an emoji to save.'
                                  : 'Hidden on dev-null. Pick an emoji to save.'}
                            {state.repositories.some(
                                (repo) =>
                                    !repo.private &&
                                    destinations.repositories.includes(repo.id),
                            ) &&
                                ' Selected public repos are visible to everyone.'}
                        </p>
                    )}
                </div>
                {!state ? (
                    <p
                        role="status"
                        className="p-4 text-sm text-muted-foreground"
                    >
                        {error
                            ? 'Could not load your settings. Close and reopen to retry.'
                            : 'Loading your reaction…'}
                    </p>
                ) : (
                    <>
                        {hasDestination ? (
                            <EmojiPicker
                                usage={[]}
                                reacted={
                                    new Set(
                                        selected ? [flavorEmoji(selected)] : [],
                                    )
                                }
                                onPick={(emoji) => {
                                    if (!disabled) {
                                        setOpen(false);
                                        onPick(emoji, destinations);
                                    }
                                }}
                            />
                        ) : (
                            <p className="flex-1 p-4 text-sm text-muted-foreground">
                                Select Public or a repository above to choose an
                                emoji.
                            </p>
                        )}
                        <Link
                            href="/settings/profile#repository"
                            className="shrink-0 border-t p-3 text-xs text-muted-foreground hover:text-foreground"
                        >
                            Manage repositories
                        </Link>
                    </>
                )}
            </PopoverContent>
        </Popover>
    );
}
