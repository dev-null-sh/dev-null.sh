import { usePage } from '@inertiajs/react';
import { Layers } from 'lucide-react';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { sectionIcon } from '@/lib/sections';
import { cn } from '@/lib/utils';

const noSection = 'none';

/**
 * Lets the author post the commit to a section instead of the logs. It is only ever chosen here, when composing: a
 * commit stays where it was posted.
 */
export function SectionPicker({
    section,
    onChange,
    disabled,
    className,
}: {
    section: string | null;
    onChange: (section: string | null) => void;
    disabled?: boolean;
    className?: string;
}) {
    const { sections } = usePage().props;
    const selected = sections.find((option) => option.value === section);
    const choice = { icon: selected ? sectionIcon(selected.value) : Layers };
    const label = selected
        ? `Posting to ${selected.title}`
        : 'Post to a section';

    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            disabled={disabled}
                            aria-label={label}
                            aria-pressed={selected !== undefined}
                            className={cn(
                                'text-muted-foreground aria-pressed:bg-primary-soft aria-pressed:text-primary aria-pressed:hover:bg-primary-soft aria-pressed:hover:text-primary',
                                className,
                            )}
                            data-test="section-button"
                        >
                            <choice.icon className="size-5" />
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>{label}</TooltipContent>
            </Tooltip>
            <DropdownMenuContent
                align="start"
                collisionPadding={8}
                className="w-56"
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Post to
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuRadioGroup
                    value={section ?? noSection}
                    onValueChange={(value) =>
                        onChange(value === noSection ? null : value)
                    }
                >
                    <DropdownMenuRadioItem value={noSection}>
                        The logs
                    </DropdownMenuRadioItem>
                    {sections.map((option) => (
                        <DropdownMenuRadioItem
                            key={option.value}
                            value={option.value}
                        >
                            {option.title}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
