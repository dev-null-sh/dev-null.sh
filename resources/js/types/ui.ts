import type { ReactNode } from 'react';

export type AppLayoutProps = {
    children: ReactNode;
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type CommittedFlash = {
    id: string;
    body: string;
};

export type DeletedFlash = {
    id: string;
    expires_at: string;
    server_time: string;
};
