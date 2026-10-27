import { router, usePage } from '@inertiajs/react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { home } from '@/routes';

function hasPreviousEntry(): boolean {
    // `history.length` counts forward entries too, so it cannot tell whether
    // `history.back()` will actually navigate. The Navigation API can.
    if (typeof window.navigation?.canGoBack === 'boolean') {
        return window.navigation.canGoBack;
    }

    return window.history.length > 1;
}

/**
 * Navigates back, falling back to the viewer's log (or the home page for
 * guests) when there is no previous entry to go back to.
 */
export function useGoBack(): () => void {
    const { auth } = usePage().props;

    return () => {
        if (hasPreviousEntry()) {
            window.history.back();

            return;
        }

        router.visit(auth.log === null ? home() : LogController.show(auth.log));
    };
}
