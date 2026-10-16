import { CalendarDays, X } from 'lucide-react';
import { EventFormFields } from '@/components/event-form-fields';
import InputError from '@/components/input-error';
import type { EventDraft } from '@/hooks/use-event-draft';

export function EventEditor({
    draft,
    onChange,
    onRemove,
    errors,
}: {
    draft: EventDraft;
    onChange: (draft: EventDraft) => void;
    onRemove: () => void;
    errors: Record<string, string | undefined>;
}) {
    return (
        <section
            aria-label="Event"
            className="mt-2 flex flex-col gap-3 rounded-lg border p-3.5"
            data-test="event-editor"
        >
            <div className="flex items-center gap-2">
                <CalendarDays className="size-4 text-muted-foreground" />
                <span className="text-sm font-semibold">Event</span>
                <button
                    type="button"
                    aria-label="Remove event"
                    onClick={onRemove}
                    className="ml-auto rounded-md p-1.5 text-muted-foreground hover:bg-accent"
                >
                    <X className="size-4" />
                </button>
            </div>
            <InputError message={errors.event} />
            <EventFormFields
                draft={draft}
                onChange={onChange}
                errors={errors}
            />
        </section>
    );
}
