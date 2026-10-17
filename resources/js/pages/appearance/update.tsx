import { useLang } from '@erag/lang-sync-inertia/react';
import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import LanguageSelect from '@/components/language-select';
import WhoToFollowTabs from '@/components/who-to-follow-tabs';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

export default function Update() {
    const { __ } = useLang();

    return (
        <>
            <Head title={__('settings.appearance.title')} />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.appearance.theme')}
                    description={__('settings.appearance.theme_description')}
                />
                <AppearanceTabs />
            </section>

            <section className="border-t px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.language.title')}
                    description={__('settings.language.description')}
                />
                <LanguageSelect />
            </section>

            <section className="border-t px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.appearance.who_to_follow')}
                    description={__(
                        'settings.appearance.who_to_follow_description',
                    )}
                />
                <WhoToFollowTabs />
            </section>
        </>
    );
}

Update.layout = [AppLayout, SettingsLayout];
