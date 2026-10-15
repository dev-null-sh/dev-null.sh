import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import WhoToFollowTabs from '@/components/who-to-follow-tabs';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

export default function Update() {
    return (
        <>
            <Head title="Appearance settings" />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Theme"
                    description="System follows your operating system, light by day and dark by night if that's how you have it set."
                />
                <AppearanceTabs />
            </section>

            <section className="border-t px-4 py-6 sm:px-6">
                <Heading
                    title="Who to follow"
                    description="Three people you might want to follow, in the right column, or in your log on smaller screens."
                />
                <WhoToFollowTabs />
            </section>
        </>
    );
}

Update.layout = [AppLayout, SettingsLayout];
