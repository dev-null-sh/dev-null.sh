import CommitReplyController from '@/actions/App/Http/Controllers/CommitReplyController';
import type { CursorPaginated, ThreadCommit } from '@/types';

export async function fetchReplies(
    commit: ThreadCommit,
    cursor: string | null,
): Promise<CursorPaginated<ThreadCommit>> {
    const response = await fetch(
        CommitReplyController.index(commit, { query: { cursor } }).url,
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    if (!response.ok) {
        throw new Error('Unable to load replies');
    }

    return (await response.json()) as CursorPaginated<ThreadCommit>;
}
