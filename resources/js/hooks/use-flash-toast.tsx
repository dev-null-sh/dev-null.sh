import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { CommitUndoToastLabels } from '@/components/commit-undo-toast';
import {
    CommitUndoToast,
    useCommitUndoToastLabels,
} from '@/components/commit-undo-toast';
import { emojify } from '@/lib/emoji';
import { shortHash } from '@/lib/utils';

let undoToastLabels: CommitUndoToastLabels | undefined;

/**
 * Toasts render outside the Inertia app, where translations can't be read, so the app layout hands the undo toast its
 * labels in the current language.
 */
export function useUndoToastLabels(): void {
    const labels = useCommitUndoToastLabels();

    useEffect(() => {
        undoToastLabels = labels;
    });
}

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const { toast: data, committed, deleted } = event.detail.flash;

            if (deleted) {
                const id = `deleted-${deleted.id}`;
                toast(
                    <CommitUndoToast
                        key={deleted.expires_at}
                        deleted={deleted}
                        toastId={id}
                        labels={undoToastLabels}
                    />,
                    {
                        id,
                        duration: Infinity,
                    },
                );
            }

            if (committed) {
                toast(
                    <span
                        className="line-clamp-3 font-mono text-[13px] [overflow-wrap:anywhere]"
                        title={committed.body}
                    >
                        [log {shortHash(committed.id)}]{' '}
                        {emojify(committed.body)}
                    </span>,
                );
            }

            if (data) {
                toast[data.type](data.message);
            }
        });
    }, []);
}
