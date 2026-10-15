import type { User } from '@/types/auth';
import type { Topic } from '@/types/topic';

export type CommitStatus = 'pending' | 'approved' | 'rejected';

export type Commit = {
    id: string;
    user_id: string;
    title: string;
    description: string | null;
    status: CommitStatus;
    created_at: string;
    updated_at: string;
    user: User;
    promoted_topics: Pick<Topic, 'id' | 'name'>[];
};

export type CursorPaginated<T> = {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
};
