import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import InputError from '@/components/input-error';
import { MentionTextarea } from '@/components/mention-textarea';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import { timeZoneLabel, wallTimeToInstant } from '@/lib/event';
import { characterCount, cn } from '@/lib/utils';

const inputClass =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';

type AgendaDraft = { label: string; starts_at: string };

export default function Create({
    limits,
}: {
    limits: {
        title: number;
        body: number;
        location: number;
        url: number;
        agendaItems: number;
        agendaLabel: number;
        durationDays: number;
    };
}) {
    const { auth } = usePage().props;
    const now = useNow();
    // Times are entered on the clock the event pages show them on: the profile's time zone, else the browser's.
    const timeZone = auth.user?.timezone ?? undefined;
    const zoneName =
        timeZone ?? Intl.DateTimeFormat().resolvedOptions().timeZone;
    const zoneLabel = timeZoneLabel(now, timeZone);
    const toInstant = (value: string) => wallTimeToInstant(value, timeZone);
    const form = useForm({
        title: '',
        body: '',
        starts_at: '',
        ends_at: '',
        location: '',
        url: '',
        agenda: [] as AgendaDraft[],
    });
    const { data, setData, errors, processing } = form;
    const errorFor = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((values) => ({
            ...values,
            starts_at: toInstant(values.starts_at),
            ends_at: toInstant(values.ends_at),
            location: values.location.trim() === '' ? null : values.location,
            url: values.url.trim() === '' ? null : values.url,
            agenda: values.agenda.map((item) => ({
                label: item.label,
                starts_at: toInstant(item.starts_at),
            })),
        }));
        form.post(EventController.store.url());
    };

    const updateAgenda = (index: number, item: Partial<AgendaDraft>) =>
        setData(
            'agenda',
            data.agenda.map((existing, position) =>
                position === index ? { ...existing, ...item } : existing,
            ),
        );

    return (
        <>
            <Head title="New event" />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <Link
                    href={EventController.index()}
                    aria-label="Back to events"
                    className="text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <h1 className="font-semibold">New event</h1>
            </div>

            <form onSubmit={submit} className="grid gap-6 px-4 py-5 sm:px-6">
                <div className="grid gap-2">
                    <Label htmlFor="event-title">Title</Label>
                    <input
                        id="event-title"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                        maxLength={limits.title}
                        required
                        autoFocus
                        placeholder="Laravel Lisbon meetup #12"
                        className={`${inputClass} h-10 text-base md:text-base`}
                        data-test="event-title-input"
                    />
                    <InputError message={errors.title} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="event-body">Description</Label>
                    <MentionTextarea
                        id="event-body"
                        value={data.body}
                        onValueChange={(value) => setData('body', value)}
                        rows={6}
                        placeholder="What's happening, who it's for, what to bring. It's also the commit announcing your event."
                        className="block min-h-32 w-full resize-y overflow-x-hidden rounded-md border bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 md:text-[15px]"
                        data-test="event-body-input"
                    />
                    <div className="flex justify-between gap-3 text-sm text-muted-foreground">
                        <span>
                            Replies to it become the event's discussion.
                        </span>
                        <span className="font-mono text-xs tabular-nums">
                            {limits.body - characterCount(data.body)}
                        </span>
                    </div>
                    <InputError message={errors.body} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid content-start gap-2">
                        <Label htmlFor="event-starts-at">Starts</Label>
                        <input
                            id="event-starts-at"
                            type="datetime-local"
                            value={data.starts_at}
                            onChange={(event) =>
                                setData('starts_at', event.target.value)
                            }
                            required
                            className={inputClass}
                            data-test="event-starts-at-input"
                        />
                        <InputError message={errors.starts_at} />
                    </div>
                    <div className="grid content-start gap-2">
                        <Label htmlFor="event-ends-at">Ends</Label>
                        <input
                            id="event-ends-at"
                            type="datetime-local"
                            value={data.ends_at}
                            min={data.starts_at || undefined}
                            onChange={(event) =>
                                setData('ends_at', event.target.value)
                            }
                            required
                            className={inputClass}
                            data-test="event-ends-at-input"
                        />
                        <InputError message={errors.ends_at} />
                    </div>
                    <p
                        className="text-xs text-muted-foreground sm:col-span-2"
                        data-test="event-time-zone"
                    >
                        Times are in {zoneName}
                        {zoneLabel === zoneName ? '' : ` (${zoneLabel})`}.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid content-start gap-2">
                        <Label htmlFor="event-location">Place</Label>
                        <input
                            id="event-location"
                            value={data.location}
                            onChange={(event) =>
                                setData('location', event.target.value)
                            }
                            maxLength={limits.location}
                            placeholder="Venue, city"
                            className={inputClass}
                            data-test="event-location-input"
                        />
                        <InputError message={errors.location} />
                    </div>
                    <div className="grid content-start gap-2">
                        <Label htmlFor="event-url">Link</Label>
                        <input
                            id="event-url"
                            type="url"
                            value={data.url}
                            onChange={(event) =>
                                setData('url', event.target.value)
                            }
                            maxLength={limits.url}
                            placeholder="https://…"
                            className={inputClass}
                            data-test="event-url-input"
                        />
                        <InputError message={errors.url} />
                    </div>
                    <p className="text-sm text-muted-foreground sm:col-span-2">
                        A place for an in-person event, a link for an online
                        one, or both for a hybrid.
                    </p>
                </div>

                <fieldset className="grid gap-3">
                    <legend className="text-sm leading-none font-medium">
                        Agenda{' '}
                        <span className="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </legend>
                    <p className="text-sm text-muted-foreground">
                        Talks are ticked off as the event goes, like issues in a
                        milestone.
                    </p>
                    {data.agenda.map((item, index) => (
                        <div key={index} className="grid gap-1.5">
                            <div className="grid grid-cols-[1fr_auto] gap-2 sm:grid-cols-[13rem_1fr_auto]">
                                <input
                                    type="datetime-local"
                                    aria-label={`Agenda item ${index + 1} time`}
                                    value={item.starts_at}
                                    min={data.starts_at || undefined}
                                    max={data.ends_at || undefined}
                                    onChange={(event) =>
                                        updateAgenda(index, {
                                            starts_at: event.target.value,
                                        })
                                    }
                                    required
                                    className={cn(inputClass, 'row-start-1')}
                                />
                                <input
                                    aria-label={`Agenda item ${index + 1}`}
                                    value={item.label}
                                    onChange={(event) =>
                                        updateAgenda(index, {
                                            label: event.target.value,
                                        })
                                    }
                                    maxLength={limits.agendaLabel}
                                    required
                                    placeholder="Talk title, @speaker"
                                    className={cn(
                                        inputClass,
                                        'col-span-2 row-start-2 sm:col-span-1 sm:row-start-1',
                                    )}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="col-start-2 row-start-1 sm:col-start-3"
                                    aria-label={`Remove agenda item ${index + 1}`}
                                    onClick={() =>
                                        setData(
                                            'agenda',
                                            data.agenda.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                            <InputError
                                message={
                                    errorFor(`agenda.${index}.starts_at`) ??
                                    errorFor(`agenda.${index}.label`)
                                }
                            />
                        </div>
                    ))}
                    {data.agenda.length < limits.agendaItems && (
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit"
                            onClick={() =>
                                setData('agenda', [
                                    ...data.agenda,
                                    {
                                        label: '',
                                        starts_at:
                                            data.agenda.at(-1)?.starts_at ||
                                            data.starts_at,
                                    },
                                ])
                            }
                            data-test="add-agenda-item"
                        >
                            <Plus />
                            Add to agenda
                        </Button>
                    )}
                    <InputError message={errors.agenda} />
                </fieldset>

                <div className="flex items-center justify-end gap-2 border-t pt-4">
                    <Button variant="ghost" asChild>
                        <Link href={EventController.index()}>Cancel</Link>
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="create-event"
                    >
                        Create event
                    </Button>
                </div>
            </form>
        </>
    );
}

Create.layout = AppLayout;
