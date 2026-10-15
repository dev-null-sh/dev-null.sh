import type { User } from '@/types/auth';

export type CommitStatus = 'pending' | 'approved' | 'rejected';

export type Commit = {
    id: string;
    user_id: string;
    body: string;
    status: CommitStatus;
    created_at: string;
    updated_at: string;
    user: User;
};

export type CursorPaginated<T> = {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
};
