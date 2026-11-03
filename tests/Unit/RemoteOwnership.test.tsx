import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { ConversationSummary } from '../../resources/js/types';

const visits: unknown[] = [];
const timeouts: unknown[] = [];

vi.mock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    router: { visit: (url: unknown) => visits.push(url), on: () => () => {} },
}));
vi.mock('../../resources/js/components/timeout-dialog', async () => ({
    ...(await vi.importActual('../../resources/js/components/timeout-dialog')),
    showTimeout: (timeout: unknown) => timeouts.push(timeout),
}));
vi.mock('../../resources/js/lib/remote-push', () => ({
    remotePushEnabled: () => false,
    remotePushOptedIn: () => false,
    syncRemotePush: async () => {},
    setRemotePush: async () => true,
}));

const chat = await import('../../resources/js/lib/chat');

const privateOrg = {
    id: 'private-a',
    kind: 'org',
    name: 'Private A title',
    partner: null,
    members: [],
    ahead: 0,
    pinned_at: null,
    muted_until_at: null,
    is_archived: false,
} as unknown as ConversationSummary;
const target = { kind: 'org', org: { id: 'private-a', name: 'Private A title' } } as const;

let release: (response: Response | Error) => void;

beforeEach(() => {
    visits.length = 0;
    timeouts.length = 0;
    const events = new EventTarget();
    vi.stubGlobal('window', {
        setTimeout,
        clearTimeout,
        setInterval,
        clearInterval,
        addEventListener: events.addEventListener.bind(events),
        removeEventListener: events.removeEventListener.bind(events),
        localStorage: { getItem: () => null, setItem: () => {} },
    });
    vi.stubGlobal('document', {
        hidden: false,
        cookie: '',
        hasFocus: () => true,
        addEventListener: () => {},
        removeEventListener: () => {},
    });
    vi.stubGlobal('fetch', () =>
        new Promise<Response>((resolve, reject) => {
            release = (outcome) => (outcome instanceof Error ? reject(outcome) : resolve(outcome));
        }),
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
});

/**
 * Starts an action as the first account, has someone else sign in while it is on its way, then lets it finish.
 */
async function acrossSignIn(start: () => Promise<unknown>, outcome: Response | Error) {
    const stopFirst = chat.watchRemoteUpdates('first');
    chat.seedInbox({ conversations: [privateOrg], has_more: false } as never);
    const action = start().then(
        (value) => ({ value }),
        (error: unknown) => ({ error }),
    );
    await Promise.resolve();
    stopFirst();
    const stopSecond = chat.watchRemoteUpdates('second');
    release(outcome);
    const settled = await action;
    stopSecond();

    return settled;
}

const outcomes = {
    'a network failure': () => new Error('synthetic network failure'),
    'a server error': () => Response.json({ message: 'Server Error' }, { status: 500 }),
    'a success': () => new Response(null, { status: 204 }),
};

for (const [name, outcome] of Object.entries(outcomes)) {
    test(`an archive the previous account started, ending in ${name}, never puts its conversations back`, async () => {
        const settled = await acrossSignIn(() => chat.setConversationArchived(target, true), outcome());

        assert.equal(JSON.stringify(chat.chatSnapshot()).includes('private-a'), false);
        assert.equal('error' in settled, false);
    });

    test(`a rename the previous account started, ending in ${name}, leaves the next account's remotes alone`, async () => {
        const settled = await acrossSignIn(() => chat.renameOrg(target.org, 'Renamed'), outcome());

        assert.equal(JSON.stringify(chat.chatSnapshot()).includes('private-a'), false);
        assert.equal('error' in settled, false);
    });
}

test("a ban or a timeout answered to the previous account isn't shown to the next one", async () => {
    await acrossSignIn(() => chat.setConversationArchived(target, true), Response.json({ banned: true }, { status: 403 }));
    await acrossSignIn(
        () => chat.setConversationMute(target, 'hour'),
        Response.json({ message: 'Timed out', timeout: { ends_at: '2026-11-04T00:00:00Z', reason: 'x' } }, { status: 403 }),
    );

    assert.deepEqual(visits, []);
    assert.deepEqual(timeouts, []);
});

test('a pin, a mute or a search the previous account started writes nothing for the next one', async () => {
    for (const start of [
        () => chat.setConversationPinned(target, true),
        () => chat.setConversationMute(target, 'hour'),
        () => chat.searchConversations('private', 'open', new AbortController().signal),
        () => chat.sendPing(target),
        () => chat.addOrgMembers(target.org, ['someone'], false),
        () => chat.removeOrgMember(target.org, { id: 'me', handle: 'me' } as never, true),
    ]) {
        const settled = await acrossSignIn(start, Response.json({ conversations: [privateOrg], pinned_at: '2026-11-03T10:00:00Z', muted_until_at: '2026-11-04T00:00:00Z' }));

        assert.equal(JSON.stringify(chat.chatSnapshot()).includes('private-a'), false);
        assert.equal('error' in settled, false);
    }
});

test('an org the previous account created is not shown as created to the next one', async () => {
    const settled = await acrossSignIn(() => chat.createOrg('Private A', []), Response.json({ org: { id: 'private-a' } }));

    assert.deepEqual(settled, { value: null });
    assert.equal(JSON.stringify(chat.chatSnapshot()).includes('private-a'), false);
});
