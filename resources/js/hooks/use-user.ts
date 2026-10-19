import { usePage } from '@inertiajs/react';
import type { Log, Profile } from '@/types';

/**
 * The signed-in user and their log, on the pages and components only signed-in users ever reach.
 */
export function useUser(): { user: Profile; log: Log } {
    const { auth } = usePage().props;

    if (auth.user === null || auth.log === null) {
        throw new Error('Only signed-in users reach this.');
    }

    return { user: auth.user, log: auth.log };
}
