import type { User } from '@/types/auth';

export type CommitStatus = 'pending' | 'approved' | 'rejected';

export type LinkPreview = {
    url: string;
    title: string | null;
    description: string | null;
    image_url: string | null;
    site_name: string | null;
};

export type CommitImage = {
    id: string;
    url: string;
    width: number;
    height: number;
};

export type BodySegment =
    | { type: 'text'; text: string }
    | { type: 'code'; code: string }
    | { type: 'block'; language: string | null; html: string };

export type Commit = {
    id: string;
    user_id: string;
    parent_id: string | null;
    body: string;
    body_segments: BodySegment[];
    status: CommitStatus;
    series_id: string | null;
    series_position: number | null;
    series_size: number | null;
    created_at: string;
    updated_at: string;
    deleted_at?: null;
    stars_count: number;
    starred: boolean;
    user: User;
    replies_count?: number;
    is_saved?: boolean;
    parent?: (Omit<Commit, 'parent'> & { user: User }) | DeletedCommit | null;
    link_preview?: LinkPreview | null;
    images?: CommitImage[];
    series_parts?: Commit[];
    ai_decision?: Exclude<CommitStatus, 'pending'> | null;
    ai_categories?: string[] | null;
};

export type DeletedCommit = {
    id: string;
    parent_id: string | null;
    deleted_at: string;
    replies_count?: number;
    series_position?: number;
    series_size?: number;
};

export type ThreadCommit = Commit | DeletedCommit;

export type CursorPaginated<T> = {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
};
