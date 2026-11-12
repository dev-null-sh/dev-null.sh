import { Ship, X } from 'lucide-react';
import type { KeyboardEvent, SyntheticEvent } from 'react';
import { useSharedState } from '@/hooks/use-shared-state';
import { shipitDraft } from '@/lib/shipit';
import { readStorage, writeStorage } from '@/lib/storage';

export function useComposerShipit({
    body,
    updateBody,
    stashKey,
    enabled,
    onLaunch,
}: {
    body: string;
    updateBody: (body: string) => void;
    stashKey: string;
    enabled: boolean;
    onLaunch: () => void;
}) {
    const key = `${stashKey}:launch`;
    const [selected, setSelected] = useSharedState(
        key,
        readStorage(key) === '1',
    );
    const isLaunch = enabled && selected;
    const draft = enabled ? shipitDraft(body) : null;
    const select = (value: boolean) => {
        writeStorage(key, value ? '1' : '');
        setSelected(value);
    };
    const launch = (value: string): boolean => {
        const remaining = enabled ? shipitDraft(value) : null;
        if (remaining === null) return false;

        updateBody(remaining);
        select(true);
        onLaunch();
        return true;
    };

    return {
        isLaunch,
        isCommand: draft !== null,
        reset: () => select(false),
        change: (value: string) => value.endsWith('\n') && launch(value),
        handleKeyDown: (event: KeyboardEvent<HTMLTextAreaElement>) => {
            if (
                event.key !== 'Enter' ||
                event.nativeEvent.isComposing ||
                draft === null ||
                (!event.ctrlKey &&
                    !event.metaKey &&
                    (event.currentTarget.selectionStart !== body.length ||
                        event.currentTarget.selectionEnd !== body.length))
            )
                return false;

            event.preventDefault();
            event.stopPropagation();
            launch(body);
            return true;
        },
        handleSubmit: (event: SyntheticEvent) => {
            if (draft === null) return;
            event.preventDefault();
            event.stopPropagation();
            launch(body);
        },
    };
}

export function ComposerShipit({ onCancel }: { onCancel: () => void }) {
    return (
        <div
            className="my-2 flex items-center gap-3 rounded-lg border border-primary/20 bg-primary-soft px-3 py-2.5"
            data-test="launch-composer-mode"
        >
            <Ship className="size-5 shrink-0 text-primary" aria-hidden />
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">Ready to launch</p>
                <p className="text-xs text-muted-foreground">
                    Share what you shipped. Everyone can come aboard.
                </p>
            </div>
            <button
                type="button"
                onClick={onCancel}
                aria-label="Cancel launch, keep draft"
                className="rounded p-1.5 text-muted-foreground hover:bg-primary/10 focus-visible:outline-ring"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
