import { Form } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useId, useState } from 'react';
import RejectionReasonController from '@/actions/App/Http/Controllers/RejectionReasonController';
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
import type { RejectionReason } from '@/types';

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
                    className="block min-h-20 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:border-destructive"
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
        );
    }

    return (
        <div className="flex items-start gap-3 py-3">
            {shortcut !== undefined && (
                <kbd
                    aria-label={`Shortcut ${shortcut}`}
                    className="mt-0.5 min-w-5 rounded border border-b-2 bg-muted px-1 text-center font-mono text-xs leading-4"
                >
                    {shortcut}
                </kbd>
            )}
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{reason.title}</p>
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

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                onOpenChange(isOpen);
                setIsAdding(false);
            }}
        >
            <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>Rejection reasons</DialogTitle>
                <DialogDescription>
                    Shared by every admin. Pick one in the queue to prefill the
                    reason, then edit it before rejecting.
                </DialogDescription>

                {reasons.length > 0 && (
                    <div className="divide-y border-y">
                        {reasons.map((reason, index) => (
                            <RejectionReasonItem
                                key={reason.id}
                                reason={reason}
                                shortcut={index < 9 ? index + 1 : undefined}
                            />
                        ))}
                    </div>
                )}

                {isAdding ? (
                    <Form
                        {...RejectionReasonController.store.form()}
                        options={{ preserveScroll: true, preserveState: true }}
                        onSuccess={() => setIsAdding(false)}
                        className="space-y-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <RejectionReasonFields errors={errors} />
                                <div className="flex justify-end gap-2">
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
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Add reason
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        className="self-start"
                        onClick={() => setIsAdding(true)}
                    >
                        <Plus />
                        New reason
                    </Button>
                )}
            </DialogContent>
        </Dialog>
    );
}
