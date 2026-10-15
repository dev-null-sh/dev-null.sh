import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import EventAttendanceController from '@/actions/App/Http/Controllers/EventAttendanceController';
import { signIn } from '@/lib/auth';
import type { Commit } from '@/types';

export type EventGoing = {
    isGoing: boolean;
    count: number;
    toggle: () => void;
};

/**
 * One source of truth for a commit's attendance, shared by every Going button on its card. It flips at once and
 * rolls back when the request fails; fresh props from the server replace it. Guests are sent to sign in first.
 */
export function useEventGoing(commit: Commit): EventGoing {
    const { auth } = usePage().props;
    const server = {
        isGoing: commit.event?.is_going ?? false,
        count: commit.event?.attendees_count ?? 0,
    };
    const [state, setState] = useState({
        ...server,
        pending: false,
        from: server,
    });

    if (
        state.from.isGoing !== server.isGoing ||
        state.from.count !== server.count
    ) {
        setState({ ...server, pending: false, from: server });
    }

    const toggle = () => {
        if (auth.user === null) {
            signIn();

            return;
        }

        if (state.pending) {
            return;
        }

        const next = !state.isGoing;
        const flip = (going: boolean) =>
            setState((current) => ({
                ...current,
                isGoing: going,
                count: Math.max(0, current.count + (going ? 1 : -1)),
            }));
        const revert = () => flip(!next);

        flip(next);
        setState((current) => ({ ...current, pending: true }));

        router.visit(
            next
                ? EventAttendanceController.store(commit)
                : EventAttendanceController.destroy(commit),
            {
                preserveScroll: true,
                preserveState: true,
                except: ['commits'],
                showProgress: false,
                onError: revert,
                onHttpException: () => {
                    revert();

                    return false;
                },
                onNetworkError: () => {
                    revert();

                    return false;
                },
                onFinish: () =>
                    setState((current) => ({ ...current, pending: false })),
            },
        );
    };

    return { isGoing: state.isGoing, count: state.count, toggle };
}
