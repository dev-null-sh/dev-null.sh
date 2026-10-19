import type { User } from '@/types/auth';
import type { Commit } from '@/types/commit';

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

export type NotificationTag = {
    id: string;
    name: string;
};

export type Notification =
    | (BaseNotification & {
          type: 'followed';
          commit_id: null;
          subject_id: null;
          tag_id: null;
          commit: null;
          tag: null;
      })
    | (BaseNotification & {
          type: 'replied' | 'starred';
          commit_id: string;
          subject_id: string;
          tag_id: null;
          commit: Omit<Commit, 'user'>;
          tag: null;
      })
    | (BaseNotification & {
          type: 'quoted' | 'mentioned' | 'approved' | 'rejected' | 'tag_activity';
          commit_id: string;
          subject_id: null;
          tag_id: null;
          commit: Omit<Commit, 'user'>;
          tag: null;
      })
    | (BaseNotification & {
          type: 'tag_member_added' | 'tag_role_changed';
          commit_id: null;
          subject_id: null;
          tag_id: string;
          commit: null;
          tag: NotificationTag;
      });
