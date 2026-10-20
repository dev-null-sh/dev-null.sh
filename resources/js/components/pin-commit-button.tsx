import { router } from '@inertiajs/react';
import { Pin, PinOff } from 'lucide-react';
import { useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { toast } from 'sonner';
import CommitPinController from '@/actions/App/Http/Controllers/CommitPinController';
import { ActionTooltip } from '@/components/action-tooltip';
import { Button } from '@/components/ui/button';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { toastWhenThrottled } from '@/lib/throttle';
import type { Commit } from '@/types';

/**
 * Pin and unpin controls for the profile owner's commits. Only one commit can be pinned, so pinning another while
 * one is pinned asks first, in a strip under that commit's actions.
 */
export function usePinCommit(pinnedCommit: Commit | null) {
    const [confirmingId, setConfirmingId] = useState<string | null>(null);
    const [savingId, setSavingId] = useState<string | null>(null);
    const opener = useRef<HTMLButtonElement | null>(null);
    const commitNotFound = useCommitNotFound();

    const save = (commit: Commit, isPinned: boolean) => {
        setConfirmingId(null);

        router.visit(
            isPinned
                ? CommitPinController.destroy(commit)
                : CommitPinController.update(commit),
            {
                only: ['commits', 'pinnedCommit'],
                reset: ['commits'],
                onStart: () => setSavingId(commit.id),
                onFinish: () =>
                    setSavingId((current) =>
                        current === commit.id ? null : current,
                    ),
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

    const cancel = () => {
        setConfirmingId(null);
        opener.current?.focus();
    };

    return {
        button: (commit: Commit) => {
            const isPinned = commit.id === pinnedCommit?.id;

            return (
                <PinCommitButton
                    isPinned={isPinned}
                    isSaving={savingId === commit.id}
                    onClick={(event) => {
                        if (isPinned || pinnedCommit === null) {
                            save(commit, isPinned);

                            return;
                        }

                        opener.current = event.currentTarget;
                        setConfirmingId(commit.id);
                    }}
                />
            );
        },
        confirmation: (commit: Commit) =>
            confirmingId === commit.id && pinnedCommit !== null ? (
                <ReplacePinConfirmation
                    onCancel={cancel}
                    onConfirm={() => save(commit, false)}
                />
            ) : null,
    };
}

function PinCommitButton({
    isPinned,
    isSaving,
    onClick,
}: {
    isPinned: boolean;
    isSaving: boolean;
    onClick: (event: MouseEvent<HTMLButtonElement>) => void;
}) {
    const Icon = isPinned ? PinOff : Pin;
    const label = isPinned ? 'Unpin from profile' : 'Pin to profile';

    return (
        <ActionTooltip label={label}>
            <button
                type="button"
                onClick={onClick}
                disabled={isSaving}
                aria-label={label}
                aria-pressed={isPinned}
                aria-busy={isSaving}
                className="flex items-center gap-1.5 rounded-md px-2 py-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:cursor-wait disabled:opacity-50 sm:min-w-24"
                data-test="pin-commit-button"
            >
                <Icon aria-hidden className="size-3.5" />
                <span className="sr-only sm:not-sr-only">
                    {isSaving ? 'Saving…' : isPinned ? 'Unpin' : 'Pin'}
                </span>
            </button>
        </ActionTooltip>
    );
}

function ReplacePinConfirmation({
    onCancel,
    onConfirm,
}: {
    onCancel: () => void;
    onConfirm: () => void;
}) {
    return (
        <div
            role="group"
            aria-label="Confirm replacing your pinned commit"
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    onCancel();
                }
            }}
            className="mt-3 flex min-h-12 flex-wrap items-center gap-x-3 gap-y-2 rounded-md border px-3 py-2 text-sm"
        >
            <span className="mr-auto">
                Replace your pinned commit? Only one commit can be pinned.
            </span>
            <div className="ml-auto flex gap-2">
                <Button variant="ghost" size="sm" autoFocus onClick={onCancel}>
                    Cancel
                </Button>
                <Button
                    size="sm"
                    onClick={onConfirm}
                    data-test="confirm-replace-pin-button"
                >
                    Replace
                </Button>
            </div>
        </div>
    );
}
