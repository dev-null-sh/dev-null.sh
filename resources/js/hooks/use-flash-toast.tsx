import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { CommitUndoToast } from '@/components/commit-undo-toast';
import { CommittedToast } from '@/components/committed-toast';
import { showTimeout } from '@/components/timeout-dialog';
import { dropConfetti } from '@/lib/confetti';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const {
                toast: data,
                committed,
                deleted,
                achievementsUnlocked,
                timeout,
            } = event.detail.flash;

            if (timeout) {
                showTimeout(timeout);
            }

            if (achievementsUnlocked?.length) {
                dropConfetti();

                achievementsUnlocked.forEach((name) =>
                    toast('achievement unlocked 🎉', { description: name }),
                );
            }

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
                const id = `committed-${committed.id}`;
                toast(<CommittedToast committed={committed} toastId={id} />, {
                    id,
                });
            }

            if (data) {
                toast[data.type](data.message);
            }
        });
    }, []);
}
