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
          branch_id: null;
          commit: null;
          branch: null;
      })
    | (BaseNotification & {
          type: 'replied' | 'starred';
          commit_id: string;
          subject_id: string;
          branch_id: null;
          commit: Omit<Commit, 'user'>;
          branch: null;
      })
    | (BaseNotification & {
          type: 'quoted' | 'mentioned' | 'approved' | 'rejected' | 'branch_activity';
          commit_id: string;
          subject_id: null;
          branch_id: null;
          commit: Omit<Commit, 'user'>;
          branch: null;
      })
    | (BaseNotification & {
          type:
              | 'branch_member_added'
              | 'branch_role_changed'
              | 'branch_claim_approved'
              | 'branch_claim_rejected'
              | 'branch_ownership_transferred';
          commit_id: null;
          subject_id: null;
          branch_id: string;
          commit: null;
          branch: NotificationTag;
      });
