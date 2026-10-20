import type { Log } from '@/types';

/** Keep writes in order even when a newer tab finishes loading during a save. */
export function createLogPreference(
    initial: Log,
    save: (log: Log) => Promise<void>,
    onError: () => void,
    refresh: () => void,
) {
    let saved = initial;
    let desired = initial;
    let saving = false;
    let missed = false;

    const drain = async () => {
        if (saving) {
            return;
        }

        saving = true;

        while (desired !== saved) {
            const next = desired;

            try {
                await save(next);
                saved = next;
            } catch {
                if (desired === next) {
                    onError();
                    break;
                }
            }
        }

        saving = false;

        if (missed) {
            missed = false;
            refresh();
        }
    };

    return {
        remember: (log: Log) => {
            desired = log;
            void drain();
        },
        // A log read while this page's own save is on its way may be older or newer than it, which only the server
        // knows once the save is done, so it is read again then.
        adopt: (log: Log): boolean => {
            if (saving) {
                missed = true;

                return false;
            }

            saved = log;
            desired = log;

            return true;
        },
    };
}
