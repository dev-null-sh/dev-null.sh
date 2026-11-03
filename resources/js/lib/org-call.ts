import OrgCallController from '@/actions/App/Http/Controllers/OrgCallController';
import OrgCallIceServerController from '@/actions/App/Http/Controllers/OrgCallIceServerController';
import { callSnapshot, gathered, verificationCode } from '@/lib/call';
import { ChatRequestError, request } from '@/lib/chat';
import { remoteAlertsEnabled } from '@/lib/remote-notifications';
import type { OrgRef, User } from '@/types';

/**
 * An org's call going on, as the incoming-calls poll sees it. It rings the org for a while after it starts, and
 * anyone in the org can join it until the last person in it leaves.
 */
export type OrgCall = {
    org: OrgRef;
    started_at: number;
    is_ringing: boolean;
    is_muted: boolean;
    members: { user: User; joined_at: number }[];
};

/**
 * Someone else in the call, as this browser hears them: once connected, straight from theirs, through the relay.
 */
export type OrgCallPeer = {
    user: User;
    isConnected: boolean;
    camera: MediaStream | null;
    isCameraOn: boolean;
    /**
     * The code only these two browsers can make, like the one of a one-to-one call.
     */
    code: string;
};

/**
 * The user's own part in an org's call. Each member's browser talks to every other's, encrypted from one to the
 * other, so a call is a handful of one-to-one calls held together by the org.
 */
export type OrgCallState =
    | { status: 'idle' }
    | { status: 'joining'; org: OrgRef }
    | {
          status: 'joined';
          org: OrgRef;
          joinedAt: number;
          isRinging: boolean;
          isMicMuted: boolean;
          camera: MediaStream | null;
          peers: OrgCallPeer[];
      }
    | { status: 'ended'; org: OrgRef; reason: string };

type Signal = { id: string; type: 'offer' | 'answer'; sdp: string };

type Room = {
    started_at: number;
    is_ringing: boolean;
    members: { user: User; joined_at: number }[];
    signals: Record<string, Signal>;
};

type Peer = OrgCallPeer & {
    connection: RTCPeerConnection;
    channel: RTCDataChannel | null;
    speaker: HTMLAudioElement;
    offerId: string;
    isOfferer: boolean;
    isAnswered: boolean;
    openedAt: number;
};

const checkIn = 2_000;
const connectFor = 20_000;

let state: OrgCallState = { status: 'idle' };
let ongoing: OrgCall[] = [];
let ringingCall: OrgCall | null = null;
let meId: string | null = null;
let iceConfig: RTCConfiguration = {};
let microphone: MediaStream | null = null;
let camera: MediaStream | null = null;
let others: User[] = [];
const peers = new Map<string, Peer>();
/**
 * The offer each member last made this browser that it answered, so an old one isn't answered twice.
 */
const answered = new Map<string, string>();
/**
 * The signals waiting for the next check-in, keyed by who each is for.
 */
let outbox: Record<string, Signal> = {};
let ringing: HTMLAudioElement | null = null;
let ringingKey: string | null = null;
/**
 * The calls the user declined, so they don't ring again while they still ring the rest of the org.
 */
const declined = new Set<string>();
/**
 * Counts every join and every end, so a check-in that comes back from an earlier call stops instead of starting a
 * second loop next to the current one.
 */
let session = 0;
/**
 * The call the user is in, to count as declined once they leave it, so it doesn't ring them for the rest of its
 * ringing.
 */
let joinedKey: string | null = null;
let timer: number | undefined;
let endTimer: number | undefined;
const listeners = new Set<() => void>();

export function subscribeToOrgCall(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function orgCallSnapshot(): OrgCallState {
    return state;
}

export function ongoingOrgCallsSnapshot(): OrgCall[] {
    return ongoing;
}

export function ringingOrgCallSnapshot(): OrgCall | null {
    return ringingCall;
}

export function isInOrgCall(): boolean {
    return state.status === 'joining' || state.status === 'joined';
}

function update(next: OrgCallState): void {
    state = next;
    playRing();
    notify();
}

function notify(): void {
    listeners.forEach((listener) => listener());
}

/**
 * Takes in the calls the incoming-calls poll found, and rings for one that just started in an org of the user's,
 * unless they are on a call already, declined it, or muted that org.
 */
export function receiveOrgCalls(calls: OrgCall[], userId: string): void {
    const call = callSnapshot();
    const isFree =
        !isInOrgCall() && (call.status === 'idle' || call.status === 'ended');

    meId = userId;
    ongoing = calls;
    ringingCall = isFree
        ? (calls.find(
              (candidate) =>
                  candidate.is_ringing &&
                  !declined.has(keyOf(candidate)) &&
                  !candidate.members.some(({ user }) => user.id === userId),
          ) ?? null)
        : null;
    playRing();
    notify();
}

export function declineOrgCall(): void {
    if (ringingCall !== null) {
        declined.add(keyOf(ringingCall));
        ringingCall = null;
        playRing();
        notify();
    }
}

function keyOf(call: OrgCall): string {
    return `${call.org.id}:${call.started_at}`;
}

/**
 * MSN's ring for a call just started in one of the user's orgs, and its outgoing ring for whoever started one, while
 * they are still alone in it.
 */
function playRing(): void {
    const next =
        state.status === 'joined' && state.isRinging && others.length === 0
            ? 'ringback'
            : ringingCall !== null &&
                !ringingCall.is_muted &&
                meId !== null &&
                remoteAlertsEnabled(meId, 'sound')
              ? `ring:${keyOf(ringingCall)}`
              : null;

    if (next === ringingKey) {
        return;
    }

    ringing?.pause();
    ringing = null;
    ringingKey = next;

    if (next !== null) {
        ringing = new Audio(
            next === 'ringback' ? '/sounds/ringback.mp3' : '/sounds/ring.mp3',
        );
        ringing.loop = true;
        void ringing.play().catch(() => undefined);
    }
}

function url(org: OrgRef) {
    return { org: org.id };
}

/**
 * Joins the org's call, or starts it, which rings everyone else in the org.
 */
export async function joinOrgCall(org: OrgRef, userId: string): Promise<void> {
    const call = callSnapshot();

    if (isInOrgCall() || (call.status !== 'idle' && call.status !== 'ended')) {
        return;
    }

    window.clearTimeout(endTimer);
    const current = ++session;
    meId = userId;
    ringingCall = null;
    const joining = ongoing.find((candidate) => candidate.org.id === org.id);
    joinedKey = joining !== undefined ? keyOf(joining) : null;
    update({ status: 'joining', org });

    // Asked for alongside the relay, and let go of if anything after fails, so the mic doesn't stay on.
    const media = navigator.mediaDevices.getUserMedia({ audio: true });

    try {
        const [servers, stream] = await Promise.all([
            request<{ ice_servers: RTCIceServer[]; is_relay_only: boolean }>(
                OrgCallIceServerController.index.url(url(org)),
            ),
            media,
        ]);

        if (current !== session) {
            stream.getTracks().forEach((track) => track.stop());

            return;
        }

        microphone = stream;
        // Only through the relay: a direct route would hand everyone in the call each other's IP addresses.
        iceConfig = {
            iceServers: servers.ice_servers,
            iceTransportPolicy: servers.is_relay_only ? 'relay' : 'all',
        };

        const { call: room } = await request<{ call: Room | null }>(
            OrgCallController.store.url(url(org)),
            'POST',
        );

        // Left while joining: leaving had nothing to take the user out of yet.
        if (current !== session) {
            if (room !== null) {
                void request(OrgCallController.destroy.url(url(org)), 'DELETE');
            }

            return;
        }

        // Filled up while the user joined.
        if (room === null) {
            end('This call is full.', false);

            return;
        }

        joinedKey = `${org.id}:${room.started_at}`;

        update({
            status: 'joined',
            org,
            joinedAt: Date.now(),
            isRinging: room.is_ringing,
            isMicMuted: false,
            camera: null,
            peers: [],
        });
        await sync(room);
    } catch (error) {
        void media
            .then((stream) => {
                if (stream !== microphone) {
                    stream.getTracks().forEach((track) => track.stop());
                }
            })
            .catch(() => undefined);

        // A join the user already left, and maybe replaced with another, has nothing left to end.
        if (current === session) {
            end(failure(error, 'Could not join the call.'), false);
        }

        return;
    }

    timer = window.setTimeout(() => void poll(current), checkIn);
}

/**
 * Checks in with the call: hands over this browser's offers and answers, and picks up the others'.
 */
async function poll(current: number): Promise<void> {
    if (state.status !== 'joined' || current !== session) {
        return;
    }

    const { org } = state;
    const signals = outbox;
    outbox = {};

    try {
        const { call: room } = await request<{ call: Room | null }>(
            OrgCallController.update.url(url(org)),
            'PUT',
            { signals },
        );

        if (current !== session) {
            return;
        }

        if (room === null) {
            end('Call ended.');

            return;
        }

        await sync(room);
    } catch (error) {
        if (current !== session) {
            return;
        }

        // Only being out of the call or the org ends it. Anything else, like a rate limit, is tried again.
        if (
            error instanceof ChatRequestError &&
            (error.status === 403 || error.status === 404)
        ) {
            end('Call ended.');

            return;
        }

        outbox = { ...signals, ...outbox };
    }

    if (current === session) {
        timer = window.setTimeout(() => void poll(current), checkIn);
    }
}

/**
 * Connects to whoever joined and lets go of whoever left. Whoever joined later offers, so every two members agree
 * on who starts without asking each other.
 */
async function sync(room: Room): Promise<void> {
    const self = room.members.find(({ user }) => user.id === meId);

    if (self === undefined || state.status !== 'joined') {
        return;
    }

    const members = room.members.filter(({ user }) => user.id !== self.user.id);
    others = members.map(({ user }) => user);

    peers.forEach((_, id) => {
        if (!others.some((other) => other.id === id)) {
            close(id);
            answered.delete(id);
        }
    });

    await Promise.all(
        members.map(async ({ user, joined_at: joinedAt }) => {
            const isOfferer =
                self.joined_at > joinedAt ||
                (self.joined_at === joinedAt && self.user.id > user.id);
            const signal = room.signals[user.id];

            if (peers.get(user.id)?.isOfferer === !isOfferer) {
                close(user.id);
            }

            const peer = peers.get(user.id);

            try {
                if (!isOfferer) {
                    if (
                        signal?.type === 'offer' &&
                        signal.id !== answered.get(user.id)
                    ) {
                        close(user.id);
                        await answer(user, signal);
                    }
                } else if (
                    peer === undefined ||
                    (!peer.isConnected &&
                        Date.now() - peer.openedAt > connectFor)
                ) {
                    close(user.id);
                    await offer(user);
                } else if (
                    signal?.type === 'answer' &&
                    signal.id === peer.offerId &&
                    !peer.isAnswered
                ) {
                    peer.isAnswered = true;
                    await peer.connection.setRemoteDescription({
                        type: 'answer',
                        sdp: signal.sdp,
                    });
                }
            } catch {
                // Start over with them on the next check-in.
                close(user.id);
            }
        }),
    );

    if (state.status === 'joined') {
        update({ ...state, isRinging: room.is_ringing });
        publish();
    }
}

async function offer(user: User): Promise<void> {
    const peer = open(user, crypto.randomUUID(), true);
    await peer.connection.setLocalDescription(
        await peer.connection.createOffer(),
    );
    const sdp = await gathered(peer.connection);

    // Unless they left, or the user did, while the browser gathered its addresses.
    if (peers.get(user.id) === peer) {
        outbox[user.id] = { id: peer.offerId, type: 'offer', sdp };
    }
}

async function answer(user: User, signal: Signal): Promise<void> {
    const peer = open(user, signal.id, false);
    answered.set(user.id, signal.id);
    await peer.connection.setRemoteDescription({
        type: 'offer',
        sdp: signal.sdp,
    });
    const video = videoOf(peer.connection);

    if (video !== undefined) {
        video.direction = 'sendrecv';
        await video.sender.replaceTrack(camera?.getVideoTracks()[0] ?? null);
    }

    await peer.connection.setLocalDescription(
        await peer.connection.createAnswer(),
    );
    const sdp = await gathered(peer.connection);

    if (peers.get(user.id) === peer) {
        outbox[user.id] = { id: signal.id, type: 'answer', sdp };
    }
}

function open(user: User, offerId: string, isOfferer: boolean): Peer {
    const connection = new RTCPeerConnection(iceConfig);
    const peer: Peer = {
        user,
        isConnected: false,
        camera: null,
        isCameraOn: false,
        code: '',
        connection,
        channel: null,
        speaker: new Audio(),
        offerId,
        isOfferer,
        isAnswered: false,
        openedAt: Date.now(),
    };
    peers.set(user.id, peer);

    if (microphone !== null) {
        const stream = microphone;
        stream
            .getTracks()
            .forEach((track) => connection.addTrack(track, stream));
    }

    connection.ontrack = (event) => {
        if (event.track.kind === 'video') {
            peer.camera = new MediaStream([event.track]);
        } else {
            peer.speaker.srcObject = new MediaStream([event.track]);
            void peer.speaker.play().catch(() => undefined);
        }

        publish();
    };

    // The channel says when their camera goes on or off, and closes the moment they leave.
    const watch = (channel: RTCDataChannel) => {
        peer.channel = channel;
        channel.onopen = () => sendCamera(peer);
        channel.onclose = () => {
            if (peers.get(user.id) === peer) {
                close(user.id);
                publish();
            }
        };
        channel.onmessage = (event: MessageEvent<unknown>) => {
            const message: unknown =
                typeof event.data === 'string' ? JSON.parse(event.data) : null;

            if (
                typeof message === 'object' &&
                message !== null &&
                'camera' in message &&
                typeof message.camera === 'boolean'
            ) {
                peer.isCameraOn = message.camera;
                publish();
            }
        };
    };

    if (isOfferer) {
        const video = connection.addTransceiver('video', {
            direction: 'sendrecv',
        });
        void video.sender
            .replaceTrack(camera?.getVideoTracks()[0] ?? null)
            .catch(() => undefined);
        watch(connection.createDataChannel('call'));
    } else {
        connection.ondatachannel = (event) => watch(event.channel);
    }

    connection.onconnectionstatechange = () => {
        if (peers.get(user.id) !== peer) {
            return;
        }

        if (connection.connectionState === 'connected' && !peer.isConnected) {
            peer.isConnected = true;
            void verificationCode(connection).then((code) => {
                peer.code = code;
                publish();
            });
        } else if (connection.connectionState === 'failed') {
            close(user.id);
        }

        publish();
    };

    return peer;
}

function close(id: string): void {
    const peer = peers.get(id);

    if (peer !== undefined) {
        peers.delete(id);
        peer.connection.close();
        peer.speaker.srcObject = null;
    }
}

function publish(): void {
    if (state.status !== 'joined') {
        return;
    }

    update({
        ...state,
        peers: others.map((user) => {
            const peer = peers.get(user.id);

            return {
                user,
                isConnected: peer?.isConnected ?? false,
                camera: peer?.camera ?? null,
                isCameraOn: peer?.isCameraOn ?? false,
                code: peer?.code ?? '',
            };
        }),
    });
}

function videoOf(connection: RTCPeerConnection): RTCRtpTransceiver | undefined {
    return connection
        .getTransceivers()
        .find((transceiver) => transceiver.receiver.track.kind === 'video');
}

function sendCamera(peer: Peer): void {
    if (peer.channel?.readyState === 'open') {
        peer.channel.send(JSON.stringify({ camera: camera !== null }));
    }
}

export function leaveOrgCall(): void {
    if (state.status !== 'joining' && state.status !== 'joined') {
        return;
    }

    void request(OrgCallController.destroy.url(url(state.org)), 'DELETE').catch(
        () => undefined,
    );
    end('You left the call.');
}

export function toggleOrgCallMicrophone(): void {
    if (state.status !== 'joined') {
        return;
    }

    const isMicMuted = !state.isMicMuted;
    microphone?.getAudioTracks().forEach((track) => {
        track.enabled = !isMicMuted;
    });
    update({ ...state, isMicMuted });
}

/**
 * Turns the camera on or off for everyone in the call at once. Returns why it couldn't, if it couldn't.
 */
export async function toggleOrgCallCamera(): Promise<string | null> {
    if (state.status !== 'joined') {
        return null;
    }

    if (camera !== null) {
        camera.getTracks().forEach((track) => track.stop());
        camera = null;
    } else {
        try {
            camera = await navigator.mediaDevices.getUserMedia({ video: true });
        } catch (error) {
            return error instanceof DOMException &&
                error.name === 'NotAllowedError'
                ? 'Allow the camera to turn it on.'
                : 'Could not turn the camera on.';
        }
    }

    await Promise.all(
        [...peers.values()].map(async (peer) => {
            await videoOf(peer.connection)
                ?.sender.replaceTrack(camera?.getVideoTracks()[0] ?? null)
                .catch(() => undefined);
            sendCamera(peer);
        }),
    );

    if (state.status === 'joined') {
        update({ ...state, camera });
    } else {
        camera?.getTracks().forEach((track) => track.stop());
        camera = null;
    }

    return null;
}

export function dismissOrgCall(): void {
    if (state.status === 'ended') {
        window.clearTimeout(endTimer);
        update({ status: 'idle' });
    }
}

function failure(error: unknown, fallback: string): string {
    if (error instanceof DOMException && error.name === 'NotAllowedError') {
        return 'Allow the microphone to join the call.';
    }

    return error instanceof ChatRequestError ? error.firstError() : fallback;
}

function end(reason: string, wasJoined = true): void {
    if (state.status === 'idle' || state.status === 'ended') {
        return;
    }

    const { org } = state;
    session++;
    window.clearTimeout(timer);

    if (joinedKey !== null) {
        declined.add(joinedKey);
        joinedKey = null;
    }

    [...peers.keys()].forEach(close);
    answered.clear();
    outbox = {};
    others = [];
    [microphone, camera].forEach((stream) =>
        stream?.getTracks().forEach((track) => track.stop()),
    );
    microphone = null;
    camera = null;

    if (wasJoined) {
        void new Audio('/sounds/hangup.mp3').play().catch(() => undefined);
    }

    update({ status: 'ended', org, reason });
    window.clearTimeout(endTimer);
    endTimer = window.setTimeout(dismissOrgCall, 4_000);
}
