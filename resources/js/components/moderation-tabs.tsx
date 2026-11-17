import { usePage } from '@inertiajs/react';
import { index as bulk } from '@/actions/App/Http/Controllers/BulkActivityController';
import { index as guilds } from '@/actions/App/Http/Controllers/GuildReportController';
import { index as people } from '@/actions/App/Http/Controllers/ModeratedUserController';
import { show } from '@/actions/App/Http/Controllers/ModerationController';
import { index } from '@/actions/App/Http/Controllers/ReportedCommitController';
import { PageTabs } from '@/components/page-tabs';

export function ModerationTabs({
    active,
}: {
    active: 'pending' | 'reports' | 'people' | 'bulk' | 'guilds';
}) {
    const hasGuilds = usePage().props.guilds != null;
    return (
        <PageTabs
            label="Moderation queues"
            tabs={[
                {
                    title: 'Pending review',
                    shortTitle: 'Pending',
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
                    shortTitle: 'People',
                    href: people(),
                    isActive: active === 'people',
                },
                {
                    title: 'Bulk',
                    href: bulk(),
                    isActive: active === 'bulk',
                },
                ...(hasGuilds
                    ? [
                          {
                              title: 'Guilds',
                              href: guilds(),
                              isActive: active === 'guilds',
                          },
                      ]
                    : []),
            ]}
        />
    );
}
