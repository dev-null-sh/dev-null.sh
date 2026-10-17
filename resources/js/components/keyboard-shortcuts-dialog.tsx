import { useLang } from '@erag/lang-sync-inertia/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { KeyLabels } from '@/lib/keyboard';
import { useKeyLabels } from '@/lib/keyboard';

type Shortcut = { keys: string[]; label: string };

type Translate = ReturnType<typeof useLang>['__'];

const shortcuts = (keys: KeyLabels, __: Translate): Shortcut[] => [
    { keys: ['j'], label: __('nav.shortcuts.next_commit') },
    { keys: ['k'], label: __('nav.shortcuts.previous_commit') },
    { keys: ['y'], label: __('nav.shortcuts.copy_link') },
    { keys: ['↵'], label: __('nav.shortcuts.open_commit') },
    { keys: ['r'], label: __('nav.shortcuts.reply') },
    { keys: ['q'], label: __('nav.shortcuts.quote') },
    { keys: ['o'], label: __('nav.shortcuts.toggle_replies') },
    { keys: ['c'], label: __('nav.shortcuts.write_commit') },
    { keys: [keys.mod, 'K'], label: __('nav.shortcuts.search') },
    { keys: ['/'], label: __('nav.shortcuts.search') },
    { keys: [keys.mod, '↵'], label: __('nav.shortcuts.commit_in_composer') },
    {
        keys: [keys.mod, keys.shift, 'P'],
        label: __('nav.shortcuts.preview_in_composer'),
    },
    { keys: ['g', 'l'], label: __('nav.shortcuts.go_to_log') },
    { keys: ['g', 'e'], label: __('nav.shortcuts.go_to_trending') },
    { keys: ['g', 'p'], label: __('nav.shortcuts.go_to_profile') },
    { keys: ['g', 's'], label: __('nav.shortcuts.go_to_settings') },
    { keys: ['?'], label: __('nav.shortcuts.show_list') },
];

const moderationShortcuts = (__: Translate): Shortcut[] => [
    { keys: ['a'], label: __('nav.shortcuts.approve') },
    { keys: ['x'], label: __('nav.shortcuts.reject') },
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
    const { __ } = useLang();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <DialogTitle>{__('nav.shortcuts.title')}</DialogTitle>
                <DialogDescription>
                    {__('nav.shortcuts.description')}
                </DialogDescription>

                <ShortcutList shortcuts={shortcuts(keys, __)} />

                {canModerate && (
                    <section aria-labelledby="moderation-shortcuts">
                        <h3
                            id="moderation-shortcuts"
                            className="mb-1 text-sm font-semibold"
                        >
                            {__('nav.shortcuts.moderation')}
                        </h3>
                        <ShortcutList shortcuts={moderationShortcuts(__)} />
                    </section>
                )}
            </DialogContent>
        </Dialog>
    );
}
