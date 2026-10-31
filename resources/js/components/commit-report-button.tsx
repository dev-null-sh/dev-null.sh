import { useForm, usePage } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import type { ReactNode } from 'react';
import { useId, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/CommitReportController';
import {
    ImageDropOverlay,
    ImagePickerButton,
    ImagePickerPreviews,
    imageErrorFrom,
    useImagePreviewCleanup,
} from '@/components/commit-image-picker';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import type { Commit } from '@/types';

const reasons = [
    ['spam', 'Spam or misleading links'],
    ['harassment', 'Harassment or hate'],
    ['unsafe', 'Harmful or private information'],
    ['other', 'Something else'],
];

export function useCanReport(commit: Commit): boolean {
    const { auth } = usePage().props;

    return (
        auth.user !== null &&
        commit.status === 'approved' &&
        commit.user_id !== auth.user.id
    );
}

export function CommitReportButton({ commit }: { commit: Commit }) {
    const [open, setOpen] = useState(false);

    if (!useCanReport(commit)) {
        return null;
    }

    return (
        <CommitReportDialog commit={commit} open={open} onOpenChange={setOpen}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DialogTrigger asChild>
                        <button
                            type="button"
                            className="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label="Report commit"
                        >
                            <Flag aria-hidden className="size-4" />
                        </button>
                    </DialogTrigger>
                </TooltipTrigger>
                <TooltipContent>Report commit</TooltipContent>
            </Tooltip>
        </CommitReportDialog>
    );
}

export function CommitReportDialog({
    commit,
    open,
    onOpenChange,
    children,
}: {
    commit: Commit;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    children?: ReactNode;
}) {
    const [isSending, setIsSending] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!isSending) {
                    onOpenChange(next);
                }
            }}
        >
            {children}
            <DialogContent className="max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] overflow-y-auto bg-card motion-reduce:animate-none">
                <ReportForm
                    commit={commit}
                    onSendingChange={setIsSending}
                    onDone={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

/**
 * The report itself, mounted only while its dialog shows: every card has a report dialog, and most never open. It
 * starts blank each time the dialog opens.
 */
function ReportForm({
    commit,
    onSendingChange,
    onDone,
}: {
    commit: Commit;
    onSendingChange: (isSending: boolean) => void;
    onDone: () => void;
}) {
    const [failure, setFailure] = useState('');
    const commitNotFound = useCommitNotFound();
    const id = useId();
    const form = useForm({
        reason: '',
        details: '',
        images: [] as File[],
        image_descriptions: [] as string[],
    });
    useImagePreviewCleanup(form.data.images);
    const attachments = useImageAttachments(
        form.data.images,
        form.data.image_descriptions,
        (images, descriptions) => {
            form.setData((data) => ({
                ...data,
                images,
                image_descriptions: descriptions,
            }));
        },
        `report:${commit.id}:${id}`,
        form.processing,
    );

    return (
        <>
            <DialogHeader className="sticky -top-6 z-10 -mx-6 -mt-6 border-b bg-card px-6 pt-6 pb-4 text-left">
                <DialogTitle>Report commit</DialogTitle>
                <DialogDescription>
                    Your report is private. Attachments are visible only to you
                    and moderators. Reporting does not hide this commit.
                </DialogDescription>
            </DialogHeader>
            <p className="line-clamp-3 border-y py-3 text-sm leading-relaxed wrap-anywhere whitespace-pre-wrap">
                {commit.body}
            </p>
            <form
                {...attachments.dropzone}
                className="relative grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (form.processing || attachments.isReading()) return;
                    setFailure('');
                    form.submit(store(commit), {
                        preserveScroll: true,
                        onStart: () => onSendingChange(true),
                        onFinish: () => onSendingChange(false),
                        onSuccess: () => {
                            onDone();
                            attachments.clear();
                            form.reset();
                        },
                        onHttpException: (response) => {
                            if (commitNotFound(commit.id, response)) {
                                onDone();

                                return false;
                            }

                            setFailure(
                                response.status === 429
                                    ? 'Too many reports. Please wait a minute and try again.'
                                    : 'This commit is no longer available to report.',
                            );
                            return false;
                        },
                        onNetworkError: () => {
                            setFailure(
                                'Could not send your report. Check your connection and try again.',
                            );
                            return false;
                        },
                    });
                }}
            >
                <fieldset
                    className="grid gap-2"
                    disabled={form.processing}
                    aria-describedby={
                        form.errors.reason ? `${id}-reason-error` : undefined
                    }
                >
                    <legend className="mb-2 text-sm font-medium">
                        Why are you reporting this commit?
                    </legend>
                    {reasons.map(([value, label]) => (
                        <label
                            key={value}
                            className="flex min-h-9 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm has-checked:border-primary has-checked:bg-primary-soft"
                        >
                            <input
                                type="radio"
                                name={`${id}-reason`}
                                value={value}
                                checked={form.data.reason === value}
                                onChange={() => form.setData('reason', value)}
                                required
                                className="size-4 accent-primary"
                            />
                            {label}
                        </label>
                    ))}
                    <InputError
                        id={`${id}-reason-error`}
                        message={form.errors.reason}
                    />
                </fieldset>
                <div className="grid gap-2">
                    <label
                        htmlFor={`${id}-details`}
                        className="text-sm font-medium"
                    >
                        Details{' '}
                        <span className="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </label>
                    <textarea
                        id={`${id}-details`}
                        value={form.data.details}
                        onChange={(event) =>
                            form.setData('details', event.target.value)
                        }
                        onPaste={attachments.onPaste}
                        maxLength={500}
                        rows={3}
                        disabled={form.processing}
                        aria-invalid={!!form.errors.details}
                        aria-describedby={`${id}-details-hint ${id}-details-error`}
                        className="w-full resize-y rounded-md border bg-background px-3 py-2 text-base outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
                    />
                    <p
                        id={`${id}-details-hint`}
                        className="text-xs text-muted-foreground"
                    >
                        Add context for moderators. Up to 500 characters.
                    </p>
                    <InputError
                        id={`${id}-details-error`}
                        message={form.errors.details}
                    />
                </div>
                {attachments.isDraggingFiles && <ImageDropOverlay />}
                <fieldset disabled={form.processing} className="grid gap-2">
                    <ImagePickerButton
                        count={attachments.images.length}
                        disabled={form.processing}
                        reading={attachments.reading}
                        onPick={attachments.pick}
                    />
                    <ImagePickerPreviews
                        images={attachments.images}
                        descriptions={attachments.descriptions}
                        onDescribe={attachments.describe}
                        onRemove={attachments.remove}
                    />
                    <InputError
                        message={
                            attachments.error ?? imageErrorFrom(form.errors)
                        }
                    />
                </fieldset>
                {failure && (
                    <p role="alert" className="text-sm text-destructive">
                        {failure}
                    </p>
                )}
                <DialogFooter className="sticky -bottom-6 z-10 -mx-6 -mb-6 border-t bg-card px-6 pt-4 pb-6">
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
                            attachments.reading ||
                            !form.data.reason
                        }
                    >
                        {form.processing ? 'Sending…' : 'Send report'}
                    </Button>
                </DialogFooter>
            </form>
        </>
    );
}
