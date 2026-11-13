import { router } from '@inertiajs/react';
import { Pin, PinOff } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import CommitPinController from '@/actions/App/Http/Controllers/CommitPinController';
import { ActionTooltip } from '@/components/action-tooltip';
import { Spinner } from '@/components/ui/spinner';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { restoreFeedCommit } from '@/lib/commit-mutation-options';
import { toastWhenThrottled } from '@/lib/throttle';
import type { Commit, CursorPaginated } from '@/types';

export function PinCommitButton({
    commit,
    isPinned,
}: {
    commit: Commit;
    isPinned: boolean;
}) {
    const [isSaving, setIsSaving] = useState(false);
    const commitNotFound = useCommitNotFound();
    const Icon = isPinned ? PinOff : Pin;
    const label = isPinned ? 'Unpin from profile' : 'Pin to profile';

    const togglePin = () => {
        router
            .optimistic(
                (props: {
                    commits: CursorPaginated<Commit>;
                    pinnedCommit: Commit | null;
                }) => {
                    const rows = props.commits.data.filter(
                        (row) => row.id !== commit.id,
                    );
                    const unpinned = isPinned ? commit : props.pinnedCommit;
                    const restored = unpinned
                        ? restoreFeedCommit(rows, unpinned, 'latest')
                        : rows;
                    const belongsToLaterPage =
                        props.commits.next_cursor !== null &&
                        restored.at(-1)?.id === unpinned?.id;

                    return {
                        pinnedCommit: isPinned ? null : commit,
                        commits: {
                            ...props.commits,
                            data: belongsToLaterPage ? rows : restored,
                        },
                    };
                },
            )
            .visit(
                isPinned
                    ? CommitPinController.destroy(commit)
                    : CommitPinController.update(commit),
                {
                    only: ['commits', 'pinnedCommit'],
                    reset: ['commits'],
                    onStart: () => setIsSaving(true),
                    onFinish: () => setIsSaving(false),
                    onSuccess: () =>
                        toast.success(
                            isPinned
                                ? 'Commit unpinned.'
                                : 'Commit pinned to your profile.',
                        ),
                    onError: () =>
                        toast.error('Could not update your pinned commit.'),
                    onHttpException: (response) =>
                        !commitNotFound(commit.id, response) &&
                        !toastWhenThrottled(response),
                },
            );
    };

    return (
        <ActionTooltip label={label}>
            <button
                type="button"
                onClick={togglePin}
                disabled={isSaving}
                aria-label={label}
                aria-pressed={isPinned}
                aria-busy={isSaving}
                className="flex items-center gap-1.5 rounded-md px-2 py-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:cursor-wait disabled:opacity-50"
            >
                {isSaving ? (
                    <Spinner aria-hidden className="size-3.5" />
                ) : (
                    <Icon aria-hidden className="size-3.5" />
                )}
                <span className="sr-only sm:not-sr-only">
                    {isPinned ? 'Unpin' : 'Pin'}
                </span>
            </button>
        </ActionTooltip>
    );
}
