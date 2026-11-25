import { router, useHttp, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import UserLogController from '@/actions/App/Http/Controllers/UserLogController';
import { reloadInBackground } from '@/lib/background-poll';
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
        shown: Log | null;
        remember: (log: Log) => void;
        adopt: (log: Log) => boolean;
    } | null>(null);
    const reads = useRef(0);

    useEffect(
        () => () => {
            preference.current = null;
        },
        [],
    );

    // A changed log is news only while a request asks the server for it again: going back or forward brings a page
    // with the log it showed then, and cancels whatever was on its way.
    useEffect(() => {
        const asksForLog = (only: string[]) =>
            only.includes('auth.log') || only.includes('auth');
        const stopStarting = router.on('start', (event) => {
            if (asksForLog(event.detail.visit.only)) {
                reads.current += 1;
            }
        });
        const stopFinishing = router.on('finish', (event) => {
            if (asksForLog(event.detail.visit.only)) {
                reads.current = Math.max(0, reads.current - 1);
            }
        });

        return () => {
            stopStarting();
            stopFinishing();
        };
    }, []);

    useEffect(() => {
        if (preference.current?.userId !== userId) {
            preference.current = null;
        }

        if (userId === undefined || remembered === null) {
            return;
        }

        if (preference.current === null) {
            const { remember, adopt } = createLogPreference(
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
                () => {
                    if (preference.current?.userId === userId) {
                        void reloadInBackground({ only: ['auth.log'] });
                    }
                },
            );
            preference.current = {
                userId,
                log: remembered,
                shown: null,
                remember,
                adopt,
            };
        }

        // A log another page remembered shows here too, unless this page is still saving its own.
        if (
            reads.current > 0 &&
            remembered !== preference.current.log &&
            preference.current.adopt(remembered)
        ) {
            preference.current.log = remembered;
        }

        const shown =
            component === 'log/show' &&
            log !== undefined &&
            commits !== undefined
                ? log
                : null;

        // Only opening a log remembers it, so taking another page's log never sends this page's back.
        if (shown !== preference.current.shown) {
            preference.current.shown = shown;

            if (shown !== null && shown !== preference.current.log) {
                preference.current.log = shown;
                preference.current.remember(shown);
            }
        }

        const latest = preference.current.log;

        // Updating a once prop also updates it in Inertia's prefetched pages.
        if (remembered !== latest) {
            router.replaceProp('auth.log', latest);
        }
    }, [commits, component, log, put, remembered, transform, userId]);
}
