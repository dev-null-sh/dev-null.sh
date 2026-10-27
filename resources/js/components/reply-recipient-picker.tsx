import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ReplyParticipant = { id: string; handle: string };

export function ReplyRecipientPicker({
    participants,
    authorId,
    excludedIds,
    onExcludedIdsChange,
}: {
    participants: ReplyParticipant[];
    authorId: string;
    excludedIds: string[];
    onExcludedIdsChange: (ids: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const selected = participants.filter(({ id }) => !excludedIds.includes(id));

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="mb-2 block px-1 text-left text-sm text-muted-foreground hover:underline"
                aria-haspopup="dialog"
            >
                Replying to{' '}
                <span className="text-primary">
                    {selected
                        .map(
                            ({ handle }, index) =>
                                `${index > 0 ? (index === selected.length - 1 ? ' and ' : ', ') : ''}@${handle}`,
                        )
                        .join('')}
                </span>
            </button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Replying to</DialogTitle>
                        <DialogDescription>
                            Choose who to include. The commit author stays
                            selected. Anyone you mention in your reply will also
                            be notified.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="divide-y">
                        {participants.map(({ id, handle }) => (
                            <label
                                key={id}
                                className="flex cursor-pointer items-center justify-between gap-4 py-4"
                            >
                                <span className="min-w-0 font-medium break-all">
                                    @{handle}
                                    {id === authorId && (
                                        <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                            Commit author
                                        </span>
                                    )}
                                </span>
                                <Checkbox
                                    checked={!excludedIds.includes(id)}
                                    disabled={id === authorId}
                                    onCheckedChange={(checked) =>
                                        onExcludedIdsChange(
                                            checked === true
                                                ? excludedIds.filter(
                                                      (excluded) =>
                                                          excluded !== id,
                                                  )
                                                : [...excludedIds, id],
                                        )
                                    }
                                    aria-label={`Include @${handle}`}
                                    className="size-5"
                                />
                            </label>
                        ))}
                    </div>
                    <Button type="button" onClick={() => setOpen(false)}>
                        Done
                    </Button>
                </DialogContent>
            </Dialog>
        </>
    );
}
