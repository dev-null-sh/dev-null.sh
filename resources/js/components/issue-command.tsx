import { useHttp, usePage } from '@inertiajs/react';
import { Bug, Lightbulb, X } from 'lucide-react';
import type { KeyboardEvent, RefObject } from 'react';
import { useLayoutEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/IssueController';
import { showTimeout, timeoutFrom } from '@/components/timeout-dialog';
import { WordmarkText } from '@/components/wordmark-text';
import { collectEnvironment } from '@/lib/environment';
import type { IssueEnvironment } from '@/lib/environment';
import type { IssueCommand, IssueKind } from '@/lib/issue-commands';
import {
    hintedCommand,
    issueCommands,
    suggestedCommands,
    typedCommand,
} from '@/lib/issue-commands';
import { hasModKey } from '@/lib/keyboard';
import type { SuggestionPosition } from '@/lib/suggestion-position';
import { suggestionPositionAt } from '@/lib/suggestion-position';
import { cn } from '@/lib/utils';

const hintDismissedKey = 'issue-command-hint-dismissed';

function isHintDismissed(): boolean {
    try {
        return window.localStorage.getItem(hintDismissedKey) === '1';
    } catch {
        return false;
    }
}

/**
 * Typing `/bug` or `/feature` and a space turns the commit draft into an issue draft, opened upstream by the app.
 */
export function useIssueCommand({
    body,
    updateBody,
    enabled,
    initialKind = null,
    onCreated,
}: {
    body: string;
    updateBody: (body: string) => void;
    enabled: boolean;
    initialKind?: IssueKind | null;
    onCreated: () => void;
}) {
    const [kind, setKind] = useState<IssueKind | null>(
        enabled ? initialKind : null,
    );
    const [selected, setSelected] = useState(0);
    const [dismissed, setDismissed] = useState(false);
    const [focused, setFocused] = useState(false);
    const [hintDismissed, setHintDismissed] = useState(isHintDismissed);
    const request = useHttp<
        { command: string; body: string; environment?: IssueEnvironment },
        { number: number; url: string }
    >({ command: '', body: '' });
    const command =
        issueCommands.find((candidate) => candidate.kind === kind) ?? null;
    // A draft restored from the stash can be a lone slash: the menu only opens while the user is typing in it.
    const suggestions =
        enabled && kind === null && !dismissed && focused
            ? suggestedCommands(body)
            : [];
    const hint =
        enabled && kind === null && !hintDismissed ? hintedCommand(body) : null;

    const start = (next: IssueKind, rest: string) => {
        setKind(next);
        setSelected(0);
        updateBody(rest);
    };

    const change = (value: string) => {
        setDismissed(false);
        const typed = enabled && kind === null ? typedCommand(value) : null;

        if (typed) {
            start(typed.kind, typed.body);
        } else {
            updateBody(value);
        }
    };

    const submit = async () => {
        if (command === null || request.processing) {
            return;
        }

        request.transform(() => ({
            command: command.name,
            body,
            environment: collectEnvironment(),
        }));

        try {
            const issue = await request.submit(store(), {
                onError: (errors) => {
                    toast.error(
                        Object.values(errors)[0] ?? 'Check your draft.',
                    );
                },
                onHttpException: (response) => {
                    const timeout = timeoutFrom(response.status, response.data);

                    if (timeout) {
                        showTimeout(timeout);
                    } else {
                        toast.error(
                            response.status === 429
                                ? 'Issue limit reached: 10 a day. Try again tomorrow.'
                                : 'GitHub did not confirm the issue. Check upstream before retrying. Your draft is safe.',
                        );
                    }
                },
                onNetworkError: () => {
                    toast.error(
                        'Connection lost. Check upstream before retrying. Your draft is safe.',
                    );
                },
            });
            setKind(null);
            onCreated();
            toast.success(`Opened issue #${issue.number}.`, {
                duration: 10000,
                action: {
                    label: 'View issue',
                    onClick: () =>
                        window.open(issue.url, '_blank', 'noopener,noreferrer'),
                },
            });
        } catch {
            // The callbacks explain the failure; the draft stays an issue for a retry.
        }
    };

    /**
     * Handles the keys the issue command owns, returning whether it did.
     */
    const handleKeyDown = (
        event: KeyboardEvent<HTMLTextAreaElement>,
        submittable: boolean,
    ): boolean => {
        if (event.nativeEvent.isComposing) {
            return false;
        }

        if (suggestions.length > 0) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                setSelected(
                    (index) =>
                        (index +
                            (event.key === 'ArrowDown' ? 1 : -1) +
                            suggestions.length) %
                        suggestions.length,
                );
                return true;
            }

            if (
                (event.key === 'Tab' || event.key === 'Enter') &&
                !event.shiftKey &&
                !hasModKey(event)
            ) {
                event.preventDefault();
                start(suggestions[selected % suggestions.length].kind, '');
                return true;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                setDismissed(true);
                return true;
            }
        }

        if (command === null) {
            return false;
        }

        if (
            event.key === 'Backspace' &&
            event.currentTarget.selectionStart === 0 &&
            event.currentTarget.selectionEnd === 0
        ) {
            event.preventDefault();
            setKind(null);
            return true;
        }

        if (event.key === 'Enter' && hasModKey(event)) {
            event.preventDefault();
            if (submittable) void submit();
            return true;
        }

        return false;
    };

    return {
        command,
        suggestions,
        hint,
        dismissHint: () => {
            setHintDismissed(true);
            try {
                window.localStorage.setItem(hintDismissedKey, '1');
            } catch {
                // Without storage, the hint stays dismissed until the page reloads.
            }
        },
        selected: selected % Math.max(suggestions.length, 1),
        processing: request.processing,
        change,
        focus: () => setFocused(true),
        blur: () => setFocused(false),
        start,
        exit: () => setKind(null),
        submit,
        handleKeyDown,
    };
}

export type UseIssueCommandReturn = ReturnType<typeof useIssueCommand>;

const icons = { feature: Lightbulb, bug: Bug };

export function IssueCommandBadge({
    command,
    onExit,
}: {
    command: IssueCommand;
    onExit: () => void;
}) {
    const { repository } = usePage().props;
    const Icon = icons[command.kind];

    return (
        <div className="mt-1 flex min-w-0 items-center gap-2 text-[13px]">
            <span className="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-primary-soft py-0.5 pr-1 pl-2 font-mono text-primary">
                <Icon className="size-3.5" aria-hidden="true" />
                {command.name}
                <button
                    type="button"
                    onClick={onExit}
                    aria-label={`Cancel ${command.name}, back to a commit`}
                    className="rounded-sm p-0.5 hover:bg-primary/15"
                >
                    <X className="size-3.5" aria-hidden="true" />
                </button>
            </span>
            <span className="min-w-0 truncate text-muted-foreground">
                Public issue on {repository}, crediting you, with your browser
                and screen details
            </span>
        </div>
    );
}

/**
 * Where the menu floats: under the slash that starts the draft, kept there while the page scrolls or resizes.
 */
function useSlashPosition(
    anchor: RefObject<HTMLTextAreaElement | null>,
): SuggestionPosition | null {
    const [position, setPosition] = useState<SuggestionPosition | null>(null);

    useLayoutEffect(() => {
        const textarea = anchor.current;

        if (!textarea) {
            return;
        }

        const reposition = () => setPosition(suggestionPositionAt(textarea, 0));

        reposition();
        window.addEventListener('scroll', reposition, {
            capture: true,
            passive: true,
        });
        window.addEventListener('resize', reposition);
        const viewport = window.visualViewport;
        viewport?.addEventListener('resize', reposition);
        viewport?.addEventListener('scroll', reposition);
        // A dialog opening on a restored slash is still zooming in when the menu first measures it.
        document.addEventListener('animationend', reposition, true);

        return () => {
            window.removeEventListener('scroll', reposition, {
                capture: true,
            });
            window.removeEventListener('resize', reposition);
            viewport?.removeEventListener('resize', reposition);
            viewport?.removeEventListener('scroll', reposition);
            document.removeEventListener('animationend', reposition, true);
        };
    }, [anchor]);

    return position;
}

/**
 * The commands a draft that is a lone slash could become, floating over the composer like the other suggestions so
 * nothing moves when it opens.
 */
export function IssueCommandSuggestions({
    suggestions,
    selected,
    onPick,
    anchor,
}: {
    suggestions: IssueCommand[];
    selected: number;
    onPick: (kind: IssueKind) => void;
    anchor: RefObject<HTMLTextAreaElement | null>;
}) {
    const position = useSlashPosition(anchor);

    if (position === null) {
        return null;
    }

    return createPortal(
        <div
            data-command-suggestions
            role="listbox"
            aria-label="Commands"
            style={position}
            onMouseDown={(event) => event.preventDefault()}
            className="pointer-events-auto fixed z-50 overflow-y-auto overscroll-contain rounded-lg border bg-popover p-1.5 text-popover-foreground shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)] motion-safe:animate-in motion-safe:fade-in-0 motion-safe:zoom-in-95"
        >
            {suggestions.map((command, index) => {
                const Icon = icons[command.kind];

                return (
                    <button
                        key={command.kind}
                        type="button"
                        role="option"
                        aria-selected={selected === index}
                        tabIndex={-1}
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => onPick(command.kind)}
                        className={cn(
                            'flex w-full items-center gap-3 rounded-sm px-2 py-1.5 text-left text-sm',
                            selected === index && 'bg-accent',
                        )}
                    >
                        <Icon
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span className="w-20 shrink-0 font-mono">
                            {command.name}
                        </span>
                        <span className="min-w-0 truncate text-muted-foreground">
                            {command.description}
                        </span>
                    </button>
                );
            })}
            <p className="flex flex-wrap gap-x-1.5 px-2 pt-1 pb-0.5 font-mono text-xs text-muted-foreground">
                <span className="whitespace-nowrap">↑↓ select</span>
                <span className="whitespace-nowrap">· Tab or Enter pick</span>
                <span className="whitespace-nowrap">· Esc dismiss</span>
            </p>
        </div>,
        document.body,
    );
}

export function IssueCommandHint({
    command,
    onUse,
    onDismiss,
}: {
    command: IssueCommand;
    onUse: (kind: IssueKind) => void;
    onDismiss: () => void;
}) {
    const Icon = icons[command.kind];

    return (
        <div className="mt-1 flex min-w-0 items-center gap-2 text-[13px] text-muted-foreground">
            <Icon className="size-3.5 shrink-0" aria-hidden="true" />
            <span className="min-w-0 truncate">
                <WordmarkText>
                    {command.kind === 'bug'
                        ? 'Found a bug in /dev/null?'
                        : 'Have an idea for /dev/null?'}
                </WordmarkText>
            </span>
            <button
                type="button"
                onClick={() => onUse(command.kind)}
                className="shrink-0 rounded-sm font-mono text-primary hover:underline"
            >
                Use {command.name}
            </button>
            <button
                type="button"
                onClick={onDismiss}
                aria-label="Don't suggest issue commands again"
                className="shrink-0 rounded-sm p-0.5 hover:bg-accent"
            >
                <X className="size-3.5" aria-hidden="true" />
            </button>
        </div>
    );
}
