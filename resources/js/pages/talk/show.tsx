import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, ExternalLink } from 'lucide-react';
import TalkController from '@/actions/App/Http/Controllers/TalkController';
import { CommitCard } from '@/components/commit-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { show as showUser } from '@/routes/user';
import type { Commit, CursorPaginated, Talk, ThreadCommit } from '@/types';

export default function Show({
    talk,
    commit,
    replies,
}: {
    talk: Talk;
    commit: Commit;
    replies: CursorPaginated<ThreadCommit>;
}) {
    const { auth } = usePage().props;
    const isOwner = auth.user?.id === talk.user.id;

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(showUser(talk.user, { query: { tab: 'talks' } }));
    };

    return (
        <>
            <Head title={`${talk.title} · ${talk.user.name}`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <button
                    type="button"
                    onClick={goBack}
                    aria-label="Back"
                    className="text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </button>
                <h1 className="truncate font-semibold">Talk</h1>
                {isOwner && (
                    <Form
                        {...TalkController.destroy.form(talk)}
                        className="ml-auto"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                disabled={processing}
                            >
                                Delete
                            </Button>
                        )}
                    </Form>
                )}
            </div>

            <section className="space-y-4 border-b px-5 py-6 sm:px-6">
                <div>
                    <p className="font-mono text-xs text-muted-foreground">
                        <Link
                            href={showUser(talk.user)}
                            className="hover:text-foreground hover:underline"
                        >
                            @{talk.user.username}
                        </Link>
                        {talk.event_name ? ` · ${talk.event_name}` : ''}
                    </p>
                    <h2 className="mt-1 text-xl font-semibold tracking-tight">
                        {talk.title}
                    </h2>
                </div>

                <p className="text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground">
                    {talk.description}
                </p>

                <div className="flex flex-wrap items-center gap-3 font-mono text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <CalendarDays className="size-3.5" />
                        <time dateTime={talk.presented_on}>
                            {talk.presented_on}
                        </time>
                    </span>
                    <a
                        href={talk.link_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 hover:text-foreground hover:underline"
                    >
                        <ExternalLink className="size-3.5" />
                        {talk.link_kind === 'recording'
                            ? 'Recording'
                            : 'Slides'}
                    </a>
                </div>
            </section>

            <div className="border-b px-5 py-3 font-mono text-xs text-muted-foreground sm:px-6">
                Discussion
            </div>

            <CommitCard
                commit={commit}
                initialReplies={replies}
                endsWithDot
                isFocused
            />
        </>
    );
}

Show.layout = AppLayout;
