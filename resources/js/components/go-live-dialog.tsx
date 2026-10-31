import { Form, router } from '@inertiajs/react';
import { KeyRound, Radio } from 'lucide-react';
import { useState } from 'react';
import UserLiveController from '@/actions/App/Http/Controllers/UserLiveController';
import UserLiveInputController from '@/actions/App/Http/Controllers/UserLiveInputController';
import { CopyableCode } from '@/components/copyable-code';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { LiveInput } from '@/types';

/**
 * Mirrors `User::MAX_LIVE_HOURS`, the longest a stream can be shown as live.
 */
const maxHours = 12;

const defaultHours = 2;

const fieldClassName =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';

type Source = 'here' | 'link';

export function endStream(callbacks?: {
    onStart?: () => void;
    onFinish?: () => void;
}): void {
    router.visit(UserLiveController.destroy(), {
        preserveScroll: true,
        ...callbacks,
        onSuccess: () => router.flushAll(),
    });
}

/**
 * Streaming here is offered where the page knows Cloudflare Stream is set up for you, and sharing a link everywhere.
 */
export function GoLiveDialog({
    open,
    onOpenChange,
    canStreamHere = false,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canStreamHere?: boolean;
}) {
    const [source, setSource] = useState<Source>(
        canStreamHere ? 'here' : 'link',
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Go live</DialogTitle>
                <DialogDescription>
                    {source === 'here'
                        ? 'Stream from OBS or any software that speaks RTMPS, and people watch you right here. Your avatar gets a live marker for as long as you choose.'
                        : 'Share your YouTube or Twitch stream and your avatar gets a live marker that sends people to it, for as long as you choose.'}
                </DialogDescription>

                {canStreamHere && (
                    <div
                        role="radiogroup"
                        aria-label="Where you stream"
                        className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1"
                    >
                        {(
                            [
                                ['here', 'Stream here'],
                                ['link', 'Share a link'],
                            ] as const
                        ).map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={source === value}
                                onClick={() => setSource(value)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors',
                                    source === value &&
                                        'bg-background text-foreground shadow-xs',
                                )}
                                data-test={`live-source-${value}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                )}

                {source === 'here' ? (
                    <StreamHereForm
                        onLive={() => {
                            onOpenChange(false);
                            router.flushAll();
                        }}
                    />
                ) : (
                    <Form
                        {...UserLiveController.update.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            onOpenChange(false);
                            router.flushAll();
                        }}
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="live-url">
                                        Stream link
                                    </Label>
                                    <input
                                        id="live-url"
                                        type="url"
                                        name="url"
                                        required
                                        autoFocus
                                        placeholder="https://www.youtube.com/live/… or https://twitch.tv/…"
                                        className={fieldClassName}
                                        data-test="live-url-input"
                                    />
                                    <InputError message={errors.url} />
                                </div>

                                <HoursField error={errors.hours} />

                                <GoLiveFooter processing={processing} />
                            </div>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * The stream key is fetched on demand and only kept in this dialog, so it never sits in a page someone could share.
 */
function StreamHereForm({ onLive }: { onLive: () => void }) {
    const [liveInput, setLiveInput] = useState<LiveInput | null>(null);
    const [isFetching, setIsFetching] = useState(false);

    const fetchKey = () =>
        router.post(
            UserLiveInputController.store(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setIsFetching(true),
                onFinish: () => setIsFetching(false),
                onSuccess: (page) => setLiveInput(page.flash.liveInput ?? null),
            },
        );

    if (!liveInput) {
        return (
            <div className="grid gap-4">
                <p className="text-sm text-muted-foreground">
                    Your stream key stays the same between streams, so you only
                    set up your streaming software once.
                </p>
                <DialogFooter className="mt-2 gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        onClick={fetchKey}
                        disabled={isFetching}
                        data-test="live-get-key"
                    >
                        <KeyRound />
                        Get stream key
                    </Button>
                </DialogFooter>
            </div>
        );
    }

    return (
        <Form
            {...UserLiveInputController.update.form()}
            options={{ preserveScroll: true }}
            onSuccess={onLive}
            className="min-w-0"
        >
            {({ processing, errors }) => (
                <div className="grid grid-cols-[minmax(0,1fr)] gap-4">
                    <div className="grid grid-cols-[minmax(0,1fr)] gap-2">
                        <Label>Server</Label>
                        <CopyableCode
                            code={liveInput.rtmps_url}
                            label="server"
                        />
                    </div>
                    <div
                        className="grid grid-cols-[minmax(0,1fr)] gap-2"
                        data-test="live-stream-key"
                    >
                        <Label>Stream key</Label>
                        <CopyableCode
                            code={liveInput.stream_key}
                            label="stream key"
                        />
                        <p className="text-xs text-muted-foreground">
                            Anyone with this key can stream as you. Keep it
                            private.
                        </p>
                    </div>

                    <HoursField error={errors.hours} />

                    <GoLiveFooter processing={processing} />
                </div>
            )}
        </Form>
    );
}

/**
 * The slider starts at the default length each time the dialog opens, since the dialog unmounts its content when closed.
 */
function HoursField({ error }: { error?: string }) {
    const [hours, setHours] = useState(defaultHours);
    const hoursLabel = hours === 1 ? '1 hour' : `${hours} hours`;
    const filled = ((hours - 1) / (maxHours - 1)) * 100;

    return (
        <div className="grid gap-3">
            <div className="flex items-baseline justify-between gap-4">
                <Label htmlFor="live-hours">For</Label>
                <output
                    htmlFor="live-hours"
                    className="min-w-[8ch] text-right font-mono text-[13px] text-muted-foreground tabular-nums"
                >
                    {hoursLabel}
                </output>
            </div>
            <input
                id="live-hours"
                type="range"
                name="hours"
                min={1}
                max={maxHours}
                value={hours}
                onChange={(event) =>
                    setHours(event.currentTarget.valueAsNumber)
                }
                aria-valuetext={hoursLabel}
                style={{
                    background: `linear-gradient(to right, var(--primary) ${filled}%, var(--muted) ${filled}%)`,
                }}
                className="h-1.5 w-full cursor-pointer appearance-none rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 [&::-moz-range-thumb]:size-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-primary [&::-moz-range-thumb]:bg-background [&::-moz-range-thumb]:shadow-sm [&::-webkit-slider-thumb]:size-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-primary [&::-webkit-slider-thumb]:bg-background [&::-webkit-slider-thumb]:shadow-sm [&::-webkit-slider-thumb]:transition-transform active:[&::-webkit-slider-thumb]:scale-110 motion-reduce:[&::-webkit-slider-thumb]:transition-none"
                data-test="live-hours-slider"
            />
            <div
                aria-hidden
                className="flex justify-between font-mono text-xs text-muted-foreground"
            >
                <span>1h</span>
                <span>{maxHours}h</span>
            </div>
            <InputError message={error} />
        </div>
    );
}

function GoLiveFooter({ processing }: { processing: boolean }) {
    return (
        <DialogFooter className="mt-2 gap-2">
            <DialogClose asChild>
                <Button type="button" variant="outline">
                    Cancel
                </Button>
            </DialogClose>
            <Button
                type="submit"
                disabled={processing}
                data-test="go-live-button"
            >
                <Radio />
                Go live
            </Button>
        </DialogFooter>
    );
}
