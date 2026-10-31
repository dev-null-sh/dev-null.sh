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
import { RemotesSetting } from '@/components/remotes-setting';
import TimezonePicker from '@/components/timezone-picker';
import TranslationLanguageSetting from '@/components/translation-language-setting';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { TranslationLanguage } from '@/lib/translation-languages';
import { formatSyncedAgo } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Lane } from '@/types';

export default function Edit({
    translationLanguage,
    translationLanguages,
    preferredLane,
    automaticLane,
    githubSyncedAt,
    activityGraphUrl,
    profileUrl,
}: {
    translationLanguage: string | null;
    translationLanguages: TranslationLanguage[];
    preferredLane: Lane | null;
    automaticLane: number;
    githubSyncedAt: string | null;
    activityGraphUrl: string;
    profileUrl: string;
}) {
    const { user } = useUser();
    const { formatFullDateTime } = useDateTime();
    const now = useNow();

    return (
        <>
            <Head title="Profile settings" />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Profile information"
                    description="Mirrored from GitHub when you sign in. To change any of it, change it on GitHub, then sync it here."
                />

                <GitHubProfile />

                <div className="mt-5 flex flex-wrap gap-2">
                    <Button asChild>
                        <a
                            href="https://github.com/settings/profile"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Edit on GitHub
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
                                Sync from GitHub
                            </Button>
                        )}
                    </Form>
                    <Button variant="outline" asChild>
                        <Link href={show(user)}>View profile</Link>
                    </Button>
                </div>

                <p
                    className="mt-3 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {githubSyncedAt ? (
                        <>
                            Last synced{' '}
                            <time
                                dateTime={githubSyncedAt}
                                title={formatFullDateTime(githubSyncedAt)}
                            >
                                {formatSyncedAgo(githubSyncedAt, now)}
                            </time>
                        </>
                    ) : (
                        'Last synced when you signed in'
                    )}
                </p>
            </section>

            <LanePicker
                preferredLane={preferredLane}
                automaticLane={automaticLane}
            />

            <TimezonePicker />

            <TranslationLanguageSetting
                language={translationLanguage}
                languages={translationLanguages}
            />

            <PasteisModeSetting />

            <RemotesSetting />

            <ActivityGraphEmbed
                activityGraphUrl={activityGraphUrl}
                profileUrl={profileUrl}
            />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Export your data"
                    description="Download everything we store about you: your profile, commits and replies (including pending, rejected, and deleted ones), stars, follows, mutes, blocks, the messages you sent, notifications, SSH key fingerprints, and active sessions."
                />
                <p className="mb-5 text-sm text-muted-foreground">
                    Other people's replies are excluded. Your export is a JSON
                    file. Your profile is still managed on GitHub.
                </p>
                <Button variant="outline" asChild>
                    <a href={ExportController.url()}>
                        <Download />
                        Download JSON
                    </a>
                </Button>
            </section>

            <DeleteUser />
        </>
    );
}

Edit.layout = [AppLayout, SettingsLayout];
