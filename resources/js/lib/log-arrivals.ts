/**
 * Arrival reads wait longer while batches stay busy. Empty cooldowns return to the fast path without a request.
 * A failed read keeps its work, and a page that is hidden keeps everything until it resumes.
 */
export function batchLogArrivals(
    refresh: (ids: string[]) => Promise<number | null>,
): {
    request: (ids: string[]) => void;
    read: () => (ids?: string[]) => string[];
    pause: () => void;
    resume: () => void;
    cancel: () => void;
} {
    const delays = [1_000, 5_000, 10_000, 20_000, 30_000];
    const pending = new Map<string, number>();
    let revision = 0;
    let step = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let running = false;
    let paused = false;
    let cancelled = false;

    const schedule = () => {
        if (paused || cancelled || running || timer !== undefined) return;

        timer = setTimeout(() => {
            timer = undefined;
            if (pending.size === 0) {
                step = 0;
                return;
            }

            const batch = new Map(pending);
            running = true;
            void refresh([...batch.keys()])
                .catch(() => null)
                .then((added) => {
                    if (added !== null) {
                        batch.forEach((version, id) => {
                            if (pending.get(id) === version) pending.delete(id);
                        });
                        if (added > 0) {
                            if (step === 0 || added > 1)
                                step = Math.min(step + 1, delays.length - 1);
                        } else {
                            step = 0;
                        }
                    }
                })
                .finally(() => {
                    running = false;
                    if (pending.size > 0 || step > 0) schedule();
                });
        }, delays[step]);
    };

    return {
        request: (ids) => {
            if (cancelled || ids.length === 0) return;
            ids.forEach((id) => pending.set(id, ++revision));
            schedule();
        },
        // Only signals heard before this read started are covered by its response. A later signal may be a deletion:
        // return it for a prompt check instead of losing it or waiting behind the arrival cooldown.
        read: () => {
            const before = revision;
            return (ids) => {
                const newer: string[] = [];
                (ids ?? [...pending.keys()]).forEach((id) => {
                    const version = pending.get(id);
                    if (version === undefined) return;
                    if (ids === undefined && version > before) return;
                    if (version > before) newer.push(id);
                    pending.delete(id);
                });
                return newer;
            };
        },
        pause: () => {
            paused = true;
            clearTimeout(timer);
            timer = undefined;
        },
        resume: () => {
            if (!paused || cancelled) return;
            paused = false;
            step = 0;
            if (pending.size > 0) schedule();
        },
        cancel: () => {
            cancelled = true;
            clearTimeout(timer);
            pending.clear();
        },
    };
}
