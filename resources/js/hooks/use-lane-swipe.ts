import { router } from '@inertiajs/react';
import type { RefObject } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { useTabSwipe } from '@/hooks/use-tab-swipe';
import type { Log } from '@/types';

const lanes: Log[] = ['following', 'latest', 'trending'];

export function useLaneSwipe(
    feedRef: RefObject<HTMLElement | null>,
    log: Log,
): void {
    useTabSwipe(feedRef, (direction) => {
        const lane = lanes[lanes.indexOf(log) + direction];
        if (lane)
            router.visit(LogController.show(lane), { viewTransition: true });
    });
}
