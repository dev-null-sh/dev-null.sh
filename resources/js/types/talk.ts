import type { Lane, User } from '@/types/auth';
import type { Embed } from '@/types/commit';

export type TalkSummary = {
    id: string;
    title: string;
    description: string;
    presented_on: string;
    slides_url: string | null;
    recording_url: string | null;
    event_name: string | null;
    published_at: string | null;
    commit_status?: string | null;
    recording_embed?: Embed | null;
    slides_embed?: Embed | null;
};

export type Talk = TalkSummary & {
    user: User;
    commit_id: string | null;
};

export type TalkExplorerItem = {
    id: string;
    title: string;
    event_name: string | null;
    presented_on: string;
    user: Pick<User, 'id' | 'username' | 'name' | 'avatar_url'> & {
        lane: Lane;
    };
};
