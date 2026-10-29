import { Link, usePage } from '@inertiajs/react';
import {
    Mic,
    MicOff,
    MonitorUp,
    Phone,
    PhoneOff,
    Video,
    VideoOff,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { toast } from 'sonner';
import ConversationController from '@/actions/App/Http/Controllers/ConversationController';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    acceptCall,
    callConsolesSnapshot,
    callSnapshot,
    declineCall,
    dismissCall,
    hangUp,
    showCallConsole,
    startCall,
    subscribeToCall,
    toggleCamera,
    toggleMicrophone,
    watchIncomingCalls,
} from '@/lib/call';
import type { CallState } from '@/lib/call';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

function useCall(): CallState {
    return useSyncExternalStore(subscribeToCall, callSnapshot, callSnapshot);
}

function isBusy(call: CallState): boolean {
    return call.status !== 'idle' && call.status !== 'ended';
}

/**
 * Sits next to Ping in a direct remote's composer and asks the composer to confirm the call in place.
 */
export function CallButton({
    partner,
    isCompact,
    onClick,
}: {
    partner: User;
    isCompact: boolean;
    onClick: () => void;
}) {
    const call = useCall();
    const { auth } = usePage().props;
    const label = `Call @${partner.handle}`;

    if (!auth.remotes?.calls) {
        return null;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="-mx-1 inline-flex shrink-0">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={isBusy(call)}
                        onClick={onClick}
                        aria-label={label}
                        className={cn(
                            'text-muted-foreground',
                            isCompact ? 'size-9' : 'size-10',
                        )}
                        data-test="call"
                    >
                        <Phone className="size-5" />
                    </Button>
                </span>
            </TooltipTrigger>
            <TooltipContent>
                {isBusy(call) ? 'Already on a call' : label}
            </TooltipContent>
        </Tooltip>
    );
}

/**
 * The call with this partner, on top of the composer: the confirmation before calling, the ringing either way, and
 * the call itself, so the text of the call still goes through the remote right below it.
 */
export function CallConsole({
    partner,
    isConfirming,
    onConfirmingChange,
}: {
    partner: User;
    isConfirming: boolean;
    onConfirmingChange: (isConfirming: boolean) => void;
}) {
    const call = useCall();
    const handle = `@${partner.handle}`;
    const isThisCall =
        call.status !== 'idle' && call.partner.handle === partner.handle;

    useEffect(() => showCallConsole(partner.handle), [partner.handle]);

    if (isConfirming && !isBusy(call)) {
        return (
            <Console data-test="call-confirm">
                <Row>
                    <span className="min-w-0 flex-1 truncate font-semibold">
                        Call {handle}?
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onConfirmingChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        autoFocus
                        onClick={() => {
                            onConfirmingChange(false);
                            void startCall(partner);
                        }}
                        className="min-w-0"
                        data-test="confirm-call"
                    >
                        <span className="truncate">Call</span>
                    </Button>
                </Row>
            </Console>
        );
    }

    if (!isThisCall) {
        return null;
    }

    if (call.status === 'ringing') {
        return (
            <Console tone="primary" data-test="call-incoming">
                <Row>
                    <Dot className="animate-pulse bg-primary" />
                    <span className="min-w-0 flex-1 truncate">
                        <span className="font-semibold">{handle}</span> is
                        calling
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        autoFocus
                        onClick={declineCall}
                    >
                        Decline
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => void acceptCall()}
                        data-test="accept-call"
                    >
                        Accept
                    </Button>
                </Row>
            </Console>
        );
    }

    if (call.status === 'calling' || call.status === 'connecting') {
        return (
            <Console data-test="call-ringing">
                <Row>
                    <Dot className="animate-pulse bg-primary" />
                    <span className="min-w-0 flex-1 truncate">
                        {call.status === 'calling' ? (
                            <>
                                <span className="font-semibold">
                                    Ringing {handle}…
                                </span>{' '}
                                <Elapsed
                                    since={call.since}
                                    className="text-muted-foreground"
                                />
                            </>
                        ) : (
                            <span className="font-semibold">Connecting…</span>
                        )}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={hangUp}
                        data-test="hang-up"
                    >
                        Cancel
                    </Button>
                </Row>
            </Console>
        );
    }

    if (call.status === 'ended') {
        return (
            <Console data-test="call-ended">
                <Row>
                    <Dot className="bg-muted-foreground" />
                    <span className="min-w-0 flex-1 truncate">
                        {call.reason}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Dismiss"
                        onClick={dismissCall}
                        className="size-8"
                    >
                        <X className="size-4" />
                    </Button>
                </Row>
            </Console>
        );
    }

    const hasVideo = call.camera !== null || call.isPartnerCameraOn;

    return (
        <Console data-test="call-connected">
            {hasVideo && (
                <div className="grid grid-cols-2 gap-2">
                    <CallVideo
                        stream={
                            call.isPartnerCameraOn ? call.partnerCamera : null
                        }
                        label={`${handle} · ${call.isPartnerCameraOn ? 'cam' : 'cam off'}`}
                        className="ring-2 ring-primary/60 ring-inset"
                    />
                    <CallVideo
                        stream={call.camera}
                        label={
                            call.camera !== null ? 'you · cam' : 'you · cam off'
                        }
                        isMirrored
                    />
                </div>
            )}
            <Row>
                <Dot className="bg-success" />
                <span className="min-w-0 flex-1 truncate">
                    <Elapsed since={call.startedAt} className="font-medium" />
                    {call.code !== '' && (
                        <span
                            className="ml-2 font-mono text-xs text-muted-foreground"
                            title={`Read this out to ${handle}. If you both see the same code, nobody is listening in between.`}
                        >
                            fp {call.code}
                        </span>
                    )}
                </span>
                <CallControls call={call} />
            </Row>
        </Console>
    );
}

/**
 * The call on every page where its remote isn't showing it, so it can be picked up, muted or ended from anywhere.
 */
export function CallBar({ user }: { user: User }) {
    const call = useCall();
    const consoles = useSyncExternalStore(
        subscribeToCall,
        callConsolesSnapshot,
        callConsolesSnapshot,
    );

    useEffect(() => watchIncomingCalls(user.id), [user.id]);

    if (call.status === 'idle' || consoles.includes(call.partner.handle)) {
        return null;
    }

    const handle = `@${call.partner.handle}`;
    const remote = ConversationController.show.url({
        partner: call.partner.handle,
    });

    return (
        <section
            aria-label="Call"
            aria-live={call.status === 'ringing' ? 'assertive' : 'polite'}
            className="fixed inset-x-4 top-3 z-50 mx-auto flex max-w-md flex-col gap-2 rounded-lg border bg-popover p-3 text-popover-foreground shadow-lg"
            data-test="call-bar"
        >
            <Row>
                <Dot
                    className={cn({
                        'animate-pulse bg-primary':
                            call.status === 'ringing' ||
                            call.status === 'calling' ||
                            call.status === 'connecting',
                        'bg-success': call.status === 'connected',
                        'bg-muted-foreground': call.status === 'ended',
                    })}
                />
                <p className="min-w-0 flex-1 truncate" title={handle}>
                    {call.status === 'ringing' && (
                        <>
                            <span className="font-semibold">{handle}</span> is
                            calling
                        </>
                    )}
                    {call.status === 'calling' && <>Ringing {handle}…</>}
                    {call.status === 'connecting' && <>Connecting…</>}
                    {call.status === 'connected' && (
                        <>
                            {handle}{' '}
                            <Elapsed
                                since={call.startedAt}
                                className="text-muted-foreground"
                            />
                        </>
                    )}
                    {call.status === 'ended' && call.reason}
                </p>
                {call.status === 'ringing' && (
                    <>
                        <Button
                            variant="outline"
                            size="sm"
                            autoFocus
                            onClick={declineCall}
                        >
                            Decline
                        </Button>
                        <Button
                            size="sm"
                            onClick={() => void acceptCall()}
                            data-test="accept-call"
                        >
                            Accept
                        </Button>
                    </>
                )}
                {(call.status === 'calling' ||
                    call.status === 'connecting') && (
                    <Button variant="outline" size="sm" onClick={hangUp}>
                        Cancel
                    </Button>
                )}
                {call.status === 'connected' && (
                    <>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={remote}>Open remote</Link>
                        </Button>
                        <CallControls call={call} />
                    </>
                )}
                {call.status === 'ended' && (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Dismiss"
                        onClick={dismissCall}
                        className="size-8"
                    >
                        <X className="size-4" />
                    </Button>
                )}
            </Row>
        </section>
    );
}

function CallControls({
    call,
}: {
    call: Extract<CallState, { status: 'connected' }>;
}) {
    return (
        <div className="flex shrink-0 items-center gap-1">
            <Control
                label={
                    call.isMicMuted ? 'Unmute microphone' : 'Mute microphone'
                }
                isPressed={call.isMicMuted}
                onClick={toggleMicrophone}
            >
                {call.isMicMuted ? (
                    <MicOff className="size-4" />
                ) : (
                    <Mic className="size-4" />
                )}
            </Control>
            <Control
                label={
                    call.camera !== null ? 'Turn camera off' : 'Turn camera on'
                }
                isPressed={call.camera !== null}
                onClick={() =>
                    void toggleCamera().then(
                        (error) => error !== null && toast.error(error),
                    )
                }
            >
                {call.camera !== null ? (
                    <Video className="size-4" />
                ) : (
                    <VideoOff className="size-4" />
                )}
            </Control>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            disabled
                            aria-label="Share screen, coming soon"
                            className="size-8"
                        >
                            <MonitorUp className="size-4" />
                        </Button>
                    </span>
                </TooltipTrigger>
                <TooltipContent>Screen sharing is coming soon</TooltipContent>
            </Tooltip>
            <Button
                type="button"
                variant="destructive"
                size="sm"
                onClick={hangUp}
                data-test="hang-up"
            >
                <PhoneOff className="size-4" />
                Hang up
            </Button>
        </div>
    );
}

function Control({
    label,
    isPressed,
    onClick,
    children,
}: {
    label: string;
    isPressed: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={label}
                    aria-pressed={isPressed}
                    onClick={onClick}
                    className="size-8 aria-pressed:border-primary aria-pressed:bg-primary-soft aria-pressed:text-primary"
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

function CallVideo({
    stream,
    label,
    isMirrored = false,
    className,
}: {
    stream: MediaStream | null;
    label: string;
    isMirrored?: boolean;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative aspect-video overflow-hidden rounded-md bg-neutral-900',
                className,
            )}
        >
            {stream !== null && (
                <video
                    ref={(video) => {
                        if (video !== null && video.srcObject !== stream) {
                            video.srcObject = stream;
                        }
                    }}
                    autoPlay
                    playsInline
                    muted
                    className={cn('size-full object-cover', {
                        '-scale-x-100': isMirrored,
                    })}
                />
            )}
            <span className="absolute bottom-1.5 left-1.5 max-w-[calc(100%-0.75rem)] truncate rounded bg-black/60 px-1.5 py-0.5 font-mono text-[11px] text-white">
                {label}
            </span>
        </div>
    );
}

function Console({
    tone,
    children,
    ...props
}: {
    tone?: 'primary';
    children: ReactNode;
    'data-test'?: string;
}) {
    return (
        <section
            aria-label="Call"
            aria-live="polite"
            className={cn(
                'flex flex-col gap-2 rounded-lg border bg-background p-2.5 text-sm',
                {
                    'border-primary bg-primary-soft': tone === 'primary',
                },
            )}
            {...props}
        >
            {children}
        </section>
    );
}

function Row({ children }: { children: ReactNode }) {
    return <div className="flex min-h-8 items-center gap-2">{children}</div>;
}

function Dot({ className }: { className?: string }) {
    return (
        <span
            aria-hidden
            className={cn('size-2 shrink-0 rounded-full', className)}
        />
    );
}

function Elapsed({ since, className }: { since: number; className?: string }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const interval = window.setInterval(() => setNow(Date.now()), 1_000);

        return () => window.clearInterval(interval);
    }, []);

    const seconds = Math.max(0, Math.floor((now - since) / 1_000));

    return (
        <span className={cn('font-mono tabular-nums', className)}>
            {String(Math.floor(seconds / 60)).padStart(2, '0')}:
            {String(seconds % 60).padStart(2, '0')}
        </span>
    );
}
