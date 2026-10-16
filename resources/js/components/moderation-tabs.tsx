import { index as automated } from '@/actions/App/Http/Controllers/AutoModeratedCommitController';
import { show } from '@/actions/App/Http/Controllers/ModerationController';
import { index as rules } from '@/actions/App/Http/Controllers/ModerationRuleController';
import { index } from '@/actions/App/Http/Controllers/ReportedCommitController';
import { PageTabs } from '@/components/page-tabs';

export function ModerationTabs({
    active,
}: {
    active: 'pending' | 'reports' | 'automated' | 'rules';
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
                    title: 'Auto-moderated',
                    href: automated(),
                    isActive: active === 'automated',
                },
                {
                    title: 'Rules',
                    href: rules(),
                    isActive: active === 'rules',
                },
                {
                    title: 'Reports',
                    href: index(),
                    isActive: active === 'reports',
                },
            ]}
        />
    );
}
