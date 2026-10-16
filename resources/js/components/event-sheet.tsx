import { EventFormFields } from '@/components/event-form-fields';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { EventDraft } from '@/hooks/use-event-draft';

export function EventSheet({
    open,
    onOpenChange,
    draft,
    onChange,
    onRemove,
    errors,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    draft: EventDraft;
    onChange: (draft: EventDraft) => void;
    onRemove: () => void;
    errors: Record<string, string | undefined>;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="bottom" className="h-[100dvh] overflow-y-auto">
                <SheetHeader className="border-b">
                    <SheetTitle className="text-center">New event</SheetTitle>
                    <SheetDescription className="sr-only">
                        The event is committed together with your commit.
                    </SheetDescription>
                </SheetHeader>
                <div className="px-4 pb-6">
                    <InputError message={errors.event} />
                    <EventFormFields
                        draft={draft}
                        onChange={onChange}
                        errors={errors}
                    />
                    <div className="mt-5 flex gap-2">
                        <Button
                            variant="ghost"
                            className="h-11"
                            onClick={() => {
                                onRemove();
                                onOpenChange(false);
                            }}
                        >
                            Remove
                        </Button>
                        <Button
                            className="h-11 flex-1"
                            onClick={() => onOpenChange(false)}
                        >
                            Done
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
