import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { emojify } from '@/lib/emoji';
import { shortHash } from '@/lib/utils';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const { toast: data, committed } = event.detail.flash;

            if (committed) {
                toast(
                    <span className="font-mono text-[13px]">
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
