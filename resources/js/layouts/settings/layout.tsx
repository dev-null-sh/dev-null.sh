import type { PropsWithChildren } from 'react';
import { PageTabs } from '@/components/page-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { settingsTabs } from '@/layouts/settings/tabs';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            <div className="sticky top-0 z-10 bg-card [view-transition-class:tab-header] [view-transition-name:settings-header]">
                <h1 className="flex h-14 items-center border-b px-4 font-semibold sm:px-6">
                    Settings
                </h1>
                <PageTabs
                    label="Settings"
                    tabs={settingsTabs.map((tab) => ({
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
