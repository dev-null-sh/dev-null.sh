import { router } from '@inertiajs/react';
import { currentPageComponent } from '@/lib/current-page';

/** Another admin may change memberships or roles after this page was cached. */
export function trackOrganizationMembersHistory(): VoidFunction {
    const refresh = (): void => {
        router.reload({
            only: ['organization', 'role', 'members'],
            preserveScroll: true,
        });
    };

    const stop = router.on('navigate', ({ detail: { type, page } }) => {
        if (
            type === 'history' &&
            page.component === 'organization-member/index'
        ) {
            refresh();
        }
    });

    const onPageShow = (event: PageTransitionEvent): void => {
        if (
            event.persisted &&
            currentPageComponent() === 'organization-member/index'
        ) {
            refresh();
        }
    };

    window.addEventListener('pageshow', onPageShow);

    return () => {
        stop();
        window.removeEventListener('pageshow', onPageShow);
    };
}
