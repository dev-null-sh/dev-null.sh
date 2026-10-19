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

export type NotificationCommit = Omit<Commit, 'user'> & {
    images_count: number;
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
          commit: NotificationCommit;
      })
    | (BaseNotification & {
          type: 'quoted';
          commit_id: string;
          subject_id: null;
          commit: Omit<NotificationCommit, 'quoted_commit'> & {
              quoted_commit?:
                  | Pick<
                        NotificationCommit,
                        'id' | 'body_segments' | 'deleted_at' | 'images_count'
                    >
                  | DeletedCommit
                  | null;
          };
      })
    | (BaseNotification & {
          type: 'mentioned' | 'approved' | 'rejected';
          commit_id: string;
          subject_id: null;
          commit: NotificationCommit;
      });
