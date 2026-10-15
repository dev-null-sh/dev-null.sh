import type { User } from '@/types/auth';

export type CommitStatus = 'pending' | 'approved' | 'rejected';

export type CommitAuthor = User & {
    is_following: boolean;
    followers_count: number;
    following_count: number;
};

export type Commit = {
    id: string;
    user_id: string;
    body: string;
    status: CommitStatus;
    created_at: string;
    updated_at: string;
    user: CommitAuthor;
};

export type CursorPaginated<T> = {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
};
