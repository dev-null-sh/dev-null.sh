import { useLang } from '@erag/lang-sync-inertia/react';
import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowUpRight, Download, RefreshCw } from 'lucide-react';
import ExportController from '@/actions/App/Http/Controllers/ExportController';
import UserProfileController from '@/actions/App/Http/Controllers/UserProfileController';
import ActivityGraphEmbed from '@/components/activity-graph-embed';
import DeleteUser from '@/components/delete-user';
import { GitHubProfile } from '@/components/github-profile';
import Heading from '@/components/heading';
import LanePicker from '@/components/lane-picker';
import { PasteisModeSetting } from '@/components/pasteis-mode-setting';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useNow } from '@/hooks/use-now';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatFullDateTime } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Lane } from '@/types';

function formatSyncedAgo(date: string, now: Date, justNow: string): string {
    const seconds = Math.round(
        (now.getTime() - new Date(date).getTime()) / 1000,
    );

    if (seconds < 60) {
        return justNow;
    }

    const [value, unit]: [number, Intl.RelativeTimeFormatUnit] =
        seconds < 3600
            ? [Math.floor(seconds / 60), 'minute']
            : seconds < 86400
              ? [Math.floor(seconds / 3600), 'hour']
              : [Math.floor(seconds / 86400), 'day'];

    return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(
        -value,
        unit,
    );
}

export default function Edit({
    preferredLane,
    automaticLane,
    githubSyncedAt,
    activityGraphUrl,
    profileUrl,
}: {
    preferredLane: Lane | null;
    automaticLane: number;
    githubSyncedAt: string | null;
    activityGraphUrl: string;
    profileUrl: string;
}) {
    const { user } = useUser();
    const now = useNow();
    const { __ } = useLang();

    return (
        <>
            <Head title={__('settings.profile.title')} />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.profile.information')}
                    description={__('settings.profile.information_description')}
                />

                <GitHubProfile />

                <div className="mt-5 flex flex-wrap gap-2">
                    <Button asChild>
                        <a
                            href="https://github.com/settings/profile"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {__('settings.profile.edit_on_github')}
                            <ArrowUpRight />
                        </a>
                    </Button>
                    <Form
                        {...UserProfileController.update.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => router.flushAll()}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                                aria-busy={processing}
                                data-test="sync-profile-button"
                            >
                                {processing ? <Spinner /> : <RefreshCw />}
                                {__('settings.profile.sync')}
                            </Button>
                        )}
                    </Form>
                    <Button variant="outline" asChild>
                        <Link href={show(user)}>
                            {__('settings.profile.view')}
                        </Link>
                    </Button>
                </div>

                <p
                    className="mt-3 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {githubSyncedAt ? (
                        <>
                            {__('settings.profile.last_synced')}{' '}
                            <time
                                dateTime={githubSyncedAt}
                                title={formatFullDateTime(githubSyncedAt)}
                            >
                                {formatSyncedAgo(
                                    githubSyncedAt,
                                    now,
                                    __('settings.profile.just_now'),
                                )}
                            </time>
                        </>
                    ) : (
                        __('settings.profile.last_synced_at_sign_in')
                    )}
                </p>
            </section>

            <LanePicker
                preferredLane={preferredLane}
                automaticLane={automaticLane}
            />

            <PasteisModeSetting />

            <ActivityGraphEmbed
                activityGraphUrl={activityGraphUrl}
                profileUrl={profileUrl}
            />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title={__('settings.profile.export')}
                    description={__('settings.profile.export_description')}
                />
                <p className="mb-5 text-sm text-muted-foreground">
                    {__('settings.profile.export_note')}
                </p>
                <Button variant="outline" asChild>
                    <a href={ExportController.url()}>
                        <Download />
                        {__('settings.profile.download_json')}
                    </a>
                </Button>
            </section>

            <DeleteUser />
        </>
    );
}

Edit.layout = [AppLayout, SettingsLayout];
