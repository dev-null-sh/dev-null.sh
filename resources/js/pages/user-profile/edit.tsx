import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Download } from 'lucide-react';
import ExportController from '@/actions/App/Http/Controllers/ExportController';
import DeleteUser from '@/components/delete-user';
import { GitHubProfile } from '@/components/github-profile';
import Heading from '@/components/heading';
import LanePicker from '@/components/lane-picker';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { show } from '@/routes/user';

export default function Edit({
    preferredLane,
    automaticLane,
}: {
    preferredLane: number | null;
    automaticLane: number;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Profile settings" />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Profile information"
                    description="Mirrored from GitHub every time you sign in. To change any of it, change it on GitHub, then sign in again."
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
                    <Button variant="outline" asChild>
                        <Link href={show(auth.user)}>View profile</Link>
                    </Button>
                </div>
            </section>

            <LanePicker
                preferredLane={preferredLane}
                automaticLane={automaticLane}
            />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Export your data"
                    description="Download your GitHub profile as stored here, your color preference, all your live commits and replies with their moderation status, and the usernames you follow. Includes pending and rejected commits."
                />
                <p className="mb-5 text-sm text-muted-foreground">
                    Deleted commits and other people's replies are excluded.
                    Your export is a JSON file. Your profile is still managed on
                    GitHub.
                </p>
                <Button variant="outline" asChild>
                    <a href={ExportController.url()} download>
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
