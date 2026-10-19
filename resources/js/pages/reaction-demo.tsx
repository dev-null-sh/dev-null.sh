import { Head, Link, usePoll } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import { CommitCard } from '@/components/commit-card';
import type { RepositoryMirrorSummary } from '@/components/repository-mirror-setting';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { Commit } from '@/types';

type Event = {
    id: string;
    repository: string;
    payload: {
        type: string;
        actor: { github_id: string };
        commit: { id: string };
        reaction?: { emoji: string; visibility: string };
    };
};

export default function ReactionDemo({
    commit,
    mirrors,
    events,
    workflow,
}: {
    commit: Commit;
    mirrors: RepositoryMirrorSummary[];
    events: Event[];
    workflow: string;
}) {
    usePoll(2000, { only: ['events', 'mirrors'] });
    return (
        <TooltipProvider>
            <Head title="Emoji → your repository → Actions" />
            <main className="mx-auto max-w-5xl px-5 py-10 sm:px-8 sm:py-14">
                <nav className="mb-10 flex items-center justify-between border-b pb-4 text-sm">
                    <span className="font-mono font-semibold">/dev/null</span>
                    <Link
                        href="/settings/profile#repository"
                        className="text-primary underline underline-offset-4"
                    >
                        Repository settings
                    </Link>
                </nav>
                <h1 className="max-w-2xl text-3xl leading-tight font-semibold tracking-tight sm:text-4xl">
                    Slack-style reacjis that can trigger Actions workflows
                </h1>
                <p className="mt-4 max-w-2xl text-base leading-relaxed text-muted-foreground">
                    React publicly, keep it in your own repo, or do both. Your
                    commits and reactions become JSON files; GitHub Actions
                    takes it from there.
                </p>
                <p className="mt-3 text-sm text-muted-foreground">
                    Local prototype with synthetic accounts. Nothing is sent to
                    GitHub.
                </p>
                <div className="mt-10 grid items-start gap-10 lg:grid-cols-2">
                    <section className="min-w-0">
                        <h2 className="mb-4 text-base font-semibold">
                            Try a reaction
                        </h2>
                        <div className="overflow-hidden rounded-lg border">
                            <CommitCard commit={commit} canDelete={false} />
                        </div>
                        <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
                            Open the smiley to add more reactions. Search for
                            any emoji and choose where it goes. Try 🚀 with{' '}
                            <strong className="font-medium text-foreground">
                                only your repo checked
                            </strong>
                            : the public count stays unchanged.
                        </p>
                        <h2 className="mt-8 text-base font-semibold">
                            Your destinations
                        </h2>
                        {mirrors.map((mirror) => (
                            <p
                                key={mirror.id}
                                className="mt-2 flex items-center gap-2 font-mono text-sm break-all"
                            >
                                {mirror.repository}
                                {mirror.private && (
                                    <LockKeyhole
                                        aria-label="Private repository"
                                        className="size-3.5 shrink-0"
                                    />
                                )}
                            </p>
                        ))}
                        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                            Check Public to show your reaction on dev-null.
                            Check repositories to save copies there. With Public
                            unchecked, your reaction does not affect dev-null’s
                            stars, ranking or notifications.
                        </p>
                    </section>
                    <section
                        className="min-w-0"
                        aria-label="Repository activity"
                    >
                        <div className="flex items-center justify-between gap-2 border-b pb-3">
                            <h2 className="text-base font-semibold">
                                Repository activity
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                Local outbox · {events.length}
                            </span>
                        </div>
                        {events.length === 0 ? (
                            <p className="py-8 text-sm leading-relaxed text-muted-foreground">
                                Nothing queued yet. Check a repository and
                                choose an emoji to see its JSON record.
                            </p>
                        ) : (
                            <ul className="divide-y" aria-live="polite">
                                {events.map(({ id, repository, payload }) => (
                                    <li key={id} className="py-4">
                                        <p className="flex items-center gap-2 text-sm">
                                            <span className="text-xl">
                                                {payload.reaction?.emoji ??
                                                    '📄'}
                                            </span>
                                            <span className="font-medium">
                                                {payload.type}
                                            </span>
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                Queued locally
                                            </span>
                                        </p>
                                        <p className="mt-2 font-mono text-xs break-all text-muted-foreground">
                                            {repository}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {payload.reaction?.visibility ===
                                            'repository'
                                                ? 'Hidden on dev-null'
                                                : 'Public on dev-null'}{' '}
                                            ·{' '}
                                            {payload.type ===
                                                'reaction.saved' &&
                                            payload.reaction?.emoji === '🚀'
                                                ? 'Matches the example Actions job'
                                                : 'No match for the example’s 🚀 rule'}
                                        </p>
                                        <details className="mt-2 text-xs">
                                            <summary className="cursor-pointer text-primary">
                                                View JSON file
                                            </summary>
                                            <pre className="mt-2 overflow-x-auto rounded-md bg-muted p-3 text-xs">
                                                {JSON.stringify(
                                                    {
                                                        event_id: id,
                                                        ...payload,
                                                    },
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        </details>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
                <section className="mt-12 border-t pt-6">
                    <h2 className="text-lg font-semibold">
                        A regular GitHub Actions workflow
                    </h2>
                    <p className="mt-2 max-w-3xl text-sm leading-relaxed text-muted-foreground">
                        Put this file in your repository’s{' '}
                        <code>.github/workflows/</code> directory. A mirrored 🚀
                        reaction matches the job below. Replace its example step
                        with your own automation. The local preview only shows
                        the match; it does not run Actions.
                    </p>
                    <details className="mt-4">
                        <summary className="cursor-pointer text-sm text-primary">
                            View example workflow
                        </summary>
                        <pre className="mt-4 overflow-auto rounded-lg bg-muted p-4 text-xs leading-relaxed">
                            {workflow}
                        </pre>
                    </details>
                </section>
            </main>
        </TooltipProvider>
    );
}
