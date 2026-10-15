import { Link, router, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { GraphRail } from '@/components/graph-rail';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import { useNow } from '@/hooks/use-now';
import {
    cn,
    formatRelativeTime,
    isWithinSeconds,
    laneFor,
    shortHash,
} from '@/lib/utils';
import { show } from '@/routes/user';
import type { Commit } from '@/types';

export function CommitCard({
    commit,
    previousLane,
    isDimmed = false,
    isPreviousDimmed = false,
    isDayHighlighted = false,
    onAuthorHover,
    actions,
}: {
    commit: Commit;
    previousLane?: string;
    isDimmed?: boolean;
    isPreviousDimmed?: boolean;
    isDayHighlighted?: boolean;
    onAuthorHover?: (isHovering: boolean) => void;
    actions?: ReactNode;
}) {
    const { auth } = usePage().props;
    const now = useNow();
    const [deletion, setDeletion] = useState<
        'idle' | 'confirming' | 'deleting'
    >('idle');

    const isOwn = commit.user_id === auth.user.id;
    const hash = shortHash(commit.id);

    const deleteCommit = () => {
        setDeletion('deleting');

        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        window.setTimeout(
            () =>
                router.visit(CommitController.destroy(commit), {
                    preserveScroll: true,
                    onError: () => setDeletion('idle'),
                }),
            prefersReducedMotion ? 0 : 700,
        );
    };

    return (
        <article
            className={cn(
                'group grid scroll-mt-28 transition-[grid-template-rows,opacity,background-color] duration-300 ease-out outline-none focus-visible:bg-accent',
                { 'bg-primary-soft/60': isDayHighlighted },
                deletion === 'deleting'
                    ? 'grid-rows-[0fr] opacity-0 delay-400'
                    : 'grid-rows-[1fr]',
            )}
            tabIndex={-1}
            data-commit
            data-test="commit"
        >
            <div className="flex min-h-0 overflow-hidden">
                <GraphRail
                    lane={laneFor(commit.user_id)}
                    previousLane={previousLane}
                    isFresh={isOwn && isWithinSeconds(commit.created_at, 15)}
                    isDimmed={isDimmed}
                    isPreviousDimmed={isPreviousDimmed}
                    onHoverChange={onAuthorHover}
                />

                <div className="flex min-w-0 flex-1 gap-3 border-b py-3 pr-4">
                    <UserPreview
                        user={commit.user}
                        className="shrink-0"
                        onHoverChange={onAuthorHover}
                    >
                        <Link
                            href={show(commit.user)}
                            className="shrink-0 rounded-full"
                        >
                            <UserAvatar
                                user={commit.user}
                                className="size-10"
                            />
                        </Link>
                    </UserPreview>

                    <div className="min-w-0 flex-1">
                        <div className="flex items-baseline gap-1.5 text-[15px]">
                            <UserPreview
                                user={commit.user}
                                className="flex min-w-0 items-baseline gap-1.5"
                                onHoverChange={onAuthorHover}
                            >
                                <Link
                                    href={show(commit.user)}
                                    className="truncate font-semibold hover:underline"
                                >
                                    {commit.user.name}
                                </Link>
                                <Link
                                    href={show(commit.user)}
                                    tabIndex={-1}
                                    className="truncate font-mono text-[13px] text-muted-foreground"
                                >
                                    @{commit.user.username}
                                </Link>
                            </UserPreview>
                            <span aria-hidden className="text-muted-foreground">
                                ·
                            </span>
                            <time
                                dateTime={commit.created_at}
                                title={new Date(
                                    commit.created_at,
                                ).toLocaleString()}
                                className="shrink-0 font-mono text-[13px] text-muted-foreground"
                            >
                                {formatRelativeTime(commit.created_at, now)}
                            </time>

                            {commit.status !== 'approved' && (
                                <span
                                    className={cn(
                                        'shrink-0 self-center rounded-full px-2 py-0.5 font-mono text-xs',
                                        commit.status === 'pending'
                                            ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                            : 'bg-destructive/10 text-destructive',
                                    )}
                                    title={
                                        commit.status === 'pending'
                                            ? 'Only you can see this commit until it is approved.'
                                            : 'This commit was rejected. Only you can see it.'
                                    }
                                    data-test="commit-status"
                                >
                                    {commit.status}
                                </span>
                            )}

                            <span className="relative ml-auto flex shrink-0 items-center self-center">
                                <span
                                    className={cn(
                                        'hidden font-mono text-[13px] text-muted-foreground transition-opacity sm:inline',
                                        {
                                            'group-focus-within:opacity-0 group-hover:opacity-0 [@media(hover:none)]:opacity-0':
                                                isOwn,
                                        },
                                    )}
                                    title={`Commit ${commit.id}`}
                                >
                                    {hash}
                                </span>

                                {isOwn && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setDeletion('confirming')
                                        }
                                        aria-label="Delete commit"
                                        title="Delete commit"
                                        className="-my-1 rounded-md p-1.5 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:opacity-100 sm:absolute sm:top-1/2 sm:-right-1.5 sm:my-0 sm:-translate-y-1/2 sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100 [@media(hover:none)]:opacity-100"
                                        data-test="delete-commit-button"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                )}
                            </span>
                        </div>

                        {deletion === 'deleting' ? (
                            <p
                                className="mt-0.5 font-mono text-sm text-muted-foreground"
                                role="status"
                            >
                                rm {hash} → /dev/null
                            </p>
                        ) : (
                            <p className="mt-0.5 text-[15px] leading-relaxed break-words whitespace-pre-wrap">
                                {commit.body}
                            </p>
                        )}

                        {actions}

                        {deletion === 'confirming' && (
                            <div
                                role="group"
                                aria-label="Confirm delete"
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        setDeletion('idle');
                                    }
                                }}
                                className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md bg-destructive/8 px-3 py-2 text-sm"
                            >
                                <span className="mr-auto">
                                    Delete this commit? There's no undo.
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    autoFocus
                                    onClick={() => setDeletion('idle')}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={deleteCommit}
                                    data-test="confirm-delete-commit-button"
                                >
                                    Delete
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </article>
    );
}
