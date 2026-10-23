import { Link, router, usePage } from '@inertiajs/react';
import { CalendarCheck, ChevronDown, Star, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import EventAttendanceController from '@/actions/App/Http/Controllers/EventAttendanceController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import type { EventResponse } from '@/types';

type Choice = EventResponse | null;

const choices: {
    value: Choice;
    label: string;
    description: string;
    icon: typeof X;
}[] = [
    {
        value: null,
        label: 'Not going',
        description: 'You are not listed and nothing is kept.',
        icon: X,
    },
    {
        value: 'going',
        label: 'Going',
        description: 'You show up among the people going.',
        icon: CalendarCheck,
    },
    {
        value: 'interested',
        label: 'Interested',
        description: 'Keep an eye on it. Only you see this.',
        icon: Star,
    },
];

/**
 * A response picker in the shape of GitHub's Watch button: the current choice opens a menu of all of them, each with
 * what it means, beside the number of people going.
 */
export function EventResponseButton({
    eventId,
    response,
    goingCount,
    isHost,
    isOpen,
    className,
}: {
    eventId: string;
    response: Choice;
    goingCount: number;
    isHost: boolean;
    isOpen: boolean;
    className?: string;
}) {
    const { auth } = usePage().props;
    const [isSaving, setIsSaving] = useState(false);
    const current = choices.find((choice) => choice.value === response);
    const Icon = isHost ? CalendarCheck : (current?.icon ?? X);
    const label = isHost ? 'Hosting' : (current?.label ?? 'Not going');

    const choose = (value: Choice) => {
        if (value === response) {
            return;
        }

        const options = {
            preserveScroll: true,
            only: ['event', 'response', 'attendees'],
            onStart: () => setIsSaving(true),
            onFinish: () => setIsSaving(false),
            onError: (errors: Record<string, string>) =>
                toast.error(
                    Object.values(errors)[0] ?? 'Could not save your response.',
                ),
        };

        if (value === null) {
            router.delete(EventAttendanceController.destroy(eventId), options);

            return;
        }

        router.put(
            EventAttendanceController.update(eventId),
            { response: value },
            options,
        );
    };

    const trigger = (
        <Button
            variant="outline"
            className="min-w-36 justify-start rounded-r-none"
            disabled={isHost || (!isOpen && auth.user !== null) || isSaving}
            aria-label={`Your response: ${label}`}
            data-test="event-response"
        >
            <Icon />
            <span className="flex-1 text-left">{label}</span>
            {!isHost && <ChevronDown className="text-muted-foreground" />}
        </Button>
    );

    return (
        <div className={cn('inline-flex', className)}>
            {auth.user === null ? (
                <Button
                    variant="outline"
                    className="min-w-36 justify-start rounded-r-none"
                    asChild
                >
                    <Link href={login()}>
                        <X />
                        <span className="flex-1 text-left">Not going</span>
                        <ChevronDown className="text-muted-foreground" />
                    </Link>
                </Button>
            ) : isHost || !isOpen ? (
                trigger
            ) : (
                <DropdownMenu modal={false}>
                    <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-72">
                        {/* Radio items, so the current choice is announced as checked rather than only drawn. */}
                        <DropdownMenuRadioGroup
                            value={response ?? 'none'}
                            onValueChange={(value) =>
                                choose(
                                    value === 'none'
                                        ? null
                                        : (value as EventResponse),
                                )
                            }
                        >
                            {choices.map((choice) => (
                                <DropdownMenuRadioItem
                                    key={choice.label}
                                    value={choice.value ?? 'none'}
                                    className="items-start py-2"
                                    data-test={`event-response-${choice.value ?? 'none'}`}
                                >
                                    <span>
                                        <span className="block font-medium">
                                            {choice.label}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {choice.description}
                                        </span>
                                    </span>
                                </DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
            <a
                href="#going"
                className="inline-flex h-9 min-w-10 items-center justify-center rounded-r-md border border-l-0 px-3 font-mono text-[13px] font-medium tabular-nums shadow-xs transition-colors hover:bg-accent"
                title={exactCountTitle(goingCount)}
                aria-label={`${goingCount} going`}
                data-test="event-going-count"
            >
                {formatCount(goingCount)}
            </a>
        </div>
    );
}
