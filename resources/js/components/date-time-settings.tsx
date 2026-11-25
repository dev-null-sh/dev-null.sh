import { Form, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import UserDateTimeController from '@/actions/App/Http/Controllers/UserDateTimeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useNow } from '@/hooks/use-now';
import { createDateTime } from '@/lib/date-time';
import type { DateTimePreferences } from '@/lib/date-time';

export default function DateTimeSettings() {
    const saved = usePage().props.auth.dateTime;
    const [dateFormat, setDateFormat] = useState(saved.dateFormat);
    const [timeFormat, setTimeFormat] = useState(saved.timeFormat);
    const now = useNow();

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Date & time"
                description="Choose how dates and times appear to you. These preferences are private."
            />

            <Form
                {...UserDateTimeController.update.form()}
                options={{ preserveScroll: true }}
                transform={(data) => ({
                    ...data,
                    date_format:
                        data.date_format === 'browser'
                            ? null
                            : data.date_format,
                    time_format:
                        data.time_format === 'browser'
                            ? null
                            : data.time_format,
                })}
                onSuccess={() => router.flushAll()}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid max-w-md gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="date-format">Date format</Label>
                                <Select
                                    name="date_format"
                                    value={dateFormat ?? 'browser'}
                                    onValueChange={(value) =>
                                        setDateFormat(
                                            value === 'browser'
                                                ? null
                                                : ((value as DateTimePreferences['dateFormat']) ??
                                                      null),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="date-format"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent
                                        avoidCollisions
                                        collisionPadding={16}
                                    >
                                        <SelectItem value="browser">
                                            Browser default
                                        </SelectItem>
                                        <SelectItem value="month_first">
                                            Oct 19
                                        </SelectItem>
                                        <SelectItem value="day_first">
                                            19 Oct
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.date_format} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="time-format">Time format</Label>
                                <Select
                                    name="time_format"
                                    value={timeFormat ?? 'browser'}
                                    onValueChange={(value) =>
                                        setTimeFormat(
                                            value === 'browser'
                                                ? null
                                                : ((value as DateTimePreferences['timeFormat']) ??
                                                      null),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="time-format"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent
                                        avoidCollisions
                                        collisionPadding={16}
                                    >
                                        <SelectItem value="browser">
                                            Browser default
                                        </SelectItem>
                                        <SelectItem value="12h">
                                            12-hour
                                        </SelectItem>
                                        <SelectItem value="24h">
                                            24-hour
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.time_format} />
                            </div>
                        </div>
                        <p className="mt-3 text-sm text-muted-foreground">
                            Choosing “Browser default” uses your browser’s date
                            order and clock format.
                        </p>
                        <p
                            className="mt-3 font-mono text-sm tabular-nums"
                            aria-live="polite"
                            data-test="date-time-preview"
                        >
                            {createDateTime({
                                dateFormat,
                                timeFormat,
                            }).formatFullDateTime(now.toISOString())}
                        </p>
                        <Button
                            type="submit"
                            className="mt-5"
                            disabled={
                                processing ||
                                (dateFormat === saved.dateFormat &&
                                    timeFormat === saved.timeFormat)
                            }
                            data-test="save-date-time-button"
                        >
                            Save date & time
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
