import type { InertiaLinkProps } from '@inertiajs/react';
import { router, usePage } from '@inertiajs/react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { home } from '@/routes';

export function useGoBack(
    fallback?: NonNullable<InertiaLinkProps['href']>,
): () => void {
    const { auth } = usePage().props;

    return () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(
            fallback ??
                (auth.log === null ? home() : LogController.show(auth.log)),
        );
    };
}
