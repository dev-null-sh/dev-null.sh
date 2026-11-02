import { Ellipsis } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

type Option = { type: string; label: string };

export function NotificationFilters({
    options,
    enabled,
    onChange,
}: {
    options: Option[];
    enabled: string[];
    onChange: (types: string[]) => void;
}) {
    const isMobile = useIsMobile();
    const hasEnabledAdditionalFilters = options
        .slice(3)
        .some((option) => enabled.includes(option.type));
    const toggle = (type: string) =>
        onChange(
            enabled.includes(type)
                ? enabled.filter((selected) => selected !== type)
                : [...enabled, type],
        );
    const buttons = options.map((option) => {
        const isActive = enabled.includes(option.type);

        return (
            <button
                key={option.type}
                type="button"
                aria-pressed={isActive}
                onClick={() => toggle(option.type)}
                className={cn(
                    'rounded-md border px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    isActive
                        ? 'border-primary/30 bg-primary-soft text-primary hover:bg-primary/15'
                        : 'border-border text-muted-foreground hover:bg-accent hover:text-foreground',
                )}
            >
                {option.label}
            </button>
        );
    });

    return (
        <div className="@container px-4 pb-3 sm:px-6">
            <div
                role="group"
                aria-label="Filter notifications"
                className="flex justify-center gap-2 md:hidden"
            >
                {buttons.slice(0, 3)}
                {/* Crossing the breakpoint resets the menu so it cannot reopen when returning to mobile. */}
                <DropdownMenu
                    key={isMobile ? 'mobile' : 'desktop'}
                    open={isMobile ? undefined : false}
                >
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            aria-label="More notification filters"
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                hasEnabledAdditionalFilters
                                    ? 'border-primary/30 bg-primary-soft text-primary hover:bg-primary/15'
                                    : 'border-border text-muted-foreground hover:bg-accent hover:text-foreground',
                            )}
                        >
                            <Ellipsis className="size-4" aria-hidden />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        className="max-h-[var(--radix-dropdown-menu-content-available-height)] min-w-44 overflow-y-auto md:hidden"
                    >
                        {options.slice(3).map((option) => (
                            <DropdownMenuCheckboxItem
                                key={option.type}
                                checked={enabled.includes(option.type)}
                                onCheckedChange={() => toggle(option.type)}
                                onSelect={(event) => event.preventDefault()}
                            >
                                {option.label}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <div
                role="group"
                aria-label="Filter notifications"
                className="mx-auto hidden w-fit grid-cols-[repeat(2,max-content)] gap-2 md:grid @2xs:grid-cols-[repeat(3,max-content)] @min-[33rem]:grid-cols-[repeat(6,max-content)]"
            >
                {buttons}
            </div>
        </div>
    );
}
