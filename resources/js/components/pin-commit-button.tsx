import { useLang } from '@erag/lang-sync-inertia/react';
import { router } from '@inertiajs/react';
import { Pin, PinOff } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import CommitPinController from '@/actions/App/Http/Controllers/CommitPinController';
import { toastWhenThrottled } from '@/lib/throttle';
import type { Commit } from '@/types';

export function PinCommitButton({
    commit,
    isPinned,
}: {
    commit: Commit;
    isPinned: boolean;
}) {
    const { __ } = useLang();
    const [isSaving, setIsSaving] = useState(false);
    const Icon = isPinned ? PinOff : Pin;
    const label = isPinned
        ? __('commits.pin.unpin_from_profile')
        : __('commits.pin.pin_to_profile');

    const togglePin = () => {
        router.visit(
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
                            ? __('commits.pin.unpinned')
                            : __('commits.pin.pinned'),
                    ),
                onError: () => toast.error(__('commits.pin.failed')),
                onHttpException: (response) => !toastWhenThrottled(response),
            },
        );
    };

    return (
        <button
            type="button"
            onClick={togglePin}
            disabled={isSaving}
            aria-label={label}
            aria-pressed={isPinned}
            aria-busy={isSaving}
            title={label}
            className="flex min-w-22 items-center gap-1.5 rounded-md px-2 py-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:cursor-wait disabled:opacity-50"
        >
            <Icon aria-hidden className="size-3.5" />
            {isSaving
                ? __('commits.pin.saving')
                : isPinned
                  ? __('commits.pin.unpin')
                  : __('commits.pin.pin')}
        </button>
    );
}
