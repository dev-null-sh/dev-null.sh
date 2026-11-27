import { Head, Link, router } from '@inertiajs/react';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/GuildReportController';
import { store as approve } from '@/actions/App/Http/Controllers/GuildSubmissionApprovalController';
import { destroy as reject } from '@/actions/App/Http/Controllers/GuildSubmissionController';
import { GuildIcon } from '@/components/guild-icon';
import { GuildRemovalButton } from '@/components/guild-moderation';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { Guild } from '@/types/guild';

type Report = { id: string; details: string; guild: Guild };
type Submission = Pick<
    Guild,
    'id' | 'handle' | 'name' | 'description' | 'emoji' | 'icon_url'
> & { author: string; is_edit: boolean; remove_icon: boolean };
type Reports = {
    data: Report[];
    current_page: number;
    next_page_url: string | null;
};

export default function Guilds({
    submissions,
    reports,
}: {
    submissions: Submission[];
    reports: Reports;
}) {
    const review = (
        submission: Submission,
        route: { url: string; method: 'post' | 'delete' },
    ) =>
        router
            .optimistic((props: { submissions: Submission[] }) => ({
                submissions: props.submissions.filter(
                    ({ id }) => id !== submission.id,
                ),
            }))
            .visit(route, { preserveScroll: true });

    return (
        <>
            <Head title="Guild moderation" />
            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                </div>
                <ModerationTabs active="guilds" />
            </div>
            {submissions.map((submission) => (
                <article
                    key={submission.id}
                    className="space-y-4 border-b p-4 sm:p-6"
                >
                    <div className="flex min-w-0 items-center gap-2">
                        <GuildIcon
                            guild={submission}
                            className="size-8 text-xl"
                        />
                        <p
                            className="min-w-0 truncate font-semibold"
                            title={`${submission.name} · #${submission.handle}`}
                        >
                            {submission.name} · #{submission.handle}
                        </p>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {submission.is_edit ? 'Edit' : 'New guild'} by @
                        {submission.author}, flagged by the automated
                        moderation. Nothing is public until you approve it.
                        {submission.is_edit && (
                            <>
                                {' '}
                                {submission.icon_url
                                    ? 'Replaces the guild icon.'
                                    : submission.remove_icon
                                      ? 'Removes the guild image and uses the emoji.'
                                      : 'Keeps the current guild icon.'}
                            </>
                        )}
                    </p>
                    <p className="wrap-anywhere whitespace-pre-wrap">
                        {submission.description}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            onClick={() =>
                                review(submission, approve(submission.id))
                            }
                        >
                            Approve
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                review(submission, reject(submission.id))
                            }
                        >
                            Reject
                        </Button>
                    </div>
                </article>
            ))}
            {submissions.length === 0 && reports.data.length === 0 && (
                <p className="p-6 text-muted-foreground">
                    No guilds or guild reports waiting for review.
                </p>
            )}
            {reports.data.map((report) => (
                <article
                    key={report.id}
                    className="space-y-4 border-b p-4 sm:p-6"
                >
                    <Link
                        prefetch
                        href={show(report.guild.handle)}
                        className="font-semibold text-primary hover:underline"
                    >
                        {report.guild.name} · #{report.guild.handle}
                    </Link>
                    <p className="wrap-anywhere whitespace-pre-wrap">
                        {report.guild.description}
                    </p>
                    <div className="rounded-md border bg-muted p-3 text-sm">
                        <p className="mb-1 font-semibold">Private report</p>
                        <p className="wrap-anywhere whitespace-pre-wrap">
                            {report.details}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router
                                    .optimistic(
                                        (props: { reports: Reports }) => ({
                                            reports: {
                                                ...props.reports,
                                                data: props.reports.data.filter(
                                                    ({ id }) =>
                                                        id !== report.id,
                                                ),
                                            },
                                        }),
                                    )
                                    .visit(destroy(report.id), {
                                        preserveScroll: true,
                                    })
                            }
                        >
                            Dismiss report
                        </Button>
                        <GuildRemovalButton guild={report.guild} />
                    </div>
                </article>
            ))}
            {(reports.current_page > 1 || reports.next_page_url) && (
                <nav
                    aria-label="Guild reports"
                    className="flex justify-between gap-3 p-4"
                >
                    {reports.current_page > 1 && (
                        <Button asChild variant="outline">
                            <Link
                                prefetch
                                href={index({
                                    query: { page: reports.current_page - 1 },
                                })}
                                only={['reports']}
                            >
                                Previous
                            </Link>
                        </Button>
                    )}
                    {reports.next_page_url && (
                        <Button asChild variant="outline">
                            <Link
                                prefetch
                                href={index({
                                    query: { page: reports.current_page + 1 },
                                })}
                                only={['reports']}
                            >
                                Next
                            </Link>
                        </Button>
                    )}
                </nav>
            )}
        </>
    );
}

Guilds.layout = AppLayout;
