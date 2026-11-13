import { Fragment } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { KeyLabels } from '@/lib/keyboard';
import { useKeyLabels } from '@/lib/keyboard';

// Each entry in `keys` is one way to press it: most have one, search has two.
type Shortcut = { keys: string[][]; label: string };
type Group = { title: string; shortcuts: Shortcut[] };

const groups = (
    keys: KeyLabels,
    hasRemotes: boolean,
    canModerate: boolean,
): Group[][] => [
    [
        {
            title: 'Commits',
            shortcuts: [
                { keys: [['j']], label: 'Next' },
                { keys: [['k']], label: 'Previous' },
                { keys: [['↵']], label: 'Open' },
                { keys: [['r']], label: 'Reply' },
                { keys: [['q']], label: 'Quote' },
                { keys: [['s']], label: 'Star or unstar' },
                { keys: [['o']], label: 'Show or hide replies' },
                { keys: [['y']], label: 'Copy link' },
            ],
        },
    ],
    [
        {
            title: 'Go to',
            shortcuts: [
                { keys: [['g', 'l']], label: 'Your log' },
                { keys: [['g', 'e']], label: 'Trending' },
                { keys: [['g', 'p']], label: 'Your profile' },
                { keys: [['g', 'n']], label: 'Notifications' },
                ...(hasRemotes
                    ? [{ keys: [['g', 'm']], label: 'Remotes' }]
                    : []),
                { keys: [['g', '*']], label: 'Starred' },
                { keys: [['g', 's']], label: 'Settings' },
            ],
        },
    ],
    [
        {
            title: 'Write',
            shortcuts: [
                { keys: [['c']], label: 'New commit' },
                { keys: [[keys.mod, '↵']], label: 'Commit' },
                { keys: [[keys.mod, keys.shift, 'P']], label: 'Preview' },
            ],
        },
        {
            title: 'Anywhere',
            shortcuts: [
                { keys: [[keys.mod, 'K'], ['/']], label: 'Search' },
                { keys: [['?']], label: 'Show this list' },
            ],
        },
        ...(canModerate
            ? [
                  {
                      title: 'Moderation queue',
                      shortcuts: [
                          { keys: [['a']], label: 'Approve commit' },
                          { keys: [['x']], label: 'Reject commit' },
                      ],
                  },
              ]
            : []),
    ],
];

function ShortcutGroup({ group }: { group: Group }) {
    const id = `shortcuts-${group.title.toLowerCase().replaceAll(' ', '-')}`;

    return (
        <section aria-labelledby={id}>
            <h3
                id={id}
                className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {group.title}
            </h3>
            <dl className="divide-y text-sm">
                {group.shortcuts.map((shortcut) => (
                    <div
                        key={shortcut.label}
                        className="flex items-center justify-between gap-4 py-2"
                    >
                        <dt>{shortcut.label}</dt>
                        <dd className="flex items-center gap-1">
                            {shortcut.keys.map((combo, index) => (
                                <Fragment key={combo.join('')}>
                                    {index > 0 && (
                                        <span className="px-0.5 text-xs text-muted-foreground">
                                            or
                                        </span>
                                    )}
                                    {combo.map((key) => (
                                        <kbd
                                            key={key}
                                            className="min-w-6 rounded border border-b-2 bg-muted px-1.5 py-0.5 text-center font-mono text-xs"
                                        >
                                            {key}
                                        </kbd>
                                    ))}
                                </Fragment>
                            ))}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export function KeyboardShortcutsDialog({
    open,
    onOpenChange,
    canModerate,
    hasRemotes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canModerate: boolean;
    hasRemotes: boolean;
}) {
    const keys = useKeyLabels();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="overflow-y-auto sm:max-w-sm md:max-w-3xl">
                <DialogTitle>Keyboard shortcuts</DialogTitle>
                <DialogDescription>
                    They work anywhere except while you're typing. Commit and
                    preview work in the composer.
                </DialogDescription>

                <div className="grid gap-x-8 gap-y-6 md:grid-cols-3">
                    {groups(keys, hasRemotes, canModerate).map((column) => (
                        <div key={column[0].title} className="space-y-6">
                            {column.map((group) => (
                                <ShortcutGroup
                                    key={group.title}
                                    group={group}
                                />
                            ))}
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
