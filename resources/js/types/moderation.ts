import type { User } from '@/types/auth';
import type { Commit, CommitStatus, DeletedCommit } from '@/types/commit';

export type RejectionReason = {
    id: number;
    title: string;
    body: string;
};

export type CommitClaim = {
    commit_id: string;
    expires_at: string;
    user: User;
};

export type CommitDecision = {
    id: number;
    status: Exclude<CommitStatus, 'pending'>;
    reason: string | null;
    created_at: string;
    user: User | null;
};

export type LoggedCommitDecision = CommitDecision & {
    commit: Commit | DeletedCommit;
};
