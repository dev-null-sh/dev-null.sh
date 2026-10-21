import { useForm, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/CommitController';
import InputError from '@/components/input-error';
import { SecretWarning } from '@/components/secret-warning';
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
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { useSecretWarning } from '@/hooks/use-secret-warning';
import {
    bodyCharacterCount,
    isSavedAs,
    maxBodyLength,
    maxRawBodyLength,
    minBodyLength,
} from '@/lib/commit';
import { isRejectedSubmission } from '@/lib/utils';
import type { Commit } from '@/types';

export function CommitEditButton({ commit }: { commit: Commit }) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [session, setSession] = useState(0);

    if (!auth.user || auth.user.id !== commit.user_id) return null;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (processing) return;
                if (next) setSession((value) => value + 1);
                setOpen(next);
            }}
        >
            <DialogTrigger asChild>
                <button
                    type="button"
                    aria-label="Edit commit"
                    title="Edit commit"
                    className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <Pencil aria-hidden className="size-4" />
                </button>
            </DialogTrigger>
            {session > 0 && (
                <CommitEditor
                    key={session}
                    commit={commit}
                    onClose={() => setOpen(false)}
                    onProcessingChange={setProcessing}
                />
            )}
        </Dialog>
    );
}

function CommitEditor({
    commit,
    onClose,
    onProcessingChange,
}: {
    commit: Commit;
    onClose: () => void;
    onProcessingChange: (processing: boolean) => void;
}) {
    const form = useForm({ body: commit.body });
    const id = useId();
    const textarea = useRef<HTMLTextAreaElement>(null);
    const secrets = useSecretWarning(form.data.body);
    const commitNotFound = useCommitNotFound();
    const [failure, setFailure] = useState('');
    const count = bodyCharacterCount(form.data.body);
    const valid =
        count <= maxBodyLength &&
        (count >= minBodyLength || !!commit.images?.length);

    return (
        <DialogContent
            className="max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] overflow-y-auto bg-card motion-reduce:animate-none"
            onEscapeKeyDown={(event) => {
                if (form.processing) event.preventDefault();
            }}
            onInteractOutside={(event) => {
                if (form.processing) event.preventDefault();
            }}
        >
            <DialogHeader className="sticky top-0 z-10 bg-card text-left">
                <DialogTitle>Edit commit</DialogTitle>
                <DialogDescription>
                    Update your text. Attachments and polls stay as they are.
                    Changes may need review before they appear publicly.
                </DialogDescription>
            </DialogHeader>
            <form
                className="grid gap-4"
                aria-busy={form.processing}
                onSubmit={(event) => {
                    event.preventDefault();
                    if (form.processing || !valid || !secrets.beforeSubmit())
                        return;
                    setFailure('');
                    form.submit(update(commit), {
                        onStart: () => onProcessingChange(true),
                        onFinish: () => onProcessingChange(false),
                        preserveScroll: true,
                        onSuccess: (page) => {
                            if (!isRejectedSubmission(page.flash)) onClose();
                        },
                        onHttpException: (response) => {
                            if (commitNotFound(commit.id, response)) {
                                onClose();
                                return false;
                            }
                            setFailure(
                                response.status === 429
                                    ? 'Too many changes. Please wait a minute and try again.'
                                    : 'Could not update this commit. Please try again.',
                            );
                            return false;
                        },
                        onNetworkError: () => {
                            setFailure(
                                'Could not save your changes. Check your connection and try again.',
                            );
                            return false;
                        },
                    });
                }}
            >
                <div className="grid gap-2">
                    <label htmlFor={id} className="text-sm font-medium">
                        Commit text
                    </label>
                    <textarea
                        ref={textarea}
                        id={id}
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        onKeyDown={(event) => {
                            if (
                                (event.metaKey || event.ctrlKey) &&
                                event.key === 'Enter'
                            ) {
                                event.preventDefault();
                                event.currentTarget.form?.requestSubmit();
                            }
                        }}
                        rows={8}
                        maxLength={maxRawBodyLength}
                        disabled={form.processing}
                        aria-invalid={!!form.errors.body}
                        aria-describedby={`${id}-error ${id}-count`}
                        className="w-full resize-y overflow-x-hidden rounded-md border bg-background px-3 py-2 text-base outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-[15px]"
                    />
                    <p
                        id={`${id}-count`}
                        className="text-right font-mono text-xs text-muted-foreground tabular-nums"
                    >
                        {count} / {maxBodyLength}
                    </p>
                    <InputError id={`${id}-error`} message={form.errors.body} />
                    <SecretWarning
                        body={form.data.body}
                        findings={secrets.findings}
                        textarea={textarea}
                        onChange={(body) => form.setData('body', body)}
                        confirming={secrets.confirming}
                        onConfirmingChange={secrets.setConfirming}
                        onApprove={secrets.approve}
                    />
                </div>
                <p role="alert" className="min-h-5 text-sm text-destructive">
                    {failure}
                </p>
                <DialogFooter className="sticky bottom-0 bg-card pt-2">
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            !valid ||
                            isSavedAs(form.data.body, commit.body)
                        }
                    >
                        Save changes
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
}
