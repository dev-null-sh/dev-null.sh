import { Form, Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Download, RefreshCw, RotateCcw } from 'lucide-react';
import ExportController from '@/actions/App/Http/Controllers/ExportController';
import UserFeedTokenController from '@/actions/App/Http/Controllers/UserFeedTokenController';
import UserProfileController from '@/actions/App/Http/Controllers/UserProfileController';
import ActivityGraphEmbed from '@/components/activity-graph-embed';
import DeleteUser from '@/components/delete-user';
import { GitHubProfile } from '@/components/github-profile';
import Heading from '@/components/heading';
import LanePicker from '@/components/lane-picker';
import { RssFeedButton } from '@/components/rss-feed-button';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useNow } from '@/hooks/use-now';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatFullDateTime } from '@/lib/utils';
import { show } from '@/routes/user';

function formatSyncedAgo(date: string, now: Date): string {
    const seconds = Math.round(
        (now.getTime() - new Date(date).getTime()) / 1000,
    );

    if (seconds < 60) {
        return 'just now';
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
    feedUrl,
}: {
    preferredLane: number | null;
    automaticLane: number;
    githubSyncedAt: string | null;
    activityGraphUrl: string;
    profileUrl: string;
    feedUrl: string;
}) {
    const { user } = useUser();
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

            <ActivityGraphEmbed
                activityGraphUrl={activityGraphUrl}
                profileUrl={profileUrl}
            />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="RSS feed"
                    description="Read the latest commits from everyone you follow in your feed reader. Replies, pending, and rejected commits are left out."
                />
                <p className="mb-5 text-sm text-muted-foreground">
                    Your RSS link signs your feed reader in as you, so keep it
                    private. If it leaks, reset it: the old link stops working
                    right away.
                </p>
                <p
                    className="mb-5 rounded-lg border px-4 py-2.5 font-mono text-[13px] break-all text-muted-foreground"
                    data-test="rss-feed-url"
                >
                    {feedUrl}
                </p>
                <div className="flex flex-wrap gap-2">
                    <RssFeedButton feedUrl={feedUrl} />
                    <Form
                        {...UserFeedTokenController.update.form()}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                                data-test="reset-rss-feed-button"
                            >
                                <RotateCcw />
                                Reset link
                            </Button>
                        )}
                    </Form>
                </div>
            </section>

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Export your data"
                    description="Download everything we store about you: your profile, commits and replies (including pending, rejected, and deleted ones), stars, saved commits, follows, mutes, blocks, notifications, SSH key fingerprints, and active sessions."
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
