import { router } from '@inertiajs/react';

/** The recipient count can change while the email composer is in history. */
export function trackEmailAudienceHistory(): VoidFunction {
    return router.on('navigate', ({ detail: { type, page } }) => {
        if (type !== 'history' || page.component !== 'operations/emails') {
            return;
        }

        router.reload({
            only: ['recipients'],
            preserveScroll: true,
        });
    });
}
