import { ImagePlay, ImagePlus } from 'lucide-react';
import { useRef } from 'react';
import { ActionTooltip } from '@/components/action-tooltip';
import { GifPickerDialog } from '@/components/gif-picker-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { GifProviderKey } from '@/lib/gif-provider-key';
import { gifProviderFor } from '@/lib/gif-providers';
import type { GifAttachment } from '@/lib/gif-providers';
import { acceptedImageTypes, maxImages } from '@/lib/read-picked-images';
import { cn } from '@/lib/utils';

/**
 * One image affordance for the commit composers. With a stored GIF provider key it offers images or a GIF; without
 * one it stays the plain image picker, so the menu never has a single dead option.
 */
export function ComposerMediaButton({
    gifKey,
    gifOpen,
    onGifOpenChange,
    hasGif,
    gifDisabled = false,
    count,
    disabled = false,
    reading = false,
    onPickImages,
    onPickGif,
    className,
}: {
    gifKey: GifProviderKey | null;
    gifOpen: boolean;
    onGifOpenChange: (open: boolean) => void;
    hasGif: boolean;
    gifDisabled?: boolean;
    count: number;
    disabled?: boolean;
    reading?: boolean;
    onPickImages: (files: File[]) => Promise<void>;
    onPickGif: (gif: GifAttachment) => void;
    className?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const canAddImages = !disabled && !reading && count < maxImages;
    const openImages = () => {
        if (canAddImages) {
            inputRef.current?.click();
        }
    };

    return (
        <>
            <input
                ref={inputRef}
                type="file"
                accept={acceptedImageTypes.join(',')}
                multiple
                hidden
                onChange={async (event) => {
                    const input = event.currentTarget;
                    const files = Array.from(input.files ?? []);
                    // Allow the same selection again even if this read is discarded.
                    input.value = '';
                    if (files.length === 0) return;

                    await onPickImages(files);
                }}
                data-test="commit-image-input"
            />
            {gifKey === null ? (
                <ActionTooltip label={`Attach up to ${maxImages} images`}>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={!canAddImages}
                        onClick={openImages}
                        aria-label={
                            reading ? 'Reading images' : 'Attach images'
                        }
                        aria-busy={reading}
                        className={cn('text-muted-foreground', className)}
                        data-test="attach-images-button"
                    >
                        <ImagePlus className="size-5" />
                    </Button>
                </ActionTooltip>
            ) : (
                <DropdownMenu>
                    <ActionTooltip label="Add image or GIF">
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={disabled}
                                aria-label="Add image or GIF"
                                className={cn(
                                    'text-muted-foreground',
                                    className,
                                )}
                                data-test="attach-media-button"
                            >
                                <ImagePlus className="size-5" />
                            </Button>
                        </DropdownMenuTrigger>
                    </ActionTooltip>
                    <DropdownMenuContent
                        align="start"
                        collisionPadding={16}
                        className="min-w-44"
                    >
                        <DropdownMenuItem
                            data-test="attach-images-button"
                            disabled={!canAddImages}
                            onSelect={() => {
                                openImages();
                            }}
                        >
                            <ImagePlus />
                            Upload image
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            data-test="attach-gif-button"
                            disabled={disabled || gifDisabled || hasGif}
                            onSelect={() => {
                                onGifOpenChange(true);
                            }}
                        >
                            <ImagePlay />
                            {hasGif ? 'One GIF per commit' : 'Choose a GIF'}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
            {gifKey !== null && (
                <GifPickerDialog
                    provider={gifProviderFor(gifKey.provider)}
                    apiKey={gifKey.key}
                    open={gifOpen}
                    onOpenChange={onGifOpenChange}
                    onPick={onPickGif}
                />
            )}
        </>
    );
}
