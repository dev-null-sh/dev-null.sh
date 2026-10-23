import { CalendarClock } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import { cn } from '@/lib/utils';

export const maxScheduleDays = 30;

const hours = Array.from({ length: 24 }, (_, hour) => hour);
const minutes = Array.from({ length: 12 }, (_, index) => index * 5);

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * Writes a date the way a `datetime-local` input does, in the browser's timezone.
 */
export function localDateTime(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * A local time skipped by a daylight saving change, like 02:30 on the night clocks go forward, comes back shifted.
 */
export function isExistingLocalTime(value: string): boolean {
    return localDateTime(new Date(value)) === value;
}

function startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function isSameDay(left: Date, right: Date): boolean {
    return startOfDay(left).getTime() === startOfDay(right).getTime();
}

export function SchedulePicker({
    id,
    value,
    onChange,
    invalid,
    describedBy,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    invalid?: boolean;
    describedBy?: string;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const dateTime = useDateTime();
    const now = useNow();
    const selected = value === '' ? undefined : new Date(value);
    const lastDay = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate() + maxScheduleDays,
    );
    const isToday = selected !== undefined && isSameDay(selected, now);

    const change = (date: Date, hour: number, minute: number) =>
        onChange(
            localDateTime(
                new Date(
                    date.getFullYear(),
                    date.getMonth(),
                    date.getDate(),
                    hour,
                    minute,
                ),
            ),
        );

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                    className={cn(
                        'w-full justify-start font-normal tabular-nums max-md:text-base',
                        { 'text-muted-foreground': selected === undefined },
                    )}
                    data-test="schedule-picker"
                >
                    <CalendarClock aria-hidden="true" />
                    {selected === undefined
                        ? 'Pick a date and time'
                        : `${dateTime.formatDate(selected, { year: false, weekday: true })} · ${dateTime.formatTime(selected)}`}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                collisionPadding={16}
                className="max-h-[calc(100dvh-2rem)] w-auto overflow-y-auto p-0"
            >
                <Calendar
                    mode="single"
                    selected={selected}
                    defaultMonth={selected ?? now}
                    startMonth={now}
                    endMonth={lastDay}
                    disabled={[{ before: startOfDay(now) }, { after: lastDay }]}
                    onSelect={(date) => {
                        if (date === undefined) {
                            return;
                        }

                        if (selected !== undefined) {
                            change(
                                date,
                                selected.getHours(),
                                selected.getMinutes(),
                            );

                            return;
                        }

                        change(
                            date,
                            isSameDay(date, now)
                                ? Math.min(now.getHours() + 1, 23)
                                : 9,
                            0,
                        );
                    }}
                    autoFocus
                />
                <div className="flex items-center gap-2 border-t p-3">
                    <Select
                        value={
                            selected === undefined
                                ? undefined
                                : String(selected.getHours())
                        }
                        disabled={selected === undefined}
                        onValueChange={(hour) =>
                            selected &&
                            change(
                                selected,
                                Number(hour),
                                selected.getMinutes(),
                            )
                        }
                    >
                        <SelectTrigger
                            aria-label="Hour"
                            className="flex-1 tabular-nums"
                        >
                            <SelectValue placeholder="Hour" />
                        </SelectTrigger>
                        <SelectContent
                            className="max-h-60"
                            avoidCollisions
                            collisionPadding={16}
                        >
                            {hours.map((hour) => (
                                <SelectItem
                                    key={hour}
                                    value={String(hour)}
                                    disabled={isToday && hour < now.getHours()}
                                    className="tabular-nums"
                                >
                                    {dateTime.formatHour(hour)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <span aria-hidden="true" className="text-muted-foreground">
                        :
                    </span>
                    <Select
                        value={
                            selected === undefined
                                ? undefined
                                : String(selected.getMinutes())
                        }
                        disabled={selected === undefined}
                        onValueChange={(minute) =>
                            selected &&
                            change(
                                selected,
                                selected.getHours(),
                                Number(minute),
                            )
                        }
                    >
                        <SelectTrigger
                            aria-label="Minute"
                            className="flex-1 tabular-nums"
                        >
                            <SelectValue placeholder="Min" />
                        </SelectTrigger>
                        <SelectContent
                            className="max-h-60"
                            avoidCollisions
                            collisionPadding={16}
                        >
                            {minutes.map((minute) => (
                                <SelectItem
                                    key={minute}
                                    value={String(minute)}
                                    className="tabular-nums"
                                >
                                    {pad(minute)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        size="sm"
                        disabled={selected === undefined}
                        onClick={() => setIsOpen(false)}
                        className="h-9"
                    >
                        Done
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
