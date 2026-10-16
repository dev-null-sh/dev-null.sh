import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { CommitUndoToast } from '@/components/commit-undo-toast';
import { emojify } from '@/lib/emoji';
import { shortHash } from '@/lib/utils';

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
                    />,
                    {
                        id,
                        duration: Infinity,
                    },
                );
            }

            if (committed) {
                toast(
                    <span className="font-mono text-[13px]">
                        [log {shortHash(committed.id)}]{' '}
                        {emojify(committed.body)}
                        {committed.series_size !== undefined &&
                            ` (1/${committed.series_size})`}
                    </span>,
                );
            }

            if (data) {
                toast[data.type](data.message);
            }
        });
    }, []);
}
