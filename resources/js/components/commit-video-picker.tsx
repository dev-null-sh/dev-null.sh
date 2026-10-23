import { Check, LoaderCircle, Video, X } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { ActionTooltip } from '@/components/action-tooltip';
import { CommitVideo } from '@/components/commit-video';
import { Button } from '@/components/ui/button';
import type { useCommitVideo } from '@/hooks/use-commit-video';
import { cn } from '@/lib/utils';

export function VideoPickerButton({
    onPick,
    disabled,
}: {
    onPick: (file: File) => Promise<void>;
    disabled: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    return (
        <>
            <input
                ref={input}
                type="file"
                accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm"
                className="sr-only"
                tabIndex={-1}
                aria-label="Choose video"
                disabled={disabled}
                onChange={(event) => {
                    const selected = event.target.files?.[0];
                    event.target.value = '';
                    if (selected) void onPick(selected);
                }}
            />
            <ActionTooltip label="Attach a video up to 60 seconds / 100 MB">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Add video"
                    className="text-muted-foreground"
                    disabled={disabled}
                    onClick={() => input.current?.click()}
                >
                    <Video className="size-5" />
                </Button>
            </ActionTooltip>
        </>
    );
}

export function CommitVideoPicker({
    attachment,
    processing,
}: {
    attachment: ReturnType<typeof useCommitVideo>;
    processing: boolean;
}) {
    const { file, video, ready, error, progress } = attachment;
    const id = useId();
    const [describing, setDescribing] = useState(false);
    const described = attachment.description.trim() !== '';
    return (
        <>
            {file && (
                <section
                    aria-label="Video attachment"
                    className="my-3 overflow-hidden rounded-lg border"
                >
                    {ready && video ? (
                        <CommitVideo
                            className="mt-0 rounded-none border-0"
                            video={{
                                ...video,
                                description: null,
                            }}
                        />
                    ) : (
                        <div
                            className="flex max-h-[70dvh] flex-col items-center justify-center gap-2 bg-muted text-muted-foreground"
                            style={{ aspectRatio: attachment.aspectRatio }}
                        >
                            {error ? (
                                <Video className="size-7" />
                            ) : (
                                <LoaderCircle className="size-7 motion-safe:animate-spin" />
                            )}
                            <p
                                role={error ? 'alert' : 'status'}
                                className={cn(
                                    'px-4 text-center text-sm font-medium',
                                    error && 'text-destructive',
                                )}
                            >
                                {error ??
                                    (video?.status === 'processing'
                                        ? 'Preparing video…'
                                        : 'Uploading video…')}
                            </p>
                            <p className="text-xs">
                                {error
                                    ? 'Remove it to choose another video.'
                                    : video?.status === 'processing'
                                      ? 'Preparing your video for playback'
                                      : 'Keep this tab open. You can keep writing.'}
                            </p>
                        </div>
                    )}
                    <div className="space-y-2 p-3">
                        <div className="flex min-w-0 items-center gap-2">
                            <p
                                className="min-w-0 flex-1 truncate text-sm font-medium"
                                title={file.name}
                            >
                                {file.name}
                            </p>
                            {ready && <Check className="size-4" />}
                            {!error && (
                                <button
                                    type="button"
                                    onClick={() => setDescribing(!describing)}
                                    disabled={processing}
                                    aria-label={
                                        described
                                            ? 'Edit video description'
                                            : 'Add video description'
                                    }
                                    aria-expanded={describing}
                                    aria-controls={`${id}-description`}
                                    className={cn(
                                        'rounded px-1 text-[11px] leading-4 font-bold tracking-wide transition',
                                        described
                                            ? 'bg-foreground text-background'
                                            : 'bg-muted text-foreground hover:bg-accent',
                                    )}
                                >
                                    {described ? 'ALT' : '+ALT'}
                                </button>
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={processing}
                                aria-label="Remove video"
                                onClick={attachment.remove}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                        {!error && (
                            <>
                                <progress
                                    className="h-1 w-full accent-primary"
                                    aria-label="Video upload"
                                    max={100}
                                    value={progress}
                                />
                                <p
                                    role="status"
                                    className="text-xs text-muted-foreground"
                                >
                                    {ready
                                        ? 'Ready to commit. Your video is not public yet.'
                                        : 'Commit becomes available when your video is ready.'}
                                </p>
                            </>
                        )}
                        {describing && !error && (
                            <div
                                id={`${id}-description`}
                                className="rounded-md border bg-background p-2"
                            >
                                <label
                                    htmlFor={`${id}-description-input`}
                                    className="text-xs font-medium text-muted-foreground"
                                >
                                    Description of the video
                                </label>
                                <textarea
                                    id={`${id}-description-input`}
                                    autoFocus
                                    rows={2}
                                    value={attachment.description}
                                    disabled={processing}
                                    maxLength={1000}
                                    onChange={(event) =>
                                        attachment.setDescription(
                                            event.target.value,
                                        )
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Escape') {
                                            event.preventDefault();
                                            event.stopPropagation();
                                            setDescribing(false);
                                        }
                                    }}
                                    placeholder="Describe the video for people who can't see it"
                                    className="mt-1 block field-sizing-content max-h-40 min-h-12 w-full resize-none bg-transparent text-base leading-relaxed outline-none placeholder:text-muted-foreground md:text-sm"
                                />
                                <div className="mt-1 flex items-center justify-end gap-3">
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {attachment.description.length}/1000
                                    </span>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => setDescribing(false)}
                                    >
                                        Done
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            )}
            {!file && error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </>
    );
}
