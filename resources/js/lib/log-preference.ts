import type { Log } from '@/types';

/** Keep writes in order even when a newer tab finishes loading during a save. */
export function createLogPreference(
    initial: Log,
    save: (log: Log) => Promise<void>,
    onError: () => void,
) {
    let saved = initial;
    let desired = initial;
    let saving = false;

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
    };

    return (log: Log) => {
        desired = log;
        void drain();
    };
}
