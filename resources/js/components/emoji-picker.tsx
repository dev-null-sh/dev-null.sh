import { Smile } from 'lucide-react';
import { useRef, useState } from 'react';
import { ActionTooltip } from '@/components/action-tooltip';
import { EmojiPicker } from '@/components/chat/message-reactions';
import { buttonVariants } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const noReactions = new Set<string>();

/**
 * The reactions' emoji picker, for composers. The emoji is handed over once the picker has closed, so `onPick` can put
 * the focus back in the textarea instead of on this button.
 */
export function EmojiPickerButton({
    disabled,
    onPick,
    className,
}: {
    disabled?: boolean;
    onPick: (emoji: string) => void;
    className?: string;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const pickedEmoji = useRef<string | null>(null);

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <ActionTooltip label="Add emoji">
                <PopoverTrigger
                    type="button"
                    disabled={disabled}
                    aria-label="Add emoji"
                    className={cn(
                        buttonVariants({ variant: 'ghost', size: 'icon' }),
                        'text-muted-foreground',
                        className,
                    )}
                    data-test="emoji-picker-button"
                >
                    <Smile className="size-5" />
                </PopoverTrigger>
            </ActionTooltip>
            <PopoverContent
                align="end"
                collisionPadding={8}
                className="flex h-[min(25rem,var(--radix-popover-content-available-height))] w-76 flex-col overflow-hidden rounded-lg p-0 shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)]"
                onCloseAutoFocus={(event) => {
                    const emoji = pickedEmoji.current;

                    if (emoji !== null) {
                        event.preventDefault();
                        pickedEmoji.current = null;
                        onPick(emoji);
                    }
                }}
            >
                <EmojiPicker
                    usage={[]}
                    reacted={noReactions}
                    onPick={(emoji) => {
                        pickedEmoji.current = emoji;
                        setIsOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
