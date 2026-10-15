import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Download } from 'lucide-react';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import LanePicker from '@/components/lane-picker';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { show as exportPersonalData } from '@/routes/personal-data-export';
import { show } from '@/routes/user';

export default function Edit({
    preferredLane,
    automaticLane,
}: {
    preferredLane: number | null;
    automaticLane: number;
}) {
    const { auth } = usePage().props;

    const fields = [
        { label: 'name', value: auth.user.name },
        { label: 'username', value: `@${auth.user.username}` },
        { label: 'bio', value: auth.user.bio },
        { label: 'company', value: auth.user.company },
        { label: 'location', value: auth.user.location },
        { label: 'website', value: auth.user.website },
    ];

    return (
        <>
            <Head title="Profile settings" />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Profile information"
                    description="Mirrored from GitHub every time you sign in. To change any of it, change it on GitHub, then sign in again."
                />

                <div className="flex items-center gap-4">
                    <UserAvatar user={auth.user} className="size-14" />
                    <div className="min-w-0">
                        <p className="truncate font-semibold">
                            {auth.user.name}
                        </p>
                        <p className="truncate font-mono text-sm text-muted-foreground">
                            @{auth.user.username}
                        </p>
                    </div>
                </div>

                <dl className="mt-5 divide-y rounded-lg border text-sm">
                    {fields.map((field) => (
                        <div
                            key={field.label}
                            className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 px-4 py-2.5"
                        >
                            <dt className="font-mono text-[13px] text-muted-foreground">
                                {field.label}
                            </dt>
                            <dd
                                className={
                                    field.value
                                        ? 'break-words'
                                        : 'font-mono text-[13px] text-muted-foreground'
                                }
                            >
                                {field.value ?? 'null'}
                            </dd>
                        </div>
                    ))}
                </dl>

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
                    <a href={exportPersonalData.url()} download>
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
