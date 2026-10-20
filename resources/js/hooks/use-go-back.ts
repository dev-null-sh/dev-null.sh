import { router, usePage } from '@inertiajs/react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { home } from '@/routes';

export function useGoBack(): () => void {
    const { auth } = usePage().props;

    return () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(auth.log === null ? home() : LogController.show(auth.log));
    };
}
