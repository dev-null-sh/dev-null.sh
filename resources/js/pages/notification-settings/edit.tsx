import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PushToggle } from '@/components/push-toggle';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

export default function Edit() {
    return (
        <>
            <Head title="Notification settings" />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Push notifications"
                    description="Get a push notification on this device when someone you follow commits. Each browser or device is turned on separately."
                />
                <PushToggle />
            </section>
        </>
    );
}

Edit.layout = [AppLayout, SettingsLayout];
