import type { User } from '@/types/auth';

export type CommitStatus = 'pending' | 'approved' | 'rejected';

export type LinkPreview = {
    url: string;
    title: string | null;
    description: string | null;
    image_url: string | null;
    site_name: string | null;
};

export type Commit = {
    id: string;
    user_id: string;
    parent_id: string | null;
    body: string;
    status: CommitStatus;
    created_at: string;
    updated_at: string;
    deleted_at?: null;
    stars_count: number;
    starred: boolean;
    user: User;
    replies_count?: number;
    parent?: (Omit<Commit, 'parent'> & { user: User }) | DeletedCommit | null;
    link_preview?: LinkPreview | null;
};

export type DeletedCommit = {
    id: string;
    parent_id: string | null;
    deleted_at: string;
    replies_count?: number;
};

export type ThreadCommit = Commit | DeletedCommit;

export type CursorPaginated<T> = {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
};
