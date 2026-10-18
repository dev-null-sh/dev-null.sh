import { useRef } from 'react';
import { CharacterShortcutsPreference } from '@/components/character-shortcuts-preference';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCharacterShortcuts } from '@/hooks/use-character-shortcuts';
import type { KeyLabels } from '@/lib/keyboard';
import { useKeyLabels } from '@/lib/keyboard';

type Shortcut = { keys: string[]; label: string; characterOnly?: boolean };

const shortcuts = (keys: KeyLabels): Shortcut[] => [
    { keys: ['j'], label: 'Next commit', characterOnly: true },
    { keys: ['k'], label: 'Previous commit', characterOnly: true },
    {
        keys: ['y'],
        label: 'Copy link to the focused commit',
        characterOnly: true,
    },
    { keys: ['↵'], label: 'Open the focused commit' },
    { keys: ['r'], label: 'Reply to commit', characterOnly: true },
    { keys: ['q'], label: 'Quote commit', characterOnly: true },
    { keys: ['o'], label: 'Show or hide replies', characterOnly: true },
    { keys: ['c'], label: 'Write a commit', characterOnly: true },
    { keys: [keys.mod, 'K'], label: 'Search developers and commits' },
    {
        keys: ['/'],
        label: 'Search developers and commits',
        characterOnly: true,
    },
    { keys: [keys.mod, '↵'], label: 'Commit (in the composer)' },
    { keys: [keys.mod, keys.shift, 'P'], label: 'Preview (in the composer)' },
    { keys: ['g', 'l'], label: 'Go to your log', characterOnly: true },
    { keys: ['g', 'e'], label: 'Go to trending', characterOnly: true },
    { keys: ['g', 'p'], label: 'Go to your profile', characterOnly: true },
    { keys: ['g', 's'], label: 'Go to settings', characterOnly: true },
    { keys: ['?'], label: 'Show this list', characterOnly: true },
];

const moderationShortcuts: Shortcut[] = [
    { keys: ['a'], label: 'Approve commit (in the queue)' },
    { keys: ['x'], label: 'Reject commit (in the queue)' },
];

function ShortcutList({ shortcuts }: { shortcuts: Shortcut[] }) {
    return (
        <dl className="divide-y text-sm">
            {shortcuts.map((shortcut) => (
                <div
                    key={shortcut.keys.join('')}
                    className="flex items-center justify-between gap-4 py-2"
                >
                    <dt>{shortcut.label}</dt>
                    <dd className="flex gap-1">
                        {shortcut.keys.map((key) => (
                            <kbd
                                key={key}
                                className="min-w-6 rounded border border-b-2 bg-muted px-1.5 py-0.5 text-center font-mono text-xs"
                            >
                                {key}
                            </kbd>
                        ))}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function KeyboardShortcutsDialog({
    open,
    onOpenChange,
    canModerate,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canModerate: boolean;
}) {
    const keys = useKeyLabels();
    const characterShortcuts = useCharacterShortcuts();
    const returnFocus = useRef<HTMLElement | null>(null);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-sm"
                onOpenAutoFocus={() => {
                    returnFocus.current =
                        document.activeElement instanceof HTMLElement
                            ? document.activeElement
                            : null;
                }}
                onCloseAutoFocus={(event) => {
                    if (returnFocus.current?.isConnected) {
                        event.preventDefault();
                        returnFocus.current.focus();
                    }
                }}
            >
                <DialogTitle>Keyboard shortcuts</DialogTitle>
                <DialogDescription>
                    Character shortcuts work outside text fields and dialogs.
                    Commit actions require focus on a commit.
                </DialogDescription>

                <CharacterShortcutsPreference />

                <ShortcutList
                    shortcuts={shortcuts(keys).filter(
                        (shortcut) =>
                            characterShortcuts || !shortcut.characterOnly,
                    )}
                />

                {canModerate && characterShortcuts && (
                    <section aria-labelledby="moderation-shortcuts">
                        <h3
                            id="moderation-shortcuts"
                            className="mb-1 text-sm font-semibold"
                        >
                            Moderation
                        </h3>
                        <ShortcutList shortcuts={moderationShortcuts} />
                    </section>
                )}
            </DialogContent>
        </Dialog>
    );
}
