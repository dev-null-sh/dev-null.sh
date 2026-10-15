import { CalendarPlus, Download, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { formatEventDay, formatEventTimeRange } from '@/lib/event';
import type { EventCalendarLinks } from '@/types';

function calendarOptions(calendar: EventCalendarLinks) {
    return [
        { label: 'Apple Calendar', href: calendar.apple_url, external: false },
        { label: 'Google Calendar', href: calendar.google_url, external: true },
        { label: 'Outlook', href: calendar.outlook_url, external: true },
    ];
}

export function EventCalendarMenu({
    startsAt,
    endsAt,
    calendar,
    variant = 'button',
}: {
    startsAt: string;
    endsAt: string;
    calendar: EventCalendarLinks;
    variant?: 'icon' | 'button';
}) {
    const isMobile = useIsMobile();
    const options = calendarOptions(calendar);

    const trigger = {
        icon: (
            <Button
                variant="ghost"
                size="icon"
                aria-label="Add to calendar"
                className="size-7 text-muted-foreground"
            >
                <CalendarPlus className="size-4" />
            </Button>
        ),
        button: (
            <Button variant="outline" size="sm" aria-label="Add to calendar">
                <CalendarPlus className="size-4" />
                <span className="hidden sm:inline">Add to calendar</span>
            </Button>
        ),
    }[variant];

    if (isMobile) {
        return (
            <Sheet>
                <SheetTrigger asChild>{trigger}</SheetTrigger>
                <SheetContent side="bottom" className="rounded-t-xl pb-6">
                    <SheetHeader>
                        <SheetTitle>Add to calendar</SheetTitle>
                        <SheetDescription className="font-mono text-[13px]">
                            {formatEventDay(startsAt)} ·{' '}
                            {formatEventTimeRange(startsAt, endsAt)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="mx-4 divide-y rounded-lg border">
                        {options.map((option) => (
                            <a
                                key={option.label}
                                href={option.href}
                                target={option.external ? '_blank' : undefined}
                                rel="noopener noreferrer"
                                className="flex min-h-12 items-center gap-3 px-3 font-medium"
                            >
                                {option.label}
                                {option.external && (
                                    <ExternalLink className="ml-auto size-4 text-muted-foreground" />
                                )}
                            </a>
                        ))}
                    </div>
                    <p className="mx-4 font-mono text-xs text-muted-foreground">
                        your calendar reminds you 1h before · changes come as
                        notifications
                    </p>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-64">
                {options.map((option) => (
                    <DropdownMenuItem key={option.label} asChild>
                        <a
                            href={option.href}
                            target={option.external ? '_blank' : undefined}
                            rel="noopener noreferrer"
                        >
                            {option.label}
                            {option.external && (
                                <ExternalLink className="ml-auto size-3.5 text-muted-foreground" />
                            )}
                        </a>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <a href={calendar.ics_url}>
                        <Download className="size-4 text-muted-foreground" />
                        Download <span className="font-mono">.ics</span>
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
