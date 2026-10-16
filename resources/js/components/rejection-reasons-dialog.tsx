import { Form } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useId, useState } from 'react';
import RejectionReasonController from '@/actions/App/Http/Controllers/RejectionReasonController';
import { EmptyFeed } from '@/components/commit-feed';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { RejectionReason } from '@/types';

const scrollIntoViewOnMount = (node: HTMLElement | null) =>
    node?.scrollIntoView({ block: 'nearest' });

function RejectionReasonFields({
    reason,
    errors,
}: {
    reason?: RejectionReason;
    errors: Partial<Record<'title' | 'body', string>>;
}) {
    const id = useId();

    return (
        <div className="space-y-3">
            <div className="space-y-1.5">
                <Label htmlFor={`${id}-title`}>Title</Label>
                <Input
                    id={`${id}-title`}
                    name="title"
                    defaultValue={reason?.title}
                    maxLength={40}
                    required
                    autoFocus
                    aria-invalid={Boolean(errors.title)}
                    aria-describedby={
                        errors.title ? `${id}-title-error` : undefined
                    }
                />
                <InputError id={`${id}-title-error`} message={errors.title} />
            </div>
            <div className="space-y-1.5">
                <Label htmlFor={`${id}-body`}>Reason</Label>
                <textarea
                    id={`${id}-body`}
                    name="body"
                    defaultValue={reason?.body}
                    maxLength={280}
                    required
                    aria-invalid={Boolean(errors.body)}
                    aria-describedby={
                        errors.body ? `${id}-body-error` : undefined
                    }
                    className="block min-h-20 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-[15px]"
                />
                <InputError id={`${id}-body-error`} message={errors.body} />
            </div>
        </div>
    );
}

function RejectionReasonItem({
    reason,
    shortcut,
}: {
    reason: RejectionReason;
    shortcut?: number;
}) {
    const [isEditing, setIsEditing] = useState(false);

    if (isEditing) {
        return (
            <div ref={scrollIntoViewOnMount}>
                <Form
                    {...RejectionReasonController.update.form(reason)}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={() => setIsEditing(false)}
                    className="space-y-3 py-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <RejectionReasonFields
                                reason={reason}
                                errors={errors}
                            />
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setIsEditing(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        );
    }

    return (
        <div className="flex items-start gap-3 py-3">
            {shortcut === undefined ? (
                <span aria-hidden className="w-5 shrink-0" />
            ) : (
                <kbd
                    aria-label={`Shortcut ${shortcut}`}
                    className="mt-0.5 w-5 shrink-0 rounded border border-b-2 bg-muted text-center font-mono text-xs leading-4"
                >
                    {shortcut}
                </kbd>
            )}
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium wrap-anywhere">
                    {reason.title}
                </p>
                <p className="mt-0.5 text-sm wrap-anywhere whitespace-pre-wrap text-muted-foreground">
                    {reason.body}
                </p>
            </div>
            <div className="-mt-1 -mr-2 flex shrink-0">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={`Edit ${reason.title}`}
                    onClick={() => setIsEditing(true)}
                >
                    <Pencil />
                </Button>
                <Form
                    {...RejectionReasonController.destroy.form(reason)}
                    options={{ preserveScroll: true, preserveState: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="ghost"
                            size="icon"
                            className="size-8 hover:bg-destructive/10 hover:text-destructive"
                            aria-label={`Delete ${reason.title}`}
                            disabled={processing}
                        >
                            <Trash2 />
                        </Button>
                    )}
                </Form>
            </div>
        </div>
    );
}

export function RejectionReasonsDialog({
    reasons,
    open,
    onOpenChange,
}: {
    reasons: RejectionReason[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [isAdding, setIsAdding] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const formId = useId();

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                onOpenChange(isOpen);
                setIsAdding(false);
            }}
        >
            <DialogContent className="top-[max(1rem,8svh)] flex max-h-[calc(100svh-max(1rem,8svh)-1rem)] translate-y-0 flex-col gap-0 overflow-hidden p-0 sm:max-w-lg">
                <div className="space-y-2 border-b px-6 pt-6 pb-4">
                    <DialogTitle className="pr-8">
                        Rejection reasons
                    </DialogTitle>
                    <DialogDescription>
                        Shared by every admin. Pick one in the queue to prefill
                        the reason, then edit it before rejecting.
                    </DialogDescription>
                </div>

                <div
                    className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6"
                    data-test="rejection-reasons-list"
                >
                    {reasons.length > 0 ? (
                        <div className="divide-y">
                            {reasons.map((reason, index) => (
                                <RejectionReasonItem
                                    key={reason.id}
                                    reason={reason}
                                    shortcut={index < 9 ? index + 1 : undefined}
                                />
                            ))}
                        </div>
                    ) : (
                        !isAdding && (
                            <div className="py-10 text-center">
                                <EmptyFeed command="$ ls reasons/">
                                    No reasons yet. Add the ones you keep typing
                                    out.
                                </EmptyFeed>
                            </div>
                        )
                    )}

                    {isAdding && (
                        <div
                            ref={scrollIntoViewOnMount}
                            className={cn(
                                'py-4',
                                reasons.length > 0 && 'border-t',
                            )}
                        >
                            <Form
                                {...RejectionReasonController.store.form()}
                                id={formId}
                                options={{
                                    preserveScroll: true,
                                    preserveState: true,
                                }}
                                onStart={() => setIsSaving(true)}
                                onFinish={() => setIsSaving(false)}
                                onSuccess={() => setIsAdding(false)}
                            >
                                {({ errors }) => (
                                    <RejectionReasonFields errors={errors} />
                                )}
                            </Form>
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-2 border-t px-6 py-4">
                    {isAdding ? (
                        <>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setIsAdding(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                form={formId}
                                size="sm"
                                disabled={isSaving}
                            >
                                Add reason
                            </Button>
                        </>
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setIsAdding(true)}
                        >
                            <Plus />
                            New reason
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
