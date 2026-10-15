import { Eye, EyeOff, X } from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { LinkifiedText } from '@/components/linkified-text';
import { Button } from '@/components/ui/button';
import { emojify } from '@/lib/emoji';
import { cn } from '@/lib/utils';

export const maxContentWarningLength = 80;

export function ContentWarning({
    warning,
    keyShortcut,
    className,
    children,
}: {
    warning: string | null;
    keyShortcut?: string;
    className?: string;
    children: ReactNode;
}) {
    const [isRevealed, setIsRevealed] = useState(false);
    const contentId = useId();

    if (warning === null) {
        return children;
    }

    const Icon = isRevealed ? Eye : EyeOff;

    return (
        <>
            <button
                type="button"
                onClick={() => setIsRevealed(!isRevealed)}
                aria-expanded={isRevealed}
                aria-controls={isRevealed ? contentId : undefined}
                aria-keyshortcuts={keyShortcut}
                title={
                    keyShortcut
                        ? `${isRevealed ? 'Hide' : 'Show'} commit (${keyShortcut})`
                        : undefined
                }
                className={cn(
                    'flex w-full items-center gap-2.5 rounded-md border border-dashed border-rail px-3 py-2 text-left transition-colors hover:bg-accent',
                    className,
                )}
                data-test="content-warning"
            >
                <Icon
                    aria-hidden
                    className="size-4 shrink-0 text-muted-foreground"
                />
                <span className="min-w-0 flex-1 text-[15px] [overflow-wrap:anywhere]">
                    <span className="sr-only">Content warning: </span>
                    {warning}
                </span>
                <span className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    {isRevealed ? 'hide' : 'show'}
                </span>
            </button>
            {isRevealed && (
                <div id={contentId} data-test="content-warning-body">
                    {children}
                </div>
            )}
        </>
    );
}

export function Spoiler({ text }: { text: string }) {
    const [isRevealed, setIsRevealed] = useState(false);

    if (isRevealed) {
        return (
            <span
                ref={(node) => node?.focus({ preventScroll: true })}
                tabIndex={-1}
                className="rounded-sm bg-muted box-decoration-clone px-0.5 outline-none"
                data-test="spoiler"
            >
                <LinkifiedText text={emojify(text)} />
            </span>
        );
    }

    const reveal = (event: KeyboardEvent<HTMLSpanElement>) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            setIsRevealed(true);
        }
    };

    return (
        <span
            role="button"
            tabIndex={0}
            aria-label="Spoiler, reveal"
            title="Reveal spoiler"
            onClick={() => setIsRevealed(true)}
            onKeyDown={reveal}
            className="cursor-pointer rounded-sm bg-muted box-decoration-clone px-0.5 outline-none select-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            data-test="spoiler"
        >
            <span aria-hidden className="blur-[5px] transition-[filter]">
                {emojify(text)}
            </span>
        </span>
    );
}

export function ContentWarningToggle({
    value,
    onChange,
    className,
}: {
    value: string | null;
    onChange: (value: string | null) => void;
    className?: string;
}) {
    const isOn = value !== null;

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={() => onChange(isOn ? null : '')}
            aria-pressed={isOn}
            aria-label="Content warning"
            title={isOn ? 'Remove content warning' : 'Add a content warning'}
            className={cn(
                'text-muted-foreground aria-pressed:bg-accent aria-pressed:text-foreground',
                className,
            )}
            data-test="content-warning-toggle"
        >
            <EyeOff className="size-5" />
        </Button>
    );
}

export function ContentWarningField({
    id,
    value,
    error,
    onChange,
    onDone,
    className,
}: {
    id: string;
    value: string | null;
    error?: string;
    onChange: (value: string | null) => void;
    onDone: () => void;
    className?: string;
}) {
    if (value === null) {
        return null;
    }

    const remaining = maxContentWarningLength - value.length;

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            onDone();
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            onChange(null);
            onDone();
        }
    };

    return (
        <div className={className}>
            <div className="flex items-center gap-2 border-b border-dashed border-rail pb-1.5">
                <EyeOff
                    aria-hidden
                    className="size-4 shrink-0 text-muted-foreground"
                />
                <label htmlFor={id} className="sr-only">
                    Content warning
                </label>
                <input
                    id={id}
                    name="content_warning"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    onKeyDown={onKeyDown}
                    maxLength={maxContentWarningLength}
                    autoFocus
                    autoComplete="off"
                    placeholder="Content warning, e.g. spoilers: severance s3"
                    aria-invalid={error ? true : undefined}
                    className="min-w-0 flex-1 bg-transparent py-0.5 text-[15px] outline-none placeholder:text-muted-foreground"
                    data-test="content-warning-input"
                />
                {remaining <= 20 && (
                    <span
                        aria-label={`${remaining} characters left`}
                        className="font-mono text-[13px] text-amber-700 dark:text-amber-400"
                    >
                        {remaining}
                    </span>
                )}
                <button
                    type="button"
                    onClick={() => {
                        onChange(null);
                        onDone();
                    }}
                    aria-label="Remove content warning"
                    title="Remove content warning"
                    className="-mr-1 rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <X className="size-4" />
                </button>
            </div>
            <InputError message={error} className="mt-1" />
        </div>
    );
}
