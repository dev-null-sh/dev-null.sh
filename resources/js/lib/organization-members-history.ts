import { router } from '@inertiajs/react';

/** Another admin may change memberships or roles after this page was cached. */
export function trackOrganizationMembersHistory(): VoidFunction {
    return router.on('navigate', ({ detail: { type, page } }) => {
        if (type !== 'history' || page.component !== 'organization-member/index') {
            return;
        }

        router.reload({
            only: ['organization', 'role', 'members'],
            preserveScroll: true,
        });
    });
}
