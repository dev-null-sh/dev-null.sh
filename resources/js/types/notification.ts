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
    reason: string | null;
};

export type Notification =
    | (BaseNotification & {
          type: 'followed';
          commit_id: null;
          commit: null;
      })
    | (BaseNotification & {
          type: 'replied' | 'approved' | 'rejected';
          commit_id: string;
          commit: Omit<Commit, 'user'>;
      });
