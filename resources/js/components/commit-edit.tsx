// Lets authors fix the text of a fresh commit, and lets readers see what an edited commit said before.
import { router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { index } from '@/actions/App/Http/Controllers/CommitRevisionController';
import { CommitBody } from '@/components/commit-body';
import { MentionTextarea } from '@/components/mention-textarea';
import { SecretWarning } from '@/components/secret-warning';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useSecretWarning } from '@/hooks/use-secret-warning';
import { editableMinutes, maxRawBodyLength } from '@/lib/commit';
import { commitMutationOptions } from '@/lib/commit-mutation-options';
import { formatFullDateTime, isWithinSeconds } from '@/lib/utils';
import type { BodySegment, Commit } from '@/types';

type Version = {
    body_segments: BodySegment[];
    created_at: string;
    mentions: Commit['mentions'];
    is_original: boolean;
    review: 'pending' | 'rejected' | null;
};

/**
 * Mirrors App\Models\Commit::isEditable(); the server also caps how many times a commit is edited.
 */
export function isEditable(commit: Commit, now: Date): boolean {
    return (
        !commit.amendment_locked_at &&
        (commit.replies_count ?? 0) === 0 &&
        !commit.poll &&
        isWithinSeconds(commit.created_at, editableMinutes * 60, now)
    );
}

export function CommitAmendForm({
    commit,
    onAmended,
    onCancel,
}: {
    commit: Commit;
    onAmended: (amended: Partial<Commit>) => void;
    onCancel: () => void;
}) {
    const { component } = usePage();
    const [body, setBody] = useState(commit.body);
    const version = useRef(commit.version ?? commit.id);
    const [error, setError] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const positionedCaret = useRef(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const secrets = useSecretWarning(body);

    const save = () => {
        if (isSaving || !secrets.beforeSubmit()) return;
        if (!isWithinSeconds(commit.created_at, editableMinutes * 60)) {
            setError(
                'The 15-minute amendment window has closed. Your changes were not saved.',
            );
            return;
        }
        setIsSaving(true);
        setError(null);
        router.patch(
            CommitController.update.url(commit),
            { body, expected_version: version.current },
            {
                ...commitMutationOptions(component),
                onSuccess: (page) => {
                    if (page.flash.amended) {
                        onAmended(page.flash.amended);
                    }
                },
                onError: (errors) =>
                    setError(
                        errors.body ??
                            errors.expected_version ??
                            'fatal: could not amend commit',
                    ),
                onFinish: () => setIsSaving(false),
            },
        );
    };

    return (
        <form
            className="mt-1"
            onSubmit={(event) => {
                event.preventDefault();
                save();
            }}
            data-test="commit-edit-form"
        >
            <MentionTextarea
                ref={(textarea) => {
                    textareaRef.current = textarea;
                    if (textarea && !positionedCaret.current) {
                        textarea.setSelectionRange(
                            textarea.value.length,
                            textarea.value.length,
                        );
                        positionedCaret.current = true;
                    }
                }}
                value={body}
                onValueChange={setBody}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        onCancel();
                    } else if (
                        event.key === 'Enter' &&
                        (event.metaKey || event.ctrlKey)
                    ) {
                        event.preventDefault();
                        save();
                    }
                }}
                autoFocus
                rows={1}
                maxLength={maxRawBodyLength}
                aria-label="Commit text"
                aria-invalid={error ? true : undefined}
                className="block field-sizing-content max-h-[60dvh] min-h-10 w-full resize-none overflow-x-hidden overflow-y-auto rounded-md border bg-transparent px-2 py-1.5 text-base leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-[15px]"
            />
            <SecretWarning
                body={body}
                findings={secrets.findings}
                textarea={textareaRef}
                onChange={setBody}
                confirming={secrets.confirming}
                onConfirmingChange={secrets.setConfirming}
                onApprove={secrets.approve}
            />
            <div className="mt-2 flex flex-wrap items-center justify-end gap-2">
                {error && (
                    <p
                        role="alert"
                        className="mr-auto text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    disabled={isSaving}
                    className="min-w-20"
                    data-test="amend-commit-button"
                >
                    Amend
                </Button>
            </div>
        </form>
    );
}

/**
 * The "amended" mark next to the timestamp, which opens every version of the commit, newest first.
 */
export function CommitAmendedLabel({ commit }: { commit: Pick<Commit, 'id'> }) {
    const { auth } = usePage().props;
    const [versions, setVersions] = useState<Version[] | null>(null);
    const [failed, setFailed] = useState(false);

    // Loads again each time it opens, so a later edit shows up.
    const onOpenChange = async (open: boolean) => {
        if (!open) {
            return;
        }

        setVersions(null);
        setFailed(false);

        try {
            const response = await fetch(index.url(commit), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Unable to load amendments');
            setVersions(((await response.json()) as { data: Version[] }).data);
        } catch {
            setFailed(true);
        }
    };

    return (
        <Dialog onOpenChange={(open) => void onOpenChange(open)}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="shrink-0 font-mono text-[13px] text-muted-foreground hover:underline"
                    data-test="commit-edited"
                >
                    amended
                </button>
            </DialogTrigger>
            <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-lg">
                <DialogHeader className="text-left">
                    <DialogTitle className="text-base font-semibold">
                        Amendment history
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Earlier versions of this commit.
                    </DialogDescription>
                </DialogHeader>
                {versions === null && !failed && (
                    <p role="status" className="sr-only">
                        Loading amendments…
                    </p>
                )}
                {failed && (
                    <p
                        role="alert"
                        className="font-mono text-[13px] text-muted-foreground"
                    >
                        fatal: could not load amendments
                    </p>
                )}
                <ol className="divide-y" data-test="commit-versions">
                    {versions?.map((version, position) => (
                        <li
                            key={`${version.created_at}-${position}`}
                            className="py-3"
                        >
                            <p className="mb-1 font-mono text-[13px] text-muted-foreground">
                                <time dateTime={version.created_at}>
                                    {formatFullDateTime(
                                        version.created_at,
                                        auth.user?.timezone ?? undefined,
                                    )}
                                </time>
                                {position === 0 && ' · current'}
                                {version.is_original && ' · original'}
                                {version.review === 'pending' && ' · in review'}
                                {version.review === 'rejected' && ' · rejected'}
                            </p>
                            <CommitBody
                                segments={version.body_segments}
                                mentions={version.mentions}
                                className="text-[15px]"
                            />
                        </li>
                    ))}
                </ol>
            </DialogContent>
        </Dialog>
    );
}
