import type { User } from '@/types/auth';
import type { Commit, DeletedCommit } from '@/types/commit';

type BaseNotification = {
    id: string;
    recipient_id: string;
    actor_id: string;
    read_at: string | null;
    created_at: string;
    updated_at: string;
    actor: User & { is_followed: boolean };
    reason: string | null;
};

export type NotificationGroup = Notification & {
    group_id: string;
    event_count: number;
    actors: User[];
    actors_count: number;
    unread_count: number;
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
          type: 'leveled_up';
          commit_id: null;
          subject_id: null;
          commit: null;
          level: number;
          rank: string;
          rank_description: string;
      })
    | (BaseNotification & {
          type: 'quoted';
          commit_id: string;
          subject_id: null;
          commit: Omit<Commit, 'user' | 'quoted_commit'> & {
              quoted_commit?:
                  | Pick<Commit, 'id' | 'body_segments' | 'deleted_at'>
                  | DeletedCommit
                  | null;
          };
      })
    | (BaseNotification & {
          type: 'mentioned' | 'approved' | 'rejected';
          commit_id: string;
          subject_id: null;
          commit: Omit<Commit, 'user'>;
      });
