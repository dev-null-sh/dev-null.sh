import type { User } from '@/types/auth';

export type TalkLinkKind = 'slides' | 'recording';

export type TalkSummary = {
    id: string;
    title: string;
    description: string;
    presented_on: string;
    link_url: string;
    link_kind: TalkLinkKind;
    event_name: string | null;
    published_at: string | null;
    commit_status?: string | null;
};

export type Talk = TalkSummary & {
    user: User;
    commit_id: string | null;
};
