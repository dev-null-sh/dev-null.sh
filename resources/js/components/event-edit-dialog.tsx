import { router } from '@inertiajs/react';
import { useState } from 'react';
import EventCancellationController from '@/actions/App/Http/Controllers/EventCancellationController';
import EventController from '@/actions/App/Http/Controllers/EventController';
import { EventFormFields } from '@/components/event-form-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { eventPayload } from '@/hooks/use-event-draft';
import type { EventDraft } from '@/hooks/use-event-draft';
import type { Commit, CommitEvent } from '@/types';

/**
 * The form edits wall-clock times in the host's own time zone, the way the event was created.
 */
function draftFrom(event: CommitEvent): EventDraft {
    const local = (iso: string) => {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: event.timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date(iso));
        const part = (type: string) =>
            parts.find((item) => item.type === type)?.value ?? '';

        return {
            date: `${part('year')}-${part('month')}-${part('day')}`,
            time: `${part('hour')}:${part('minute')}`,
        };
    };
    const starts = local(event.starts_at);
    const ends = local(event.ends_at);

    return {
        title: event.title,
        startsDate: starts.date,
        startsTime: starts.time,
        endsDate: ends.date,
        endsTime: ends.time,
        timezone: event.timezone,
        format: event.format,
        venue: event.venue ?? '',
        address: event.address ?? '',
        platform: event.platform ?? 'youtube',
        joinUrl: event.join_url ?? '',
        cover: null,
    };
}

export function EventEditDialog({
    commit,
    event,
    open,
    onOpenChange,
}: {
    commit: Commit;
    event: CommitEvent;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [draft, setDraft] = useState(() => draftFrom(event));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [isConfirmingCancel, setIsConfirmingCancel] = useState(false);

    const save = () => {
        setProcessing(true);
        router.visit(EventController.update(commit), {
            data: { event: eventPayload(draft, { withTitle: false }) },
            preserveScroll: true,
            onError: setErrors,
            onSuccess: () => onOpenChange(false),
            onFinish: () => setProcessing(false),
        });
    };

    const cancelEvent = () => {
        setProcessing(true);
        router.visit(EventCancellationController.store(commit), {
            preserveScroll: true,
            onError: setErrors,
            onSuccess: () => onOpenChange(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit event</DialogTitle>
                    <DialogDescription>
                        People going hear about a new time or place. The title
                        stays as committed.
                    </DialogDescription>
                </DialogHeader>
                <InputError message={errors.event} />
                <EventFormFields
                    draft={draft}
                    onChange={setDraft}
                    errors={errors}
                    titleLocked
                    coverLocked={commit.status !== 'pending'}
                    currentCoverUrl={event.cover?.url ?? null}
                />
                <DialogFooter className="flex-row flex-wrap items-center gap-2">
                    {isConfirmingCancel ? (
                        <div className="mr-auto flex items-center gap-2 rounded-md bg-destructive/8 px-3 py-2 text-sm">
                            Cancel this event? There's no undo.
                            <Button
                                variant="destructive"
                                size="sm"
                                disabled={processing}
                                onClick={cancelEvent}
                            >
                                Cancel event
                            </Button>
                        </div>
                    ) : (
                        <Button
                            variant="ghost"
                            className="mr-auto text-destructive"
                            onClick={() => setIsConfirmingCancel(true)}
                        >
                            Cancel event…
                        </Button>
                    )}
                    <Button disabled={processing} onClick={save}>
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
