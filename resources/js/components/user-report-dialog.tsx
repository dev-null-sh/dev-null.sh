import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/UserReportController';
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
} from '@/components/ui/dialog';
import { reportReasons } from '@/lib/report-reasons';
import type { User } from '@/types';

/**
 * Reports a person, from their profile, a private chat or an org's member list. It's about them, so it never carries anything from a chat.
 */
export function UserReportDialog({
    user,
    open,
    onOpenChange,
    onCloseAutoFocus,
}: {
    user: Pick<User, 'handle'>;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCloseAutoFocus?: () => void;
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
            <DialogContent
                className="max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] overflow-y-auto bg-card motion-reduce:animate-none"
                onClick={(event) => event.stopPropagation()}
                onCloseAutoFocus={onCloseAutoFocus}
            >
                <ReportForm
                    user={user}
                    onSendingChange={setIsSending}
                    onDone={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

/**
 * Mounted only while its dialog shows, so every chat row stays light and the form starts blank each time.
 */
function ReportForm({
    user,
    onSendingChange,
    onDone,
}: {
    user: Pick<User, 'handle'>;
    onSendingChange: (isSending: boolean) => void;
    onDone: () => void;
}) {
    const [failure, setFailure] = useState('');
    const id = useId();
    const form = useForm({ reason: '', details: '' });

    return (
        <>
            <DialogHeader className="text-left">
                <DialogTitle className="truncate">
                    Report @{user.handle}
                </DialogTitle>
                <DialogDescription>
                    Your report is private and they won't be told. Moderators
                    see your reason and details, not your messages. Reporting
                    doesn't block them.
                </DialogDescription>
            </DialogHeader>
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (form.processing) return;
                    setFailure('');
                    form.submit(store(user), {
                        preserveScroll: true,
                        preserveState: true,
                        onStart: () => onSendingChange(true),
                        onFinish: () => onSendingChange(false),
                        onSuccess: onDone,
                        onHttpException: (response) => {
                            setFailure(
                                response.status === 429
                                    ? 'Too many reports. Please wait a minute and try again.'
                                    : response.status === 404
                                      ? 'This profile is no longer available to report.'
                                      : 'Could not send your report. Please try again.',
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
                <fieldset className="grid gap-2" disabled={form.processing}>
                    <legend className="mb-2 text-sm font-medium">
                        Why are you reporting this person?
                    </legend>
                    {reportReasons.map(([value, label]) => (
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
                                className="size-4 shrink-0 accent-primary"
                            />
                            {label}
                        </label>
                    ))}
                    <InputError message={form.errors.reason} />
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
                {failure && (
                    <p role="alert" className="text-sm text-destructive">
                        {failure}
                    </p>
                )}
                <DialogFooter>
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
                        disabled={form.processing || !form.data.reason}
                    >
                        {form.processing ? 'Sending…' : 'Send report'}
                    </Button>
                </DialogFooter>
            </form>
        </>
    );
}
