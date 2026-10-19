import { useEffect, useState } from 'react';
import CommitStatusController from '@/actions/App/Http/Controllers/CommitStatusController';
import type { Commit, CommitStatus } from '@/types';

const POLL_DELAYS = [1000, 2000, 3000, 5000, 8000, 13000];

/**
 * How long a commit that was approved while its author watched is still marked so, before its badge goes away.
 */
const JUST_APPROVED_MS = 3000;

type CheckedStatus = { status: CommitStatus; is_checking: boolean };

/**
 * A pending commit is checked in the background right after it is posted, so its status is fetched again until the
 * check is over, instead of the badge only changing after a reload. Once the check leaves it pending, it waits for a
 * moderator, which takes far longer than anyone should poll for.
 */
export function useCommitStatus(
    commit: Pick<Commit, 'id' | 'status' | 'is_checking'>,
): { status: CommitStatus; isChecking: boolean; isJustApproved: boolean } {
    const isPolling =
        commit.status === 'pending' && commit.is_checking === true;
    const [checked, setChecked] = useState<CheckedStatus | null>(null);
    const [hasGivenUp, setHasGivenUp] = useState(false);
    const [isJustApproved, setIsJustApproved] = useState(false);

    useEffect(() => {
        if (!isPolling) {
            return;
        }

        const controller = new AbortController();
        let timeout = 0;

        const poll = async (attempt: number) => {
            try {
                const response = await fetch(
                    CommitStatusController.show.url(commit.id),
                    {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    setHasGivenUp(true);

                    return;
                }

                const result = (await response.json()) as CheckedStatus;

                if (result.status !== 'pending' || !result.is_checking) {
                    setChecked(result);
                    setIsJustApproved(result.status === 'approved');

                    return;
                }
            } catch {
                if (controller.signal.aborted) {
                    return;
                }
            }

            if (attempt + 1 < POLL_DELAYS.length) {
                timeout = window.setTimeout(
                    () => void poll(attempt + 1),
                    POLL_DELAYS[attempt + 1],
                );
            } else {
                setHasGivenUp(true);
            }
        };

        timeout = window.setTimeout(() => void poll(0), POLL_DELAYS[0]);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [commit.id, isPolling]);

    useEffect(() => {
        if (!isJustApproved) {
            return;
        }

        const timeout = window.setTimeout(
            () => setIsJustApproved(false),
            JUST_APPROVED_MS,
        );

        return () => window.clearTimeout(timeout);
    }, [isJustApproved]);

    const current = isPolling && checked ? checked : null;

    return {
        status: current?.status ?? commit.status,
        isChecking: current ? current.is_checking : isPolling && !hasGivenUp,
        isJustApproved: current !== null && isJustApproved,
    };
}
