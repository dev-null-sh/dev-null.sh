import { Form, Head, Link, usePage, usePoll } from '@inertiajs/react';
import { ArrowUpRight, HandHelping, Plus } from 'lucide-react';
import ContributorRequestController from '@/actions/App/Http/Controllers/ContributorRequestController';
import { CommitCard } from '@/components/commit-card';
import { GraphCanvas } from '@/components/graph-canvas';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { contributorHelp } from '@/types/contributor-request';
import type {
    ContributorHelp,
    ContributorRequest,
} from '@/types/contributor-request';

const selectClass =
    'h-10 min-w-0 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring';

export default function Index({
    requests,
    filters,
}: {
    requests: {
        data: ContributorRequest[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: {
        kind: ContributorHelp | null;
        status: 'open' | 'filled' | 'all';
    };
}) {
    const { auth } = usePage().props;
    usePoll(30000, { only: ['requests'] });

    return (
        <>
            <Head title="Help wanted" />
            <header className="sticky top-0 z-10 flex min-h-14 items-center justify-between gap-3 border-b bg-card px-4 py-2 sm:px-6">
                <h1 className="flex items-center gap-2 font-semibold">
                    <HandHelping
                        aria-hidden
                        className="size-5 text-muted-foreground"
                    />
                    Help wanted
                </h1>
                <Button size="sm" asChild>
                    <Link
                        href={
                            auth.user
                                ? ContributorRequestController.create()
                                : '/login'
                        }
                    >
                        <Plus aria-hidden />
                        Post a request
                    </Link>
                </Button>
            </header>
            <div className="border-b px-4 py-5 sm:px-6">
                <p className="text-sm text-muted-foreground">
                    Find a small way to move someone’s project forward. Code,
                    test, improve the docs, or offer a fresh pair of eyes.
                </p>
                <Form
                    {...ContributorRequestController.index.form()}
                    options={{ preserveState: false }}
                    className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-[1fr_1fr_auto]"
                >
                    <label className="min-w-0 space-y-1 text-sm">
                        <span>Help needed</span>
                        <select
                            name="kind"
                            aria-label="Help needed"
                            defaultValue={filters.kind ?? ''}
                            className={selectClass}
                        >
                            <option value="">Any kind</option>
                            {contributorHelp.map((kind) => (
                                <option key={kind.value} value={kind.value}>
                                    {kind.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="min-w-0 space-y-1 text-sm">
                        <span>Status</span>
                        <select
                            name="status"
                            aria-label="Status"
                            defaultValue={filters.status}
                            className={selectClass}
                        >
                            <option value="open">Open</option>
                            <option value="filled">Filled</option>
                            <option value="all">All requests</option>
                        </select>
                    </label>
                    <Button
                        variant="outline"
                        type="submit"
                        className="col-span-2 self-end sm:col-span-1"
                    >
                        Apply filters
                    </Button>
                </Form>
            </div>
            {requests.data.length === 0 ? (
                <section className="px-6 py-16 text-center" aria-live="polite">
                    <HandHelping
                        aria-hidden
                        className="mx-auto mb-3 size-8 text-muted-foreground"
                    />
                    <h2 className="font-semibold">
                        No requests match these filters
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Try another kind of help, or share something your
                        project needs.
                    </p>
                </section>
            ) : (
                <GraphCanvas>
                    {requests.data.map((item) => (
                        <article
                            key={item.id}
                            className="border-b"
                            data-contributor-request={item.id}
                        >
                            <div className="flex flex-wrap items-center gap-2 px-4 pt-4 sm:px-6">
                                <span className="rounded border bg-muted px-2 py-0.5 font-mono text-xs">
                                    {
                                        contributorHelp.find(
                                            (kind) => kind.value === item.kind,
                                        )?.label
                                    }
                                </span>
                                <span
                                    className={
                                        item.status === 'open'
                                            ? 'text-xs font-medium text-success'
                                            : 'text-xs text-muted-foreground'
                                    }
                                >
                                    {item.status === 'open'
                                        ? 'Open to help'
                                        : 'Filled'}
                                </span>
                                {item.can_manage && (
                                    <Form
                                        {...ContributorRequestController.update.form(
                                            item.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        className="ml-auto"
                                    >
                                        {({ processing }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value={
                                                        item.status === 'open'
                                                            ? 'filled'
                                                            : 'open'
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    {item.status === 'open'
                                                        ? 'Mark as filled'
                                                        : 'Reopen'}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                )}
                            </div>
                            <a
                                href={item.project_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="mx-4 mt-2 inline-flex max-w-[calc(100%-2rem)] items-center gap-1 text-sm break-all text-primary hover:underline sm:mx-6"
                            >
                                <span>
                                    {item.project_url.replace(
                                        /^https:\/\/github\.com\//i,
                                        '',
                                    )}
                                </span>
                                <ArrowUpRight
                                    aria-hidden
                                    className="size-3.5 shrink-0"
                                />
                            </a>
                            <CommitCard
                                commit={item.commit}
                                isFolded
                                endsWithDot
                                hasRailTail={false}
                            />
                        </article>
                    ))}
                </GraphCanvas>
            )}
            {(requests.prev_page_url || requests.next_page_url) && (
                <nav
                    aria-label="Request pages"
                    className="flex justify-between gap-3 px-4 py-5 sm:px-6"
                >
                    {requests.prev_page_url ? (
                        <Button asChild variant="outline">
                            <Link href={requests.prev_page_url}>
                                Newer requests
                            </Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                    {requests.next_page_url && (
                        <Button asChild variant="outline">
                            <Link href={requests.next_page_url}>
                                Older requests
                            </Link>
                        </Button>
                    )}
                </nav>
            )}
        </>
    );
}
Index.layout = AppLayout;
