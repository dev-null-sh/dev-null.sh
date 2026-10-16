import type { ReactNode } from 'react';
import { ModerationTabs } from '@/components/moderation-tabs';

export function ModerationHeader({
    active,
    aside,
    children,
}: {
    active: 'queue' | 'automated' | 'rules';
    aside?: ReactNode;
    children?: ReactNode;
}) {
    return (
        <div className="sticky top-0 z-10 bg-card">
            <div className="flex h-14 items-center gap-3 border-b px-4">
                <h1 className="font-semibold">Moderation</h1>
                {aside && (
                    <div className="ml-auto min-w-0 truncate text-sm text-muted-foreground">
                        {aside}
                    </div>
                )}
            </div>
            <ModerationTabs active={active === 'queue' ? 'pending' : active} />
            {children}
        </div>
    );
}
