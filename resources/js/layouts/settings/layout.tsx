import { useLang } from '@erag/lang-sync-inertia/react';
import type { PropsWithChildren } from 'react';
import { PageTabs } from '@/components/page-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editSafety } from '@/routes/safety';
import { show as showTerminal } from '@/routes/terminal';
import { edit } from '@/routes/user-profile';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl } = useCurrentUrl();
    const { __ } = useLang();

    return (
        <>
            <div className="sticky top-0 z-10 bg-card [view-transition-class:tab-header] [view-transition-name:settings-header]">
                <h1 className="flex h-14 items-center border-b px-4 font-semibold sm:px-6">
                    {__('settings.title')}
                </h1>
                <PageTabs
                    label={__('settings.title')}
                    name="Settings"
                    tabs={[
                        {
                            title: __('settings.tabs.profile'),
                            href: edit(),
                            isActive: isCurrentUrl(edit()),
                        },
                        {
                            title: __('settings.tabs.appearance'),
                            href: editAppearance(),
                            isActive: isCurrentUrl(editAppearance()),
                        },
                        {
                            title: __('settings.tabs.terminal'),
                            href: showTerminal(),
                            isActive: isCurrentUrl(showTerminal()),
                        },
                        {
                            title: __('settings.tabs.safety'),
                            href: editSafety(),
                            isActive: isCurrentUrl(editSafety()),
                        },
                    ]}
                />
            </div>

            <div className="divide-y [view-transition-class:tab-content] [view-transition-name:settings-content]">
                {children}
            </div>
        </>
    );
}
