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
    series_size?: number;
};
