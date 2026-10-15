import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

const shortcuts: { keys: string[]; label: string }[] = [
    { keys: ['j'], label: 'Next commit' },
    { keys: ['k'], label: 'Previous commit' },
    { keys: ['↵'], label: 'Open the focused commit' },
    { keys: ['c'], label: 'Write a commit' },
    { keys: ['⌘', 'K'], label: 'Search developers' },
    { keys: ['/'], label: 'Search developers' },
    { keys: ['⌘', '↵'], label: 'Commit (in the composer)' },
    { keys: ['g', 'l'], label: 'Go to your log' },
    { keys: ['g', 'e'], label: 'Go to everyone' },
    { keys: ['g', 'p'], label: 'Go to your profile' },
    { keys: ['g', 's'], label: 'Go to settings' },
    { keys: ['?'], label: 'Show this list' },
];

export function KeyboardShortcutsDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <DialogTitle>Keyboard shortcuts</DialogTitle>
                <DialogDescription>
                    They work anywhere except while you're typing.
                </DialogDescription>

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
            </DialogContent>
        </Dialog>
    );
}
