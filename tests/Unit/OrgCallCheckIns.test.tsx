import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { joinOrgCall, leaveOrgCall } from '../../resources/js/lib/org-call';

const org = { id: 'org', name: 'Org' };
const me = { id: 'me', handle: 'me' };
const other = { id: 'other', handle: 'other' };
let checkIns: number[];

class FakePeerConnection {
    iceGatheringState = 'complete';
    localDescription: { type: string; sdp: string } | null = null;
    connectionState = 'new';
    addTrack() {}
    addTransceiver() {
        return { sender: { replaceTrack: async () => {} } };
    }
    createDataChannel() {
        return {};
    }
    async createOffer() {
        return { type: 'offer', sdp: 'v=0 offer' };
    }
    async setLocalDescription(description: { type: string; sdp: string }) {
        this.localDescription = description;
    }
    addEventListener() {}
    close() {}
}

const room = (signals: Record<string, unknown> = {}) => ({
    started_at: 1,
    is_ringing: false,
    members: [
        { user: other, joined_at: 1 },
        { user: me, joined_at: 2 },
    ],
    signals,
});

beforeEach(() => {
    vi.useFakeTimers();
    checkIns = [];
    vi.stubGlobal('window', { setTimeout, clearTimeout });
    vi.stubGlobal('document', { cookie: '' });
    vi.stubGlobal('navigator', {
        mediaDevices: {
            getUserMedia: async () => ({ getTracks: () => [] }),
        },
    });
    vi.stubGlobal('RTCPeerConnection', FakePeerConnection);
    vi.stubGlobal(
        'Audio',
        class {
            play = async () => {};
            pause() {}
        },
    );
    vi.stubGlobal('fetch', async (url: string, init?: RequestInit) => {
        if (url.endsWith('/ice-servers')) {
            return Response.json({ ice_servers: [], is_relay_only: false });
        }

        if (init?.method === 'PUT') {
            checkIns.push(Date.now());
        }

        return Response.json({ call: init?.method === 'DELETE' ? null : room() });
    });
});

afterEach(() => {
    leaveOrgCall();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('a member who offers as they join still checks in once every two seconds', async () => {
    await joinOrgCall(org, me.id);
    await vi.advanceTimersByTimeAsync(10_000);

    assert.ok(checkIns.length >= 4 && checkIns.length <= 6, `checked in ${checkIns.length} times in ten seconds`);
    checkIns.slice(1).forEach((at, index) => assert.ok(at - checkIns[index] >= 1_900, `checked in twice within ${at - checkIns[index]}ms`));
});
