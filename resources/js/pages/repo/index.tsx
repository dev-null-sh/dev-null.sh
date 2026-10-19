import { Form, Head, Link } from '@inertiajs/react';
import { FolderGit2, FolderPlus } from 'lucide-react';
import RepoController from '@/actions/App/Http/Controllers/RepoController';
import { EmptyFeed } from '@/components/commit-feed';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { RepoListing } from '@/types';

export default function Index({ repos }: { repos: RepoListing[] }) {
    return (
        <>
            <Head title="Repos" />

            <header className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Repos</h1>
                <NewRepo />
            </header>

            <p className="border-b px-4 py-3 text-sm text-muted-foreground sm:px-6">
                A private log for a few people. Commits in a repo stay in the
                repo: they never show up in anyone's log or profile.
            </p>

            {repos.length === 0 ? (
                <div className="px-4 py-16 text-center sm:px-6">
                    <EmptyFeed command="fatal: not a git repository">
                        You aren't in any repos yet. Create one, then add people
                        who follow you.
                    </EmptyFeed>
                </div>
            ) : (
                <ul className="divide-y" aria-label="Your repos">
                    {repos.map((repo) => (
                        <li key={repo.id}>
                            <Link
                                href={RepoController.show(repo)}
                                className="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none sm:px-6"
                            >
                                <FolderGit2
                                    aria-hidden
                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="flex items-center gap-2">
                                        <span
                                            className="truncate font-medium"
                                            title={repo.name}
                                        >
                                            {repo.name}
                                        </span>
                                        {repo.pivot.role === 'maintainer' && (
                                            <span className="shrink-0 rounded-full border px-2 py-0.5 text-xs text-muted-foreground">
                                                Maintainer
                                            </span>
                                        )}
                                    </span>
                                    {repo.description && (
                                        <span
                                            className="mt-0.5 block truncate text-sm text-muted-foreground"
                                            title={repo.description}
                                        >
                                            {repo.description}
                                        </span>
                                    )}
                                </span>
                                <span className="shrink-0 text-sm text-muted-foreground tabular-nums">
                                    {repo.contributors_count}{' '}
                                    {repo.contributors_count === 1
                                        ? 'contributor'
                                        : 'contributors'}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

function NewRepo() {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="-mr-1"
                    data-test="new-repo"
                >
                    <FolderPlus className="size-4" />
                    New repo
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto bg-card motion-reduce:animate-none">
                <DialogHeader className="text-left">
                    <DialogTitle>New repo</DialogTitle>
                    <DialogDescription>
                        You maintain it. Only its contributors see what's
                        committed in it.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...RepoController.store.form()}
                    resetOnSuccess
                    className="grid gap-4"
                    data-test="create-repo-form"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <label
                                    htmlFor="repo-name"
                                    className="text-sm font-medium"
                                >
                                    Name
                                </label>
                                <input
                                    id="repo-name"
                                    name="name"
                                    required
                                    maxLength={50}
                                    autoComplete="off"
                                    placeholder="dev/null"
                                    disabled={processing}
                                    aria-invalid={!!errors.name}
                                    aria-describedby="repo-name-error"
                                    className="h-9 w-full rounded-md border bg-background px-3 text-base outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                                />
                                <InputError
                                    id="repo-name-error"
                                    message={errors.name}
                                />
                            </div>
                            <div className="grid gap-2">
                                <label
                                    htmlFor="repo-description"
                                    className="text-sm font-medium"
                                >
                                    Description{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </label>
                                <input
                                    id="repo-description"
                                    name="description"
                                    maxLength={160}
                                    autoComplete="off"
                                    disabled={processing}
                                    aria-invalid={!!errors.description}
                                    aria-describedby="repo-description-hint repo-description-error"
                                    className="h-9 w-full rounded-md border bg-background px-3 text-base outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                                />
                                <p
                                    id="repo-description-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    Up to 160 characters.
                                </p>
                                <InputError
                                    id="repo-description-error"
                                    message={errors.description}
                                />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating…' : 'Create repo'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Index.layout = AppLayout;
