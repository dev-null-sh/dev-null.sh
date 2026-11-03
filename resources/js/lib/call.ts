import CallController from '@/actions/App/Http/Controllers/CallController';
import ConversationCallController from '@/actions/App/Http/Controllers/ConversationCallController';
import ConversationCallIceServerController from '@/actions/App/Http/Controllers/ConversationCallIceServerController';
import { ChatRequestError, request } from '@/lib/chat';
import { declineOrgCall, isInOrgCall, receiveOrgCalls } from '@/lib/org-call';
import type { OrgCall } from '@/lib/org-call';
import { remoteAlertsEnabled } from '@/lib/remote-notifications';
import type { User } from '@/types';

/**
 * A call: the browsers swap an offer and an answer through the server, then the audio and video go between them
 * through a relay, encrypted from one browser to the other, so neither /dev/null nor the relay can hear or see it, and
 * neither person ever sees the other's IP address.
 */
export type CallState =
    | { status: 'idle' }
    | { status: 'calling'; partner: User; since: number }
    | { status: 'ringing'; partner: User; sdp: string }
    | { status: 'connecting'; partner: User }
    | {
          status: 'connected';
          partner: User;
          startedAt: number;
          isMicMuted: boolean;
          camera: MediaStream | null;
          partnerCamera: MediaStream | null;
          isPartnerCameraOn: boolean;
          /**
           * A short code made from both browsers' key fingerprints. When both people read out the same one, nobody
           * swapped the keys on the way through the server.
           */
          code: string;
      }
    | { status: 'ended'; partner: User; reason: string };

const answerPoll = 1_500;
const incomingPoll = 3_000;
const ringFor = 45_000;
const connectFor = 20_000;

let state: CallState = { status: 'idle' };
let connection: RTCPeerConnection | null = null;
let channel: RTCDataChannel | null = null;
let microphone: MediaStream | null = null;
let camera: MediaStream | null = null;
let partnerCamera: MediaStream | null = null;
let speaker: HTMLAudioElement | null = null;
/**
 * MSN's ring while a call rings: the incoming one for the callee, the outgoing one for the caller.
 */
let ringing: HTMLAudioElement | null = null;
let timer: number | undefined;
let endTimer: number | undefined;
/**
 * The offer the user declined, so it doesn't ring again while it is still in the cache.
 */
let seenOffer: string | null = null;
/**
 * The partners whose conversation shows the call in its composer right now, so the bar on top of the page steps
 * aside for them.
 */
let consoles: string[] = [];
const listeners = new Set<() => void>();

export function subscribeToCall(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function callSnapshot(): CallState {
    return state;
}

export function callConsolesSnapshot(): string[] {
    return consoles;
}

/**
 * Marks the partner's conversation as showing the call. Returns the function that unmarks it.
 */
export function showCallConsole(handle: string): () => void {
    consoles = [...consoles, handle];
    notify();

    return () => {
        const index = consoles.indexOf(handle);
        consoles = consoles.filter((_, at) => at !== index);
        notify();
    };
}

function update(next: CallState): void {
    // Picking up or placing a call stops an org's call from ringing, as the poll no longer checks while on one.
    if (
        next.status === 'calling' ||
        next.status === 'connecting' ||
        next.status === 'connected'
    ) {
        declineOrgCall();
    }

    if (next.status !== state.status) {
        ringing?.pause();
        ringing = null;

        if (next.status === 'calling') {
            ring('/sounds/ringback.mp3');
        }
    }

    state = next;
    notify();
}

function ring(sound: string): void {
    ringing = new Audio(sound);
    ringing.loop = true;
    void ringing.play().catch(() => undefined);
}

function notify(): void {
    listeners.forEach((listener) => listener());
}

function url(partner: User) {
    return { partner: partner.handle };
}

/**
 * Rings the partner. The offer carries the caller's IP addresses, so it is only sent once they confirmed.
 */
export async function startCall(partner: User): Promise<void> {
    if (
        (state.status !== 'idle' && state.status !== 'ended') ||
        isInOrgCall()
    ) {
        return;
    }

    window.clearTimeout(endTimer);
    update({ status: 'calling', partner, since: Date.now() });

    try {
        const peer = await connect(partner, true);
        await peer.setLocalDescription(await peer.createOffer());
        await request(
            ConversationCallController.store.url(url(partner)),
            'POST',
            { sdp: await gathered(peer) },
        );
    } catch (error) {
        end(partner, failure(error, `Could not call @${partner.handle}.`));

        return;
    }

    const ringsUntil = Date.now() + ringFor;

    const poll = async () => {
        if (state.status !== 'calling' || connection === null) {
            return;
        }

        try {
            const { call } = await request<{
                call: { answer: string | null } | null;
            }>(ConversationCallController.show.url(url(partner)));

            if (call?.answer) {
                update({ status: 'connecting', partner });
                await connection.setRemoteDescription({
                    type: 'answer',
                    sdp: call.answer,
                });
                void forget(partner);
                giveUpConnecting(partner);

                return;
            }

            if (call === null || Date.now() > ringsUntil) {
                void forget(partner);
                end(partner, 'No answer.');

                return;
            }
        } catch {
            // Try again on the next poll.
        }

        timer = window.setTimeout(() => void poll(), answerPoll);
    };

    timer = window.setTimeout(() => void poll(), answerPoll);
}

/**
 * Answers the call ringing. Only now does the callee's own IP address leave their browser.
 */
export async function acceptCall(): Promise<void> {
    if (state.status !== 'ringing') {
        return;
    }

    const { partner, sdp } = state;
    update({ status: 'connecting', partner });

    try {
        const peer = await connect(partner, false);
        await peer.setRemoteDescription({ type: 'offer', sdp });
        peer.getTransceivers().forEach((transceiver) => {
            if (transceiver.receiver.track.kind === 'video') {
                transceiver.direction = 'sendrecv';
            }
        });
        await peer.setLocalDescription(await peer.createAnswer());
        await request(
            ConversationCallController.update.url(url(partner)),
            'PUT',
            { sdp: await gathered(peer) },
        );
    } catch (error) {
        void forget(partner, { failed: true });
        end(partner, failure(error, 'Could not answer the call.'));

        return;
    }

    giveUpConnecting(partner);
}

export function declineCall(): void {
    if (state.status !== 'ringing') {
        return;
    }

    void forget(state.partner);
    seenOffer = state.sdp;
    update({ status: 'idle' });
}

export function hangUp(): void {
    if (state.status === 'idle' || state.status === 'ended') {
        return;
    }

    if (state.status === 'calling') {
        void forget(state.partner);
    }

    end(state.partner, 'Call ended.');
}

export function toggleMicrophone(): void {
    if (state.status !== 'connected') {
        return;
    }

    const isMicMuted = !state.isMicMuted;
    microphone?.getAudioTracks().forEach((track) => {
        track.enabled = !isMicMuted;
    });
    update({ ...state, isMicMuted });
}

/**
 * Turns the camera on or off. The call was set up with room for video, so the camera only swaps into it, straight
 * to the partner, and the partner hears about it over the call itself. Returns why it couldn't, if it couldn't.
 */
export async function toggleCamera(): Promise<string | null> {
    const sender = connection
        ?.getTransceivers()
        .find(
            (transceiver) => transceiver.receiver.track.kind === 'video',
        )?.sender;

    if (state.status !== 'connected' || sender === undefined) {
        return null;
    }

    if (camera !== null) {
        camera.getTracks().forEach((track) => track.stop());
        camera = null;
        await sender.replaceTrack(null);
    } else {
        try {
            camera = await navigator.mediaDevices.getUserMedia({ video: true });
            await sender.replaceTrack(camera.getVideoTracks()[0]);
        } catch (error) {
            camera?.getTracks().forEach((track) => track.stop());
            camera = null;

            return error instanceof DOMException &&
                error.name === 'NotAllowedError'
                ? 'Allow the camera to turn it on.'
                : 'Could not turn the camera on.';
        }
    }

    if (state.status === 'connected') {
        update({ ...state, camera });
    }

    if (channel?.readyState === 'open') {
        channel.send(JSON.stringify({ camera: camera !== null }));
    }

    return null;
}

export function dismissCall(): void {
    if (state.status === 'ended') {
        window.clearTimeout(endTimer);
        update({ status: 'idle' });
    }
}

/**
 * Checks for a call ringing the user while nothing else is going on, and rings along with it unless the remote is
 * muted. It also picks up the calls going on in the user's orgs, which anyone in them can join. Returns the function
 * that stops watching.
 */
export function watchIncomingCalls(userId: string): () => void {
    let pollTimer: number | undefined;
    let isStopped = false;

    const poll = async () => {
        window.clearTimeout(pollTimer);

        // On a call, nothing else can ring, and there's no other call to join, so there is nothing to check.
        if (!document.hidden && isFree()) {
            try {
                const { call, org_calls: orgCalls } = await request<{
                    call: {
                        partner: User;
                        sdp: string;
                        is_muted: boolean;
                    } | null;
                    org_calls: OrgCall[];
                }>(CallController.show.url());

                // Unless the user started or joined a call while it checked.
                if (isFree()) {
                    takeIncomingCall(call, userId);
                    receiveOrgCalls(orgCalls, userId);
                }
            } catch {
                // Try again on the next poll.
            }
        }

        if (!isStopped) {
            pollTimer = window.setTimeout(() => void poll(), incomingPoll);
        }
    };

    void poll();

    return () => {
        isStopped = true;
        window.clearTimeout(pollTimer);
    };
}

function isFree(): boolean {
    return (
        !isInOrgCall() &&
        (state.status === 'idle' ||
            state.status === 'ended' ||
            state.status === 'ringing')
    );
}

function takeIncomingCall(
    call: { partner: User; sdp: string; is_muted: boolean } | null,
    userId: string,
): void {
    if (call === null || call.sdp === seenOffer) {
        if (state.status === 'ringing') {
            end(state.partner, `Missed call from @${state.partner.handle}.`);
        }
    } else if (state.status !== 'ringing' || state.sdp !== call.sdp) {
        window.clearTimeout(endTimer);
        update({
            status: 'ringing',
            partner: call.partner,
            sdp: call.sdp,
        });

        if (!call.is_muted && remoteAlertsEnabled(userId, 'sound')) {
            ring('/sounds/ring.mp3');
        }
    }
}

async function connect(
    partner: User,
    isCaller: boolean,
): Promise<RTCPeerConnection> {
    const { ice_servers: iceServers, is_relay_only: isRelayOnly } =
        await request<{
            ice_servers: RTCIceServer[];
            is_relay_only: boolean;
        }>(ConversationCallIceServerController.index.url(url(partner)));
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    // Only through the relay: a direct route would hand each person the other's IP address.
    const peer = new RTCPeerConnection({
        iceServers,
        iceTransportPolicy: isRelayOnly ? 'relay' : 'all',
    });
    microphone = stream;
    connection = peer;
    stream.getTracks().forEach((track) => peer.addTrack(track, stream));

    peer.ontrack = (event) => {
        if (event.track.kind === 'video') {
            partnerCamera = new MediaStream([event.track]);

            return;
        }

        speaker ??= new Audio();
        speaker.srcObject = new MediaStream([event.track]);
        void speaker.play().catch(() => undefined);
    };

    // Hanging up closes the channel on both ends at once, with no server in between. It also says when the
    // partner's camera goes on or off.
    const watchChannel = (opened: RTCDataChannel) => {
        channel = opened;
        opened.onclose = () => {
            if (connection === peer) {
                end(partner, 'Call ended.');
            }
        };
        opened.onmessage = (event: MessageEvent<unknown>) => {
            const message: unknown =
                typeof event.data === 'string' ? JSON.parse(event.data) : null;

            if (
                state.status === 'connected' &&
                connection === peer &&
                typeof message === 'object' &&
                message !== null &&
                'camera' in message &&
                typeof message.camera === 'boolean'
            ) {
                update({ ...state, isPartnerCameraOn: message.camera });
            }
        };
    };

    if (isCaller) {
        peer.addTransceiver('video', { direction: 'sendrecv' });
        watchChannel(peer.createDataChannel('call'));
    } else {
        peer.ondatachannel = (event) => watchChannel(event.channel);
    }

    peer.onconnectionstatechange = () => {
        if (connection !== peer) {
            return;
        }

        if (
            peer.connectionState === 'connected' &&
            state.status === 'connecting'
        ) {
            window.clearTimeout(timer);
            update({
                status: 'connected',
                partner,
                startedAt: Date.now(),
                isMicMuted: false,
                camera: null,
                partnerCamera,
                isPartnerCameraOn: false,
                code: '',
            });
            void verificationCode(peer).then((code) => {
                if (state.status === 'connected' && connection === peer) {
                    update({ ...state, code });
                }
            });
        } else if (peer.connectionState === 'failed') {
            end(
                partner,
                state.status === 'connected'
                    ? 'Call ended.'
                    : "Couldn't connect the call.",
            );
        }
    };

    return peer;
}

/**
 * Waits until the browser knows every way to reach it, so one offer or answer holds everything and the server only
 * passes on two messages.
 */
export async function gathered(peer: RTCPeerConnection): Promise<string> {
    if (peer.iceGatheringState !== 'complete') {
        await new Promise<void>((resolve) => {
            const done = window.setTimeout(resolve, 3_000);
            peer.addEventListener('icegatheringstatechange', () => {
                if (peer.iceGatheringState === 'complete') {
                    window.clearTimeout(done);
                    resolve();
                }
            });
        });
    }

    return peer.localDescription?.sdp ?? '';
}

/**
 * Eight hex digits from both fingerprints, read like a short hash: `3f9a c217`.
 */
export async function verificationCode(
    peer: RTCPeerConnection,
): Promise<string> {
    const fingerprints = [
        peer.localDescription?.sdp,
        peer.remoteDescription?.sdp,
    ]
        .map((sdp) => /a=fingerprint:(.+)/.exec(sdp ?? '')?.[1]?.trim() ?? '')
        .toSorted()
        .join('|');
    const digest = new Uint8Array(
        await crypto.subtle.digest(
            'SHA-256',
            new TextEncoder().encode(fingerprints),
        ),
    );
    const hex = Array.from(digest.slice(0, 4), (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');

    return `${hex.slice(0, 4)} ${hex.slice(4)}`;
}

/**
 * When the relay can't be reached, the call stops waiting after a while instead of hanging.
 */
function giveUpConnecting(partner: User): void {
    timer = window.setTimeout(() => {
        if (state.status === 'connecting') {
            end(partner, "Couldn't connect the call.");
        }
    }, connectFor);
}

/**
 * Forgets the call with the partner. Failing to answer it says so, so the remote doesn't log it as declined.
 */
async function forget(
    partner: User,
    body: { failed: boolean } = { failed: false },
): Promise<void> {
    await request(
        ConversationCallController.destroy.url(url(partner)),
        'DELETE',
        body,
    ).catch(() => undefined);
}

function failure(error: unknown, fallback: string): string {
    if (error instanceof DOMException && error.name === 'NotAllowedError') {
        return 'Allow the microphone to call.';
    }

    return error instanceof ChatRequestError ? error.firstError() : fallback;
}

function end(partner: User, reason: string): void {
    window.clearTimeout(timer);
    connection?.close();
    connection = null;
    channel = null;
    [microphone, camera].forEach((stream) =>
        stream?.getTracks().forEach((track) => track.stop()),
    );
    microphone = null;
    camera = null;
    partnerCamera = null;

    if (speaker !== null) {
        speaker.srcObject = null;
    }

    // A call that never rang through on this side, a missed one, ends quietly.
    if (state.status !== 'ringing') {
        void new Audio('/sounds/hangup.mp3').play().catch(() => undefined);
    }

    update({ status: 'ended', partner, reason });
    window.clearTimeout(endTimer);
    endTimer = window.setTimeout(dismissCall, 4_000);
}
