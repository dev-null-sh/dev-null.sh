import type { ReactNode } from 'react';
import AutoModeratedCommitController from '@/actions/App/Http/Controllers/AutoModeratedCommitController';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import ModerationRuleController from '@/actions/App/Http/Controllers/ModerationRuleController';
import { PageTabs } from '@/components/page-tabs';

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
            <PageTabs
                label="Moderation"
                tabs={[
                    {
                        title: 'Queue',
                        href: ModerationController.show(),
                        isActive: active === 'queue',
                    },
                    {
                        title: 'Auto-moderated',
                        href: AutoModeratedCommitController.index(),
                        isActive: active === 'automated',
                    },
                    {
                        title: 'Rules',
                        href: ModerationRuleController.index(),
                        isActive: active === 'rules',
                    },
                ]}
            />
            {children}
        </div>
    );
}
