import type { ReactNode } from 'react';
import { index as people } from '@/actions/App/Http/Controllers/ModeratedUserController';
import { show } from '@/actions/App/Http/Controllers/ModerationController';
import { index as covers } from '@/actions/App/Http/Controllers/PendingCoverImageController';
import { index } from '@/actions/App/Http/Controllers/ReportedCommitController';
import { PageTabs } from '@/components/page-tabs';

/**
 * The sticky header every moderation queue shares, with room below the tabs for a queue's own toolbar.
 */
export function ModerationHeader({
    active,
    children,
}: {
    active: 'pending' | 'reports' | 'covers' | 'people';
    children?: ReactNode;
}) {
    return (
        <div className="sticky top-0 z-10 bg-card">
            <div className="flex h-14 items-center border-b px-4">
                <h1 className="font-semibold">Moderation</h1>
                {active !== 'people' && (
                    <p className="ml-auto text-sm text-muted-foreground">
                        Oldest first
                    </p>
                )}
            </div>
            <PageTabs
                label="Moderation queues"
                tabs={[
                    {
                        title: 'Pending review',
                        href: show(),
                        isActive: active === 'pending',
                    },
                    {
                        title: 'Reports',
                        href: index(),
                        isActive: active === 'reports',
                    },
                    {
                        title: 'Covers',
                        href: covers(),
                        isActive: active === 'covers',
                    },
                    {
                        title: 'Timeouts & bans',
                        href: people(),
                        isActive: active === 'people',
                    },
                ]}
            />
            {children}
        </div>
    );
}
