import { Head, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { ChangelogContent } from '@/components/changelog-dialog';
import { useChangelog } from '@/hooks/use-changelog';
import AppLayout from '@/layouts/app-layout';
import { browserTimeZone } from '@/lib/utils';

export default function Show() {
    const { auth } = usePage().props;
    const timeZone = auth.user?.timezone ?? browserTimeZone();
    const { load, loadOlder, ...changelog } = useChangelog(timeZone);

    useEffect(() => {
        load();
    }, [load]);

    return (
        <>
            <Head title="Changelog" />
            <ChangelogContent
                {...changelog}
                onLoadOlder={loadOlder}
                timeZone={timeZone}
            />
        </>
    );
}

Show.layout = AppLayout;
