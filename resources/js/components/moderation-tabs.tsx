import { index as claims } from '@/actions/App/Http/Controllers/BranchClaimRequestController';
import { index as verifications } from '@/actions/App/Http/Controllers/BranchVerificationRequestController';
import { show } from '@/actions/App/Http/Controllers/ModerationController';
import { index as reports } from '@/actions/App/Http/Controllers/ReportedCommitController';
import { PageTabs } from '@/components/page-tabs';

export function ModerationTabs({
    active,
}: {
    active: 'pending' | 'reports' | 'claims' | 'verifications';
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
                    href: reports(),
                    isActive: active === 'reports',
                },
                {
                    title: 'Claims',
                    href: claims(),
                    isActive: active === 'claims',
                },
                {
                    title: 'Verifications',
                    href: verifications(),
                    isActive: active === 'verifications',
                },
            ]}
        />
    );
}
