import type { User } from '@/types/auth';
import type { Commit } from '@/types/commit';

type BaseNotification = {
    id: string;
    recipient_id: string;
    actor_id: string;
    read_at: string | null;
    created_at: string;
    updated_at: string;
    actor: User;
    actors: User[];
    actors_count: number;
    unread_count: number;
    reason: string | null;
};

export type Notification =
    | (BaseNotification & {
          type: 'followed';
          commit_id: null;
          subject_id: null;
          commit: null;
      })
    | (BaseNotification & {
          type: 'replied' | 'starred';
          commit_id: string;
          subject_id: string;
          commit: Omit<Commit, 'user'>;
      })
    | (BaseNotification & {
          type: 'mentioned' | 'approved' | 'rejected';
          commit_id: string;
          subject_id: null;
          commit: Omit<Commit, 'user'>;
      });
