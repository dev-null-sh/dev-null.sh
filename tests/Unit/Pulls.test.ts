import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { PendingMessage } from '../../resources/js/lib/chat.ts';
import { pullsOf, statusMessageOf } from '../../resources/js/lib/pulls.ts';
import type { Message, OrgMember, User } from '../../resources/js/types';

const me = { id: 'me' } as User;
const ana = { id: 'ana' } as User;
const ben = { id: 'ben' } as User;

const message = (id: number, user: User, event?: Message['event']) =>
    ({ id, user_id: user.id, client_id: `c${id}`, event }) as Message;

const pending = { client_id: 'p1' } as PendingMessage;

const member = (user: User, readThroughId: number): OrgMember => ({
    user,
    role: null,
    read_through_id: readThroughId,
    suggests_mentions: true,
    is_followed: false,
});

await test('shows an org status under its latest message, whoever wrote it', () => {
    const messages = [message(1, me), message(2, ana), message(3, me, 'added')];

    assert.equal(statusMessageOf(messages, [], me, true), 'c2');
});

await test('shows a direct status under my latest message', () => {
    const messages = [message(1, me), message(2, ana)];

    assert.equal(statusMessageOf(messages, [], me, false), 'c1');
});

await test('shows the status under a message still being pushed', () => {
    const messages = [message(1, ana)];

    assert.equal(statusMessageOf(messages, [pending], me, true), 'p1');
    assert.equal(statusMessageOf(messages, [pending], me, false), 'p1');
});

await test('counts who pulled a message, leaving out its author', () => {
    const members = [member(ana, 5), member(ben, 4)];

    assert.deepEqual(pullsOf(members, message(5, ana)), {
        pulledBy: [],
        total: 1,
    });
    assert.deepEqual(pullsOf(members, message(4, me)), {
        pulledBy: [ana, ben],
        total: 2,
    });
});
