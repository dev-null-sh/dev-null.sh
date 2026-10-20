import { index as people } from '@/actions/App/Http/Controllers/ModeratedUserController';
import { show } from '@/actions/App/Http/Controllers/ModerationController';
import { index } from '@/actions/App/Http/Controllers/ReportedCommitController';
import { PageTabs } from '@/components/page-tabs';

export function ModerationTabs({
    active,
}: {
    active: 'pending' | 'reports' | 'people';
}) {
    return (
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
                    title: 'Timeouts & bans',
                    href: people(),
                    isActive: active === 'people',
                },
            ]}
        />
    );
}
