import { ChevronDown, Link2, Link2Off } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { UseLinkCardReturn } from '@/hooks/use-link-card';
import { cn } from '@/lib/utils';

const noCard = 'none';

function withoutScheme(link: string): string {
    return link.replace(/^https?:\/\//i, '');
}

/**
 * Lets the author pick which link of the body gets the link card, or none. The first link gets it by default, so
 * the choice is only sent once the author made one.
 */
export function LinkCardPicker({
    linkCard,
    disabled,
    className,
    labelClassName,
    name = 'link_preview_url',
}: {
    linkCard: UseLinkCardReturn;
    disabled?: boolean;
    className?: string;
    labelClassName?: string;
    name?: string;
}) {
    if (linkCard.links.length === 0) {
        return null;
    }

    return (
        <>
            {linkCard.isChosen && (
                <input type="hidden" name={name} value={linkCard.link ?? ''} />
            )}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={disabled}
                        title="Choose which link gets a card"
                        className={cn(
                            'min-w-0 gap-1.5 px-2 font-mono text-[13px] font-normal text-muted-foreground',
                            className,
                        )}
                        data-test="link-card-button"
                    >
                        {linkCard.link === null ? <Link2Off /> : <Link2 />}
                        <span className="sr-only">Link card:</span>
                        <span className={cn('truncate', labelClassName)}>
                            {linkCard.link === null
                                ? 'No card'
                                : withoutScheme(linkCard.link)}
                        </span>
                        <ChevronDown className="size-3.5 opacity-60" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    collisionPadding={16}
                    className="w-72 max-w-[calc(100vw-2rem)]"
                >
                    <DropdownMenuLabel className="font-mono text-xs font-normal text-muted-foreground">
                        Link card
                    </DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={linkCard.link ?? noCard}
                        onValueChange={(value) =>
                            linkCard.choose(value === noCard ? null : value)
                        }
                    >
                        {linkCard.links.map((link) => (
                            <DropdownMenuRadioItem
                                key={link}
                                value={link}
                                title={link}
                                className="font-mono text-[13px]"
                            >
                                <span className="truncate">
                                    {withoutScheme(link)}
                                </span>
                            </DropdownMenuRadioItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuRadioItem value={noCard}>
                            No card
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );
}
