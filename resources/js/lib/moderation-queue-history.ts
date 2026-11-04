import { router } from '@inertiajs/react';

/** Another moderator can approve or reject items while the queue page is cached. */
export function trackModerationQueueHistory(): VoidFunction {
    return router.on('navigate', ({ detail: { type, page } }) => {
        if (type !== 'history' || page.component !== 'moderation/show') {
            return;
        }

        router.reload({
            only: ['commits', 'hasUnmoderatedCommits'],
            reset: ['commits'],
            preserveScroll: false,
        });
    });
}
