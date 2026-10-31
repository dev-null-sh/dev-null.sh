import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import OrganizationProfileController from '@/actions/App/Http/Controllers/OrganizationProfileController';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { OrganizationAvatar } from '@/components/organization-avatar';
import { PageTabs } from '@/components/page-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { Organization } from '@/types';

/**
 * An organization's settings, like the user's. Only admins have its profile, first; every member sees its members.
 */
export default function OrganizationSettingsLayout({
    children,
}: PropsWithChildren) {
    const { organization, role } = usePage<{
        organization: Organization & { id: string };
        role: 'admin' | 'member';
    }>().props;
    const { isCurrentUrl } = useCurrentUrl();

    const tabs = [
        ...(role === 'admin'
            ? [
                  {
                      title: 'Profile',
                      href: OrganizationProfileController.edit(organization.id),
                  },
              ]
            : []),
        {
            title: 'Members',
            href: OrganizationMemberController.index(organization.id),
        },
    ];

    return (
        <>
            <div className="sticky top-0 z-10 bg-card [view-transition-class:tab-header] [view-transition-name:settings-header]">
                <div className="flex h-14 items-center gap-3 border-b px-4 sm:px-6">
                    <h1 className="shrink-0 font-semibold">Settings</h1>
                    <Link
                        href={show(organization.handle)}
                        className="flex min-w-0 items-center gap-1.5 font-mono text-[13px] text-muted-foreground hover:text-foreground"
                    >
                        <OrganizationAvatar
                            organization={organization}
                            className="size-5 rounded text-[10px]"
                            pixels={40}
                        />
                        <span className="truncate">@{organization.handle}</span>
                    </Link>
                </div>
                <PageTabs
                    label="Organization settings"
                    tabs={tabs.map((tab) => ({
                        ...tab,
                        isActive: isCurrentUrl(tab.href),
                    }))}
                />
            </div>

            <div className="divide-y [view-transition-class:tab-content] [view-transition-name:settings-content]">
                {children}
            </div>
        </>
    );
}
