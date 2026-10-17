import { useLang } from '@erag/lang-sync-inertia/react';
import { router } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitRestorationController from '@/actions/App/Http/Controllers/CommitRestorationController';
import { Button } from '@/components/ui/button';
import { commitMutationOptions } from '@/lib/commit-mutation-options';
import { currentPageComponent } from '@/lib/current-page';
import { shortHash } from '@/lib/utils';
import type { DeletedFlash } from '@/types';

export type CommitUndoToastLabels = {
    expired: string;
    restored: string;
    restoreFailed: string;
    deleted: string;
    timeToUndo: (time: string) => string;
    availability: string;
    restoring: string;
    undo: string;
};

const englishLabels: CommitUndoToastLabels = {
    expired: 'Undo window expired.',
    restored: 'Commit restored.',
    restoreFailed: 'Unable to restore this commit.',
    deleted: 'Commit deleted.',
    timeToUndo: (time) => `${time} to undo`,
    availability: 'Undo is available for 30 seconds after deletion.',
    restoring: 'Restoring…',
    undo: 'Undo',
};

/**
 * Toasts render outside the Inertia page context, so whoever shows the toast translates its labels with this hook.
 */
export function useCommitUndoToastLabels(): CommitUndoToastLabels {
    const { __ } = useLang();

    return {
        expired: __('commits.undo.expired'),
        restored: __('commits.undo.restored'),
        restoreFailed: __('commits.undo.restore_failed'),
        deleted: __('commits.undo.deleted'),
        timeToUndo: (time) => __('commits.undo.time_to_undo', { time }),
        availability: __('commits.undo.availability'),
        restoring: __('commits.undo.restoring'),
        undo: __('commits.undo.undo'),
    };
}

export function CommitUndoToast({
    deleted,
    toastId,
    labels = englishLabels,
}: {
    deleted: DeletedFlash;
    toastId: string | number;
    labels?: CommitUndoToastLabels;
}) {
    const [deadline] = useState(
        () =>
            Date.now() +
            Date.parse(deleted.expires_at) -
            Date.parse(deleted.server_time),
    );
    const [remaining, setRemaining] = useState(() =>
        Math.max(0, Math.ceil((deadline - Date.now()) / 1000)),
    );
    const [processing, setProcessing] = useState(false);
    const submitting = useRef(false);

    useEffect(() => {
        const update = () =>
            setRemaining(
                Math.max(0, Math.ceil((deadline - Date.now()) / 1000)),
            );
        const interval = window.setInterval(update, 250);
        return () => window.clearInterval(interval);
    }, [deadline]);

    useEffect(() => {
        if (remaining === 0 && !processing) {
            toast(labels.expired, { id: toastId, duration: 4000 });
        }
    }, [remaining, processing, toastId, labels.expired]);

    const undo = () => {
        if (submitting.current || Date.now() >= deadline) {
            return;
        }

        submitting.current = true;
        setProcessing(true);

        router.visit(CommitRestorationController.store(deleted.id), {
            ...commitMutationOptions(currentPageComponent()),
            onSuccess: (page) => {
                toast.dismiss(toastId);
                if (page.flash.restored) {
                    toast.success(labels.restored);
                }
            },
            onError: (errors) => {
                toast.dismiss(toastId);
                toast.error(errors.undo ?? labels.restoreFailed);
            },
            onFinish: () => {
                submitting.current = false;
                setProcessing(false);
            },
        });
    };

    return (
        <div className="flex w-full items-center gap-4" data-test="commit-undo">
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{labels.deleted}</p>
                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                    {shortHash(deleted.id)} ·{' '}
                    <span aria-hidden>
                        {labels.timeToUndo(`${remaining}s`)}
                    </span>
                    <span className="sr-only">{labels.availability}</span>
                </p>
            </div>
            <Button
                size="sm"
                variant="outline"
                onClick={undo}
                disabled={processing || remaining === 0}
                className="min-w-24"
            >
                <Undo2 className="size-3.5" />
                {processing ? labels.restoring : labels.undo}
            </Button>
        </div>
    );
}
