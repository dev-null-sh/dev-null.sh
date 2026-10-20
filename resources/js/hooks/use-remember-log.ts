import { router, useHttp, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import UserLogController from '@/actions/App/Http/Controllers/UserLogController';
import { createLogPreference } from '@/lib/log-preference';
import type { Commit, CursorPaginated, Log } from '@/types';

/** Remember displayed logs from the persistent layout, including cached visits. */
export function useRememberLog(): void {
    const {
        component,
        props: { auth, log, commits },
    } = usePage<{ log?: Log; commits?: CursorPaginated<Commit> }>();
    const userId = auth.user?.id;
    const remembered = auth.log;
    const { put, transform } = useHttp<{ log: Log }, null>({
        log: 'following',
    });
    const preference = useRef<{
        userId: string;
        log: Log;
        remember: (log: Log) => void;
    } | null>(null);

    useEffect(
        () => () => {
            preference.current = null;
        },
        [],
    );

    useEffect(() => {
        if (preference.current?.userId !== userId) {
            preference.current = null;
        }

        if (userId === undefined || remembered === null) {
            return;
        }

        if (preference.current === null) {
            preference.current = {
                userId,
                log: remembered,
                remember: createLogPreference(
                    remembered,
                    async (next) => {
                        if (preference.current?.userId !== userId) {
                            return;
                        }

                        transform(() => ({ log: next }));
                        await put(UserLogController.update.url(), {
                            onError: () => {
                                throw new Error('Could not remember the log.');
                            },
                        });
                    },
                    () => toast.error('Could not save your preferred log.'),
                ),
            };
        }

        if (
            component === 'log/show' &&
            log !== undefined &&
            commits !== undefined &&
            preference.current.log !== log
        ) {
            preference.current.log = log;
            preference.current.remember(log);
        }

        const latest = preference.current.log;

        // Updating a once prop also updates it in Inertia's prefetched pages.
        if (remembered !== latest) {
            router.replaceProp('auth.log', latest);
        }
    }, [commits, component, log, put, remembered, transform, userId]);
}
