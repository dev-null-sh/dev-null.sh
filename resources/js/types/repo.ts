import type { User } from '@/types/auth';

export type RepoRole = 'maintainer' | 'contributor';

export type Repo = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    created_at: string;
    updated_at: string;
};

export type RepoListing = Repo & {
    contributors_count: number;
    pivot: { role: RepoRole };
};

export type RepoContributor = {
    id: number;
    repo_id: string;
    user_id: string;
    role: RepoRole;
    created_at: string;
    user: User;
};
