import { ImagePlus, X } from 'lucide-react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import type { EventDraft } from '@/hooks/use-event-draft';
import { cn } from '@/lib/utils';
import type { EventFormat } from '@/types';

const formats: { value: EventFormat; label: string }[] = [
    { value: 'onsite', label: 'On-site' },
    { value: 'online', label: 'Online' },
    { value: 'hybrid', label: 'Hybrid' },
];

const platforms = [
    { value: 'youtube', label: 'YouTube' },
    { value: 'zoom', label: 'Zoom' },
    { value: 'meet', label: 'Google Meet' },
    { value: 'twitch', label: 'Twitch' },
    { value: 'other', label: 'Other' },
];

const input =
    'h-11 w-full rounded-md border border-input bg-background px-2.5 text-base outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60 sm:h-9 sm:text-[15px]';

const label = 'text-[13px] font-medium text-muted-foreground';

/**
 * Every zone the browser knows, keeping the current one even when the browser reports a legacy name for it.
 */
function timeZones(current: string): string[] {
    const zones = Intl.supportedValuesOf('timeZone');

    return zones.includes(current) ? zones : [current, ...zones];
}

export function EventFormFields({
    draft,
    onChange,
    errors,
    titleLocked = false,
    coverLocked = false,
    currentCoverUrl = null,
}: {
    draft: EventDraft;
    onChange: (draft: EventDraft) => void;
    errors: Record<string, string | undefined>;
    titleLocked?: boolean;
    coverLocked?: boolean;
    currentCoverUrl?: string | null;
}) {
    const set = <K extends keyof EventDraft>(key: K, value: EventDraft[K]) =>
        onChange({ ...draft, [key]: value });

    return (
        <div className="flex flex-col gap-3">
            {!coverLocked && (
                <div>
                    <CoverPicker
                        file={draft.cover}
                        currentUrl={currentCoverUrl}
                        onChange={(file) => set('cover', file)}
                    />
                    <InputError message={errors['event.cover']} />
                </div>
            )}

            <label className="flex flex-col gap-1">
                <span className={label}>Title</span>
                <input
                    className={input}
                    value={draft.title}
                    maxLength={120}
                    disabled={titleLocked}
                    onChange={(event) => set('title', event.target.value)}
                />
                <InputError message={errors['event.title']} />
            </label>

            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                {(['starts', 'ends'] as const).map((edge) => {
                    const name = edge === 'starts' ? 'Start' : 'End';

                    return (
                        <div key={edge} className="flex flex-col gap-1">
                            <span className={label}>
                                {edge === 'starts' ? 'Starts' : 'Ends'}
                            </span>
                            <div className="flex gap-1.5">
                                <input
                                    type="date"
                                    aria-label={`${name} date`}
                                    className={cn(input, 'min-w-0 flex-1')}
                                    value={draft[`${edge}Date`]}
                                    onChange={(event) =>
                                        set(`${edge}Date`, event.target.value)
                                    }
                                />
                                <input
                                    type="time"
                                    aria-label={`${name} time`}
                                    className={cn(
                                        input,
                                        'w-28 font-mono sm:w-24 sm:text-[13px]',
                                    )}
                                    value={draft[`${edge}Time`]}
                                    onChange={(event) =>
                                        set(`${edge}Time`, event.target.value)
                                    }
                                />
                            </div>
                            <InputError message={errors[`event.${edge}_at`]} />
                        </div>
                    );
                })}
            </div>
            <label className="-mt-1 flex items-center gap-2 font-mono text-xs text-muted-foreground">
                <span className="sr-only">Time zone</span>
                <select
                    value={draft.timezone}
                    onChange={(event) => set('timezone', event.target.value)}
                    className="max-w-full rounded-md bg-transparent py-1 font-mono text-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    {timeZones(draft.timezone).map((zone) => (
                        <option key={zone} value={zone}>
                            {zone}
                        </option>
                    ))}
                </select>
            </label>
            <InputError message={errors['event.timezone']} />

            <div
                role="radiogroup"
                aria-label="Format"
                className="flex gap-[3px] rounded-lg border p-[3px]"
            >
                {formats.map((format) => (
                    <button
                        key={format.value}
                        type="button"
                        role="radio"
                        aria-checked={draft.format === format.value}
                        onClick={() => set('format', format.value)}
                        className={cn(
                            'h-10 flex-1 rounded-md text-sm font-medium sm:h-8',
                            draft.format === format.value
                                ? 'bg-primary-soft text-primary'
                                : 'text-muted-foreground',
                        )}
                    >
                        {format.label}
                    </button>
                ))}
            </div>

            {draft.format !== 'online' && (
                <>
                    <label className="flex flex-col gap-1">
                        <span className={label}>Venue</span>
                        <input
                            className={input}
                            maxLength={120}
                            value={draft.venue}
                            onChange={(event) =>
                                set('venue', event.target.value)
                            }
                        />
                        <InputError message={errors['event.venue']} />
                    </label>
                    <label className="flex flex-col gap-1">
                        <span className={label}>Address</span>
                        <input
                            className={input}
                            maxLength={200}
                            value={draft.address}
                            onChange={(event) =>
                                set('address', event.target.value)
                            }
                        />
                        <InputError message={errors['event.address']} />
                    </label>
                </>
            )}

            {draft.format !== 'onsite' && (
                <>
                    <div className="flex flex-col gap-1.5">
                        <span className={label}>Where it streams</span>
                        <div className="flex flex-wrap gap-1.5">
                            {platforms.map((platform) => (
                                <button
                                    key={platform.value}
                                    type="button"
                                    aria-pressed={
                                        draft.platform === platform.value
                                    }
                                    onClick={() =>
                                        set('platform', platform.value)
                                    }
                                    className={cn(
                                        'h-9 rounded-md border px-3 text-[13px] font-medium sm:h-[30px]',
                                        draft.platform === platform.value
                                            ? 'border-primary bg-primary-soft text-primary'
                                            : 'border-input',
                                    )}
                                >
                                    {platform.label}
                                </button>
                            ))}
                        </div>
                        <InputError message={errors['event.platform']} />
                    </div>
                    <label className="flex flex-col gap-1">
                        <span className={label}>Join link</span>
                        <input
                            type="url"
                            inputMode="url"
                            placeholder="https://"
                            className={cn(input, 'font-mono sm:text-[13px]')}
                            value={draft.joinUrl}
                            onChange={(event) =>
                                set('joinUrl', event.target.value)
                            }
                        />
                        <InputError message={errors['event.join_url']} />
                    </label>
                </>
            )}
        </div>
    );
}

/**
 * Shows the picked image (or the event's current cover) at 16:9, the way it will look on the event.
 */
function CoverPicker({
    file,
    currentUrl,
    onChange,
}: {
    file: File | null;
    currentUrl: string | null;
    onChange: (file: File | null) => void;
}) {
    // Not revoked: StrictMode replays effect cleanups, which would break a URL still on screen; one blob URL per picked file is negligible.
    const previewUrl = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    const shownUrl = previewUrl ?? currentUrl;
    const input = (
        <input
            type="file"
            accept="image/jpeg,image/png,image/gif,image/webp"
            className="sr-only"
            onChange={(event) => onChange(event.target.files?.[0] ?? null)}
        />
    );

    if (!shownUrl) {
        return (
            <label className="flex h-28 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-[1.5px] border-dashed border-input bg-muted/40 text-sm font-medium text-muted-foreground">
                <ImagePlus className="size-5" />
                Add a cover
                <span className="font-mono text-xs font-normal">
                    16:9 · jpg, png, webp · up to 10 MB
                </span>
                {input}
            </label>
        );
    }

    return (
        <div className="relative overflow-hidden rounded-lg border">
            <img
                src={shownUrl}
                alt="Cover preview"
                className="aspect-video w-full bg-muted object-cover"
            />
            <div className="absolute top-2 left-2 flex gap-1.5">
                <label className="cursor-pointer rounded-md bg-black/70 px-2.5 py-1.5 text-[13px] font-medium text-white backdrop-blur-sm hover:bg-black/80">
                    Change cover
                    {input}
                </label>
                {file && (
                    <button
                        type="button"
                        aria-label="Remove cover"
                        onClick={() => onChange(null)}
                        className="rounded-md bg-black/70 p-1.5 text-white backdrop-blur-sm hover:bg-black/80"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>
        </div>
    );
}
