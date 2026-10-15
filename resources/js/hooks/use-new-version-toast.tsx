import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export function useNewVersionToast(): void {
    useEffect(() => {
        return router.on('location', (event) => {
            if (!event.detail.versionChange) {
                return;
            }

            toast('/dev/null was updated.', {
                id: 'new-version',
                description: 'Reload to keep up. Unsent drafts are kept.',
                duration: Infinity,
                action: {
                    label: 'Reload',
                    onClick: () => window.location.reload(),
                },
            });
        });
    }, []);
}
