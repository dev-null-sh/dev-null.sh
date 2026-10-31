import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import EventThemeSelect from '@/components/event-theme-select';
import Heading from '@/components/heading';
import LaneDimmingTabs from '@/components/lane-dimming-tabs';
import NavigationTabs from '@/components/navigation-tabs';
import RepliesPerPageTabs from '@/components/replies-per-page-tabs';
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
                    title="Event theme"
                    description="Dress up the site for Halloween, Christmas and New Year. Auto picks the theme of the day, from two days before to the day after, and Default keeps the usual colors all year."
                />
                <EventThemeSelect />
            </section>

            <section className="hidden border-t px-4 py-6 sm:px-6 lg:block">
                <Heading
                    title="Navigation"
                    description="The full menu with labels, or only its icons, which leaves more room for your log."
                />
                <NavigationTabs />
            </section>

            <section className="border-t px-4 py-6 sm:px-6">
                <Heading
                    title="Lane dimming"
                    description="Fade the other commits while your pointer rests on one, or on its author, so their lane stands out."
                />
                <LaneDimmingTabs />
            </section>

            <section className="border-t px-4 py-6 sm:px-6">
                <Heading
                    title="Replies"
                    description="How many replies show under a commit, in your log and in a thread, before you ask for more."
                />
                <RepliesPerPageTabs />
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
