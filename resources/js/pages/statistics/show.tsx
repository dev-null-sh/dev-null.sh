import { Head } from '@inertiajs/react';
import { DocumentPage, DocumentSection } from '@/components/document-page';
import { StatisticsActivity } from '@/components/statistics-activity';
import AppLayout from '@/layouts/app-layout';
import type { Statistics } from '@/types';

const timestampFormat = new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: 'UTC',
});

export default function Show({ statistics }: { statistics: Statistics }) {
    const periodTotal = statistics.activity.reduce(
        (sum, bucket) => sum + bucket.count,
        0,
    );

    return (
        <>
            <Head title="Statistics" />

            <DocumentPage title="Statistics" meta="/dev/null">
                <DocumentSection
                    id="community"
                    title="The community in numbers"
                >
                    <p className="text-muted-foreground">
                        Developers and the commits they share on /dev/null.
                    </p>
                    <dl className="grid gap-4 border-y py-4 sm:grid-cols-2">
                        <div className="min-w-0">
                            <dt className="text-sm text-muted-foreground">
                                Developers
                            </dt>
                            <dd className="mt-1 font-mono text-2xl font-medium break-words tabular-nums">
                                {statistics.users.toLocaleString('en-US')}
                            </dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-sm text-muted-foreground">
                                Public commits
                            </dt>
                            <dd className="mt-1 font-mono text-2xl font-medium break-words tabular-nums">
                                {statistics.commits.toLocaleString('en-US')}
                            </dd>
                        </div>
                    </dl>
                    <p className="text-sm text-muted-foreground">
                        Current accounts and approved commits, including
                        replies. Deleted commits are excluded.
                    </p>
                </DocumentSection>

                <DocumentSection id="daily-activity" title="Daily activity">
                    <p className="text-sm text-muted-foreground">
                        <span className="font-mono tabular-nums">
                            {periodTotal.toLocaleString('en-US')}
                        </span>{' '}
                        {periodTotal === 1 ? 'public commit' : 'public commits'}{' '}
                        over the last 30 days. Days use UTC; today is still in
                        progress.
                    </p>
                    <StatisticsActivity activity={statistics.activity} />
                </DocumentSection>

                <footer className="space-y-1 px-4 py-5 text-sm text-muted-foreground sm:px-6">
                    <p>
                        Last updated{' '}
                        <time dateTime={statistics.updatedAt}>
                            {timestampFormat.format(
                                new Date(statistics.updatedAt),
                            )}{' '}
                            UTC
                        </time>
                    </p>
                    <p>Statistics are cached for five minutes.</p>
                </footer>
            </DocumentPage>
        </>
    );
}

Show.layout = AppLayout;
