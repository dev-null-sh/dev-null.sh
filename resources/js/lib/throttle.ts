import { toast } from 'sonner';

/**
 * Rate limited actions toast and keep the current page, instead of swapping in the 429 page.
 */
export function toastWhenThrottled({ status }: { status: number }): boolean {
    if (status !== 429) {
        return false;
    }

    toast.error('Slow down: too many requests. Try again in a minute.', {
        id: 'too-many-requests',
    });

    return true;
}
