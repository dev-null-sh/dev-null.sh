import type { ReactNode } from 'react';
import type { DeletedCommit } from '@/types/commit';

export type AppLayoutProps = {
    children: ReactNode;
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type TimeoutFlash = {
    reason: string;
    remaining: string;
    ends_at: string;
};

export type CommittedFlash = {
    id: string;
    body: string;
};

export type DeletedFlash = DeletedCommit & {
    expires_at: string;
    server_time: string;
};
