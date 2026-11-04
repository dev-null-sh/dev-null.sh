import { router } from '@inertiajs/react';
import { currentPageComponent } from '@/lib/current-page';

/** Another moderator can approve or reject items while the queue page is cached. */
export function trackModerationQueueHistory(): VoidFunction {
    const refresh = (url: string): void => {
        router.visit(url, {
            only: ['commits', 'hasUnmoderatedCommits'],
            reset: ['commits'],
            preserveState: true,
            preserveScroll: false,
        });
    };

    const stop = router.on('navigate', ({ detail: { type, page } }) => {
        if (type === 'history' && page.component === 'moderation/show') {
            refresh(page.url);
        }
    });

    const onPageShow = (event: PageTransitionEvent): void => {
        if (event.persisted && currentPageComponent() === 'moderation/show') {
            refresh(window.location.href);
        }
    };

    window.addEventListener('pageshow', onPageShow);

    return () => {
        stop();
        window.removeEventListener('pageshow', onPageShow);
    };
}
