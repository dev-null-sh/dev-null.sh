import { router } from '@inertiajs/react';
import { currentPageComponent } from '@/lib/current-page';

const authUserId = (page: { props: Record<string, unknown> }) => {
    const auth = page.props.auth as
        | { user?: { id: string | number } | null }
        | undefined;

    return auth?.user?.id ?? null;
};

/** Another admin may change memberships or roles after this page was cached. */
export function trackOrganizationMembersHistory(): VoidFunction {
    let currentUserId: string | number | null = null;

    const refresh = (): void => {
        const previousUserId = currentUserId;

        router.reload({
            only: ['organization', 'role', 'members', 'auth'],
            preserveScroll: true,
            onSuccess: (page) => {
                if (authUserId(page) !== previousUserId) {
                    window.location.reload();
                }
            },
        });
    };

    const stop = router.on('navigate', ({ detail: { type, page } }) => {
        currentUserId = authUserId(page);

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
