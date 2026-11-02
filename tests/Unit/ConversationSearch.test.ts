import assert from 'node:assert/strict';
import { test } from 'node:test';
import { conversationsMatching } from '../../resources/js/lib/conversation-search.ts';
import type { ConversationSummary, User } from '../../resources/js/types';

const person = (handle: string, name: string) => ({ id: handle, handle, name }) as User;
const ana = person('ana', 'Ana Lovelace');
const ben = person('benji', 'Ben Hopper');

const direct = (partner: User, changes: Partial<ConversationSummary> = {}) =>
    ({ id: `direct-${partner.id}`, kind: 'direct', name: null, partner, members: null, ...changes }) as ConversationSummary;

const org = (id: string, name: string | null, users: User[]) =>
    ({ id, kind: 'org', name, partner: null, members: users.map((user) => ({ user, role: null })) }) as ConversationSummary;

const ids = (conversations: ConversationSummary[]) => conversations.map(({ id }) => id);

test('finds loaded conversations by a partner or member handle or name, or an org name, ignoring case', () => {
    const loaded = [direct(ana), direct(ben), org('core', 'Core Team', [ana]), org('crew', null, [ben])];

    assert.deepEqual(ids(conversationsMatching('ANA', loaded)), ['direct-ana', 'core']);
    assert.deepEqual(ids(conversationsMatching('hopp', loaded)), ['direct-benji', 'crew']);
    assert.deepEqual(ids(conversationsMatching('core t', loaded)), ['core']);
    assert.deepEqual(conversationsMatching('nobody', loaded), []);
});

test('keeps the first matching copy of a conversation found in several lists', () => {
    const fresh = direct(ana, { ahead: 2 });
    const renamed = org('core', 'Core', [ben]);

    assert.deepEqual(conversationsMatching('ana', [fresh, renamed, direct(ana, { ahead: 0 }), org('core', 'Ana fans', [ben])]), [
        fresh,
        org('core', 'Ana fans', [ben]),
    ]);
});

test('keeps every conversation the server found for the term, ahead of stale copies on hand', () => {
    const found = [org('core', 'Review Renamed Team', [ben]), direct(ana)];

    assert.deepEqual(conversationsMatching('review', [org('core', 'Core', [ben])], found), [...found]);
});
