import { useEffect, useRef, useState } from 'react';
import type { PointerEvent, ReactElement, ReactNode } from 'react';
import { PastelIcon } from '@/components/pastel-icon';
import type { PastelVariant } from '@/components/pastel-icon';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatCount, formatExactCount } from '@/lib/numbers';

export function PastelCollection({
    total,
    diamond,
    frosted,
    trigger,
    children,
}: {
    total: number;
    diamond: number;
    frosted: number;
    trigger: ReactElement;
    children?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const closing = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pointerOpening = useRef(false);
    const pointerOpened = useRef(false);
    const triggerElement = useRef<HTMLSpanElement>(null);
    const contentElement = useRef<HTMLDivElement>(null);
    const cancelClose = () => {
        if (closing.current !== null) clearTimeout(closing.current);
    };
    const leave = (event: PointerEvent<HTMLElement>) => {
        if (event.pointerType !== 'mouse') return;
        cancelClose();
        closing.current = setTimeout(() => {
            if (
                !triggerElement.current?.contains(document.activeElement) &&
                !contentElement.current?.contains(document.activeElement)
            ) {
                setOpen(false);
            }
        }, 180);
    };
    useEffect(
        () => () => {
            if (closing.current !== null) clearTimeout(closing.current);
        },
        [],
    );

    return (
        <Popover
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);
                if (!nextOpen) pointerOpening.current = false;
            }}
        >
            <span
                ref={triggerElement}
                className="inline-flex"
                onFocusCapture={cancelClose}
                onPointerEnter={(event) => {
                    if (event.pointerType === 'mouse') {
                        cancelClose();
                        if (!open) {
                            pointerOpening.current = true;
                            pointerOpened.current = true;
                            setOpen(true);
                        }
                    }
                }}
                onPointerDown={() => {
                    if (!open) {
                        pointerOpening.current = true;
                        pointerOpened.current = true;
                    }
                    cancelClose();
                }}
                onPointerLeave={leave}
            >
                <PopoverTrigger asChild>{trigger}</PopoverTrigger>
            </span>
            <PopoverContent
                ref={contentElement}
                side="top"
                align="start"
                className="w-60 max-w-[calc(100vw-2rem)] p-3"
                onPointerEnter={cancelClose}
                onPointerLeave={leave}
                onFocusCapture={() => {
                    cancelClose();
                    pointerOpened.current = false;
                }}
                onOpenAutoFocus={(event) => {
                    if (pointerOpening.current) event.preventDefault();
                    pointerOpening.current = false;
                }}
                onCloseAutoFocus={(event) => {
                    if (pointerOpened.current) event.preventDefault();
                    pointerOpened.current = false;
                }}
            >
                <div className="flex items-center justify-between gap-3">
                    <span className="shrink-0 text-xs text-muted-foreground">
                        Received
                    </span>
                    <div className="flex flex-wrap items-center justify-end gap-x-3 gap-y-2">
                        {(
                            [
                                [
                                    'original',
                                    'Original',
                                    Math.max(0, total - diamond - frosted),
                                ],
                                ['diamond', 'Diamond', diamond],
                                ['frosted', 'Frosted', frosted],
                            ] as const
                        )
                            .filter(([, , count]) => count > 0)
                            .map(([variant, label, count]) => (
                                <PastelVariantInfo
                                    key={variant}
                                    variant={variant}
                                    label={label}
                                    count={count}
                                    animated={open}
                                    onPointerEnter={cancelClose}
                                    onPointerLeave={leave}
                                />
                            ))}
                        {total === 0 && (
                            <span className="text-xs text-muted-foreground">
                                None yet
                            </span>
                        )}
                    </div>
                </div>
                {children}
            </PopoverContent>
        </Popover>
    );
}

const descriptions: Record<
    PastelVariant,
    { name: string; description: string }
> = {
    original: {
        name: 'Original',
        description:
            'A little appreciation for a commit. Every developer has ten to give each day.',
    },
    diamond: {
        name: 'Diamond · Legendary',
        description:
            'A special thank-you for a commit or developer you particularly appreciated. One to give each week.',
    },
    frosted: {
        name: 'Frosted · Christmas',
        description:
            'A Christmas surprise: an Original pastel can become Frosted at random on December 25. Yours to keep all year.',
    },
};

function PastelVariantInfo({
    variant,
    label,
    count,
    animated,
    onPointerEnter,
    onPointerLeave,
}: {
    variant: PastelVariant;
    label: string;
    count: number;
    animated: boolean;
    onPointerEnter: () => void;
    onPointerLeave: (event: PointerEvent<HTMLElement>) => void;
}) {
    const [open, setOpen] = useState(false);
    const info = descriptions[variant];
    return (
        <Tooltip open={open} onOpenChange={setOpen}>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className="inline-flex items-center gap-1.5 rounded-sm text-xs focus-visible:outline-2 focus-visible:outline-ring"
                    aria-label={`${formatExactCount(count)} ${label} pastéis received. Learn about this pastel.`}
                    onClick={(event) => {
                        event.preventDefault();
                        setOpen((current) => !current);
                    }}
                >
                    <PastelIcon
                        variant={variant}
                        size={20}
                        animated={animated}
                    />
                    <span aria-hidden="true" className="font-mono tabular-nums">
                        {formatCount(count)}
                    </span>
                </button>
            </TooltipTrigger>
            <TooltipContent
                side="top"
                className="max-w-56 space-y-1"
                onPointerEnter={onPointerEnter}
                onPointerLeave={onPointerLeave}
            >
                <p className="font-medium">{info.name}</p>
                <p className="leading-relaxed text-muted-foreground">
                    {info.description}
                </p>
            </TooltipContent>
        </Tooltip>
    );
}
