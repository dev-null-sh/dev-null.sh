import type { User } from '@/types/auth';
import type { Commit, DeletedCommit } from '@/types/commit';
import type { Organization } from '@/types/organization';

type BaseNotification = {
    id: string;
    recipient_id: string;
    actor_id: string;
    read_at: string | null;
    created_at: string;
    updated_at: string;
    actor: User & { is_followed: boolean };
    // The organization the user hears about it for, as one of its members, or null when it is their own.
    organization: Organization | null;
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
          commit: Omit<Commit, 'author'>;
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
          commit: Omit<Commit, 'author' | 'quoted_commit'> & {
              quoted_commit?:
                  | Pick<Commit, 'id' | 'body_segments' | 'deleted_at'>
                  | DeletedCommit
                  | null;
          };
      })
    | (BaseNotification & {
          type: 'mentioned' | 'first_commit' | 'approved' | 'rejected';
          commit_id: string;
          subject_id: null;
          commit: Omit<Commit, 'author'>;
      });
