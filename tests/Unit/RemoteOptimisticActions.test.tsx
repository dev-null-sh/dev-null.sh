import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import {
    chatSnapshot,
    loadTab,
    renameOrg,
    seedInbox,
    seedThread,
    setConversationMute,
    setOrgMemberRole,
    settleRequest,
} from '../../resources/js/lib/chat';
import type {
    ConversationSummary,
    OrgMember,
    OrgThread,
    Thread,
    User,
} from '../../resources/js/types';

const partner = { id: 'requester', handle: 'requester' } as User;
const member = { id: 'member', handle: 'member' } as User;
const org = { id: 'optimistic-org', name: 'Before' };
const orgTarget = { kind: 'org' as const, org };
const directTarget = { kind: 'direct' as const, partner };

const request: ConversationSummary = {
    id: 'request',
    kind: 'direct',
    name: null,
    partner,
    members: null,
    last_message: null,
    last_message_at: '2026-10-23T11:00:00Z',
    last_incoming_message_id: null,
    last_incoming_sender: null,
    ahead: 1,
    partner_read_through_id: 0,
    partner_read_at: null,
    is_archived: false,
    pinned_at: null,
    muted_until_at: null,
};

const directThread: Thread = {
    messages: [],
    has_older: false,
    reactions_version: 0,
    reaction_changes: [],
    read_through_id: 0,
    partner_read_through_id: 0,
    partner_read_at: null,
    partner_typing: false,
    is_archived: false,
    pinned_at: null,
    muted_until_at: null,
    access: 'request',
};

const contributor: OrgMember = {
    suggests_mentions: true,
    is_followed: false,
    user: member,
    role: 'contributor',
    read_through_id: 0,
};

const orgThread: OrgThread = {
    messages: [],
    has_older: false,
    reactions_version: 0,
    reaction_changes: [],
    read_through_id: 0,
    members: [contributor],
    typing: [],
    is_archived: false,
    pinned_at: null,
    muted_until_at: null,
    role: 'maintainer',
    access: 'open',
    pinned: [],
};

function heldFetch(): {
    finish: (response: Response) => void;
    fail: () => void;
} {
    let resolve!: (response: Response) => void;
    let reject!: (error: Error) => void;
    vi.stubGlobal(
        'fetch',
        () =>
            new Promise<Response>((onResolve, onReject) => {
                resolve = onResolve;
                reject = onReject;
            }),
    );

    return {
        finish: (response) => resolve(response),
        fail: () => reject(new Error('offline')),
    };
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal('document', {
        cookie: '',
        hidden: true,
        hasFocus: () => false,
        addEventListener: () => {},
        removeEventListener: () => {},
    });
    vi.stubGlobal('window', { setTimeout, clearTimeout });
    seedInbox({ conversations: [], has_more: false });
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

test('a server render starts from an empty store, whatever an earlier render seeded', () => {
    seedThread(directTarget, directThread);
    vi.stubGlobal('window', undefined);

    seedInbox({ conversations: [request], has_more: false });

    assert.equal(chatSnapshot().threads[partner.handle], undefined);
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        ['request'],
    );
});

test('a mute shows at once, and comes off again when the server refuses it', async () => {
    seedThread(directTarget, directThread);
    const response = heldFetch();

    const muting = setConversationMute(directTarget, 'hour');
    assert.notEqual(chatSnapshot().mutePreferences[partner.handle].until, null);

    response.finish(Response.json({}, { status: 500 }));
    await assert.rejects(muting);
    assert.equal(chatSnapshot().mutePreferences[partner.handle].until, null);
    assert.equal(chatSnapshot().threads[partner.handle].muted_until_at, null);
});

test('a mute settles on the end the server saved', async () => {
    seedThread(directTarget, directThread);
    const response = heldFetch();

    const muting = setConversationMute(directTarget, 'hour');
    response.finish(Response.json({ muted_until_at: '2026-10-23T13:00:00Z' }));
    await muting;

    assert.equal(
        chatSnapshot().mutePreferences[partner.handle].until,
        '2026-10-23T13:00:00Z',
    );
});

test('merging a request opens the composer at once, and puts the request back when the server refuses', async () => {
    vi.stubGlobal('fetch', async () =>
        Response.json({
            conversations: [request],
            has_more: false,
            unread: 1,
            requests: 1,
        }),
    );
    await loadTab('fetched');
    seedThread(directTarget, directThread);
    const response = heldFetch();

    const merging = settleRequest(partner, true);
    assert.equal(chatSnapshot().threads[partner.handle].access, 'open');
    assert.deepEqual(chatSnapshot().fetched, []);
    assert.deepEqual(
        chatSnapshot().conversations?.map(({ id }) => id),
        ['request'],
    );

    response.fail();
    assert.equal(await merging, false);
    assert.equal(chatSnapshot().threads[partner.handle].access, 'request');
    assert.deepEqual(
        chatSnapshot().fetched?.map(({ id }) => id),
        ['request'],
    );
    assert.deepEqual(chatSnapshot().conversations, []);
    assert.equal(chatSnapshot().requests, 1);
});

test('a role change shows at once, and goes back when the server refuses it', async () => {
    seedThread(orgTarget, orgThread);
    const response = heldFetch();

    const promoting = setOrgMemberRole(org, member, 'maintainer');
    assert.equal(
        chatSnapshot().threads[`org:${org.id}`].members[0].role,
        'maintainer',
    );

    response.fail();
    await assert.rejects(promoting);
    assert.equal(
        chatSnapshot().threads[`org:${org.id}`].members[0].role,
        'contributor',
    );
});

test('a new org name shows at once, and goes back when the server refuses it', async () => {
    seedThread(orgTarget, orgThread);
    const response = heldFetch();

    const renaming = renameOrg(org, ' After ');
    assert.deepEqual(chatSnapshot().threads[`org:${org.id}`].target, {
        kind: 'org',
        org: { id: org.id, name: 'After' },
    });

    response.fail();
    await assert.rejects(renaming);
    assert.deepEqual(chatSnapshot().threads[`org:${org.id}`].target, {
        kind: 'org',
        org,
    });
});
