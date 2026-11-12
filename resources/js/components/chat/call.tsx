import { Link, router, usePage } from '@inertiajs/react';
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
import OperationsController from '@/actions/App/Http/Controllers/OperationsController';
import OrgController from '@/actions/App/Http/Controllers/OrgController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
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
import {
    declineOrgCall,
    dismissOrgCall,
    joinOrgCall,
    leaveOrgCall,
    ongoingOrgCallsSnapshot,
    orgCallSnapshot,
    ringingOrgCallSnapshot,
    subscribeToOrgCall,
    toggleOrgCallCamera,
    toggleOrgCallMicrophone,
} from '@/lib/org-call';
import type { OrgCall, OrgCallState } from '@/lib/org-call';
import { cn } from '@/lib/utils';
import type { OrgRef, User } from '@/types';

function useCall(): CallState {
    return useSyncExternalStore(subscribeToCall, callSnapshot, callSnapshot);
}

function useOrgCall(): OrgCallState {
    return useSyncExternalStore(
        subscribeToOrgCall,
        orgCallSnapshot,
        orgCallSnapshot,
    );
}

function useOngoingOrgCalls(): OrgCall[] {
    return useSyncExternalStore(
        subscribeToOrgCall,
        ongoingOrgCallsSnapshot,
        ongoingOrgCallsSnapshot,
    );
}

function useRingingOrgCall(): OrgCall | null {
    return useSyncExternalStore(
        subscribeToOrgCall,
        ringingOrgCallSnapshot,
        ringingOrgCallSnapshot,
    );
}

/**
 * Whether the user is on a call of either kind, which keeps them from starting or joining another.
 */
function useIsBusy(): boolean {
    const call = useCall();
    const orgCall = useOrgCall();

    return (
        (call.status !== 'idle' && call.status !== 'ended') ||
        orgCall.status === 'joining' ||
        orgCall.status === 'joined'
    );
}

/**
 * Sits next to Ping in a remote's composer and asks the composer to confirm the call in place.
 */
export function CallButton({
    label,
    isCompact,
    onClick,
}: {
    label: string;
    isCompact: boolean;
    onClick: () => void;
}) {
    const isOnCall = useIsBusy();
    const { auth } = usePage().props;

    if (!auth.remotes) {
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
                        disabled={isOnCall}
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
                {isOnCall ? 'Already on a call' : label}
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
    const isOnCall = useIsBusy();
    const handle = `@${partner.handle}`;
    const isThisCall =
        call.status !== 'idle' && call.partner.handle === partner.handle;

    useEffect(() => showCallConsole(partner.handle), [partner.handle]);

    if (isConfirming && !isOnCall) {
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
                <CallControls
                    isMicMuted={call.isMicMuted}
                    isCameraOn={call.camera !== null}
                    onMicrophone={toggleMicrophone}
                    onCamera={toggleCamera}
                    onHangUp={hangUp}
                />
            </Row>
        </Console>
    );
}

/**
 * The calls on every page where their remote isn't showing them, so they can be picked up, joined, muted or ended
 * from anywhere.
 */
export function CallBar({ user }: { user: User }) {
    const call = useCall();
    const orgCall = useOrgCall();
    const ringing = useRingingOrgCall();
    const consoles = useSyncExternalStore(
        subscribeToCall,
        callConsolesSnapshot,
        callConsolesSnapshot,
    );

    useEffect(() => watchIncomingCalls(user.id), [user.id]);

    const isDirectShown =
        call.status !== 'idle' && !consoles.includes(call.partner.handle);
    const org =
        orgCall.status !== 'idle' ? orgCall.org : (ringing?.org ?? null);
    const isOrgShown = org !== null && !consoles.includes(`org:${org.id}`);

    if (!isDirectShown && !isOrgShown) {
        return null;
    }

    return (
        <div className="fixed inset-x-4 top-3 z-50 mx-auto flex max-w-md flex-col gap-2">
            {isDirectShown && <DirectCallCard call={call} />}
            {isOrgShown && (
                <OrgCallCard call={orgCall} ringing={ringing} user={user} />
            )}
        </div>
    );
}

function DirectCallCard({
    call,
}: {
    call: Exclude<CallState, { status: 'idle' }>;
}) {
    const handle = `@${call.partner.handle}`;
    const remote = ConversationController.show.url({
        partner: call.partner.handle,
    });

    return (
        <section
            aria-label="Call"
            aria-live={call.status === 'ringing' ? 'assertive' : 'polite'}
            className={barClassName}
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
                        <CallControls
                            isMicMuted={call.isMicMuted}
                            isCameraOn={call.camera !== null}
                            onMicrophone={toggleMicrophone}
                            onCamera={toggleCamera}
                            onHangUp={hangUp}
                        />
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

/**
 * The pages a link can reach from here that don't share this layout. Going to one takes the call bar away, so the
 * call ends with it.
 */
function leavesLayout(path: string): boolean {
    const operations = OperationsController.show.url();

    return path === operations || path.startsWith(`${operations}/`);
}

/**
 * Whether the user is on a call, or placing or joining one. A call only ringing them isn't theirs to lose yet.
 */
function isTalking(call: CallState, orgCall: OrgCallState): boolean {
    return (
        call.status === 'calling' ||
        call.status === 'connecting' ||
        call.status === 'connected' ||
        orgCall.status === 'joining' ||
        orgCall.status === 'joined'
    );
}

/**
 * Warns before anything ends the call: closing, reloading or leaving the app asks the browser to confirm, and
 * opening a page without this layout asks here, then ends the call on the way out.
 */
export function CallLeaveWarning() {
    const call = useCall();
    const orgCall = useOrgCall();
    const isOnCall = isTalking(call, orgCall);
    const [leavingTo, setLeavingTo] = useState<string | null>(null);

    useEffect(() => {
        if (!isOnCall) {
            return;
        }

        const confirmUnload = (event: BeforeUnloadEvent) => {
            if (isTalking(callSnapshot(), orgCallSnapshot())) {
                event.preventDefault();
            }
        };
        window.addEventListener('beforeunload', confirmUnload);

        const stopVisits = router.on('before', ({ detail: { visit } }) => {
            // Read live, as ending the call to leave visits before this unsubscribes.
            if (
                !isTalking(callSnapshot(), orgCallSnapshot()) ||
                visit.prefetch ||
                visit.method !== 'get' ||
                visit.url.origin !== window.location.origin ||
                !leavesLayout(visit.url.pathname)
            ) {
                return;
            }

            setLeavingTo(visit.url.href);

            return false;
        });

        return () => {
            window.removeEventListener('beforeunload', confirmUnload);
            stopVisits();
        };
    }, [isOnCall]);

    return (
        <Dialog
            open={leavingTo !== null}
            onOpenChange={(isOpen) => !isOpen && setLeavingTo(null)}
        >
            <DialogContent data-test="call-leave-warning">
                <DialogTitle>End the call?</DialogTitle>
                <DialogDescription>
                    This page doesn&apos;t keep calls going, so opening it ends
                    the call you&apos;re on.
                </DialogDescription>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Stay on the call</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            const url = leavingTo;
                            hangUp();
                            leaveOrgCall();
                            setLeavingTo(null);

                            if (url !== null) {
                                router.visit(url);
                            }
                        }}
                        data-test="end-call-and-leave"
                    >
                        End call and leave
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const barClassName =
    'flex flex-col gap-2 rounded-lg border bg-popover p-3 text-popover-foreground shadow-lg';

function OrgCallCard({
    call,
    ringing,
    user,
}: {
    call: OrgCallState;
    ringing: OrgCall | null;
    user: User;
}) {
    const org = call.status === 'idle' ? ringing?.org : call.org;

    if (org === undefined) {
        return null;
    }

    const name = orgName(org);
    const remote = OrgController.show.url({ org: org.id });

    return (
        <section
            aria-label="Org call"
            aria-live={call.status === 'idle' ? 'assertive' : 'polite'}
            className={barClassName}
            data-test="org-call-bar"
        >
            <Row>
                <Dot
                    className={cn({
                        'animate-pulse bg-primary':
                            call.status === 'idle' ||
                            call.status === 'joining' ||
                            (call.status === 'joined' &&
                                call.peers.length === 0),
                        'bg-success':
                            call.status === 'joined' && call.peers.length > 0,
                        'bg-muted-foreground': call.status === 'ended',
                    })}
                />
                <p className="min-w-0 flex-1 truncate" title={name}>
                    {call.status === 'idle' && ringing !== null && (
                        <>
                            Call in{' '}
                            <span className="font-semibold">{name}</span>
                            <span className="text-muted-foreground">
                                {' '}
                                ·{' '}
                                {handlesOf(
                                    ringing.members.map(({ user }) => user),
                                )}
                            </span>
                        </>
                    )}
                    {call.status === 'joining' && <>Joining the call…</>}
                    {call.status === 'joined' && (
                        <>
                            {name}{' '}
                            <Elapsed
                                since={call.joinedAt}
                                className="text-muted-foreground"
                            />
                        </>
                    )}
                    {call.status === 'ended' && call.reason}
                </p>
                {call.status === 'idle' && (
                    <>
                        <Button
                            variant="outline"
                            size="sm"
                            autoFocus
                            onClick={declineOrgCall}
                        >
                            Decline
                        </Button>
                        <Button
                            size="sm"
                            onClick={() => void joinOrgCall(org, user.id)}
                            data-test="join-org-call"
                        >
                            Join
                        </Button>
                    </>
                )}
                {call.status === 'joining' && (
                    <Button variant="outline" size="sm" onClick={leaveOrgCall}>
                        Cancel
                    </Button>
                )}
                {call.status === 'joined' && (
                    <>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={remote}>Open remote</Link>
                        </Button>
                        <CallControls
                            isMicMuted={call.isMicMuted}
                            isCameraOn={call.camera !== null}
                            hangUpLabel="Leave"
                            onMicrophone={toggleOrgCallMicrophone}
                            onCamera={toggleOrgCallCamera}
                            onHangUp={leaveOrgCall}
                        />
                    </>
                )}
                {call.status === 'ended' && (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Dismiss"
                        onClick={dismissOrgCall}
                        className="size-8"
                    >
                        <X className="size-4" />
                    </Button>
                )}
            </Row>
        </section>
    );
}

/**
 * The org's call, on top of its composer: starting it, joining it whenever it goes on, and the call itself, with
 * everyone's camera once someone turns theirs on.
 */
export function OrgCallConsole({
    org,
    user,
    isConfirming,
    onConfirmingChange,
}: {
    org: OrgRef;
    user: User;
    isConfirming: boolean;
    onConfirmingChange: (isConfirming: boolean) => void;
}) {
    const call = useOrgCall();
    const isOnCall = useIsBusy();
    const ongoing =
        useOngoingOrgCalls().find((candidate) => candidate.org.id === org.id) ??
        null;
    const ringing = useRingingOrgCall();
    const name = orgName(org);
    const isThisCall = call.status !== 'idle' && call.org.id === org.id;

    useEffect(() => showCallConsole(`org:${org.id}`), [org.id]);

    if (isThisCall && call.status === 'joining') {
        return (
            <Console data-test="org-call-joining">
                <Row>
                    <Dot className="animate-pulse bg-primary" />
                    <span className="min-w-0 flex-1 truncate font-semibold">
                        Joining the call…
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={leaveOrgCall}
                    >
                        Cancel
                    </Button>
                </Row>
            </Console>
        );
    }

    if (isThisCall && call.status === 'ended') {
        return (
            <Console data-test="org-call-ended">
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
                        onClick={dismissOrgCall}
                        className="size-8"
                    >
                        <X className="size-4" />
                    </Button>
                </Row>
            </Console>
        );
    }

    if (isThisCall && call.status === 'joined') {
        const cameras = call.peers.filter((peer) => peer.isCameraOn);

        return (
            <Console data-test="org-call-joined">
                {(call.camera !== null || cameras.length > 0) && (
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {cameras.map((peer) => (
                            <CallVideo
                                key={peer.user.id}
                                stream={peer.camera}
                                label={`@${peer.user.handle}`}
                            />
                        ))}
                        <CallVideo
                            stream={call.camera}
                            label={
                                call.camera !== null
                                    ? 'you · cam'
                                    : 'you · cam off'
                            }
                            isMirrored
                        />
                    </div>
                )}
                <Row>
                    <Dot
                        className={cn(
                            call.peers.length === 0
                                ? 'animate-pulse bg-primary'
                                : 'bg-success',
                        )}
                    />
                    <span className="min-w-0 flex-1 truncate">
                        {call.peers.length === 0 ? (
                            <span className="font-semibold">
                                {call.isRinging
                                    ? `Ringing ${name}…`
                                    : 'Waiting for someone to join…'}
                            </span>
                        ) : (
                            <Elapsed
                                since={call.joinedAt}
                                className="font-medium"
                            />
                        )}
                    </span>
                    <CallControls
                        isMicMuted={call.isMicMuted}
                        isCameraOn={call.camera !== null}
                        hangUpLabel="Leave"
                        onMicrophone={toggleOrgCallMicrophone}
                        onCamera={toggleOrgCallCamera}
                        onHangUp={leaveOrgCall}
                    />
                </Row>
                {call.peers.length > 0 && (
                    <ul
                        aria-label="In the call"
                        className="flex flex-wrap gap-1.5"
                    >
                        {call.peers.map((peer) => (
                            <li
                                key={peer.user.id}
                                className="flex max-w-full min-w-0 items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs"
                                title={
                                    peer.code !== ''
                                        ? `fp ${peer.code}. Read it out to @${peer.user.handle}: if you both see the same code, nobody is listening in between.`
                                        : `Connecting to @${peer.user.handle}…`
                                }
                            >
                                <Dot
                                    className={
                                        peer.isConnected
                                            ? 'bg-success'
                                            : 'animate-pulse bg-primary'
                                    }
                                />
                                <span className="truncate">
                                    @{peer.user.handle}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Console>
        );
    }

    if (isConfirming && !isOnCall) {
        return (
            <Console data-test="org-call-confirm">
                <Row>
                    <span className="min-w-0 flex-1 truncate font-semibold">
                        {ongoing !== null
                            ? `Join the call in ${name}?`
                            : `Call everyone in ${name}?`}
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
                            void joinOrgCall(org, user.id);
                        }}
                        data-test="confirm-org-call"
                    >
                        {ongoing !== null ? 'Join' : 'Call'}
                    </Button>
                </Row>
            </Console>
        );
    }

    if (ongoing === null || isOnCall) {
        return null;
    }

    const isRinging = ringing?.org.id === org.id;

    return (
        <Console
            tone={isRinging ? 'primary' : undefined}
            data-test="org-call-ongoing"
        >
            <Row>
                <Dot className="animate-pulse bg-primary" />
                <span className="min-w-0 flex-1 truncate">
                    <span className="font-semibold">Call going on</span>
                    <span className="text-muted-foreground">
                        {' '}
                        · {handlesOf(ongoing.members.map(({ user }) => user))}
                    </span>
                </span>
                {isRinging && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={declineOrgCall}
                    >
                        Decline
                    </Button>
                )}
                <Button
                    type="button"
                    size="sm"
                    onClick={() => void joinOrgCall(org, user.id)}
                    data-test="join-org-call"
                >
                    Join
                </Button>
            </Row>
        </Console>
    );
}

function orgName(org: OrgRef): string {
    return org.name ?? 'the org';
}

/**
 * The first two handles in a call, and how many more: `@nuno, @taylor +3`.
 */
function handlesOf(users: User[]): string {
    const shown = users
        .slice(0, 2)
        .map((user) => `@${user.handle}`)
        .join(', ');

    return users.length > 2 ? `${shown} +${users.length - 2}` : shown;
}

function CallControls({
    isMicMuted,
    isCameraOn,
    hangUpLabel = 'Hang up',
    onMicrophone,
    onCamera,
    onHangUp,
}: {
    isMicMuted: boolean;
    isCameraOn: boolean;
    hangUpLabel?: string;
    onMicrophone: () => void;
    onCamera: () => Promise<string | null>;
    onHangUp: () => void;
}) {
    return (
        <div className="flex shrink-0 items-center gap-1">
            <Control
                label={isMicMuted ? 'Unmute microphone' : 'Mute microphone'}
                isPressed={isMicMuted}
                onClick={onMicrophone}
            >
                {isMicMuted ? (
                    <MicOff className="size-4" />
                ) : (
                    <Mic className="size-4" />
                )}
            </Control>
            <Control
                label={isCameraOn ? 'Turn camera off' : 'Turn camera on'}
                isPressed={isCameraOn}
                onClick={() =>
                    void onCamera().then(
                        (error) => error !== null && toast.error(error),
                    )
                }
            >
                {isCameraOn ? (
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
                onClick={onHangUp}
                data-test="hang-up"
            >
                <PhoneOff className="size-4" />
                {hangUpLabel}
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
