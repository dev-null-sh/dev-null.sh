import type { Commit } from '@/types/commit';

export type ContributorHelp =
    | 'coding'
    | 'testing'
    | 'documentation'
    | 'design'
    | 'feedback';
export type ContributorRequest = {
    id: string;
    project_url: string;
    kind: ContributorHelp;
    status: 'open' | 'filled';
    filled_at: string | null;
    can_manage: boolean;
    commit: Commit;
};

export const contributorHelp: { value: ContributorHelp; label: string }[] = [
    { value: 'coding', label: 'Coding' },
    { value: 'testing', label: 'Testing' },
    { value: 'documentation', label: 'Documentation' },
    { value: 'design', label: 'Design' },
    { value: 'feedback', label: 'Feedback' },
];
