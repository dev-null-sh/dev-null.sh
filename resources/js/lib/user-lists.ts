import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { toastWhenThrottled } from '@/lib/throttle';

/**
 * List actions answer with a toast either way: a refused one carries its reason as a validation error.
 */
export function visitWithErrorToast(
    action: Parameters<typeof router.visit>[0],
    options: Parameters<typeof router.visit>[1] = {},
) {
    router.visit(action, {
        preserveScroll: true,
        onHttpException: (response) => !toastWhenThrottled(response),
        onError: (errors) => {
            const message = Object.values(errors)[0];

            if (message) {
                toast.error(message);
            }
        },
        ...options,
    });
}
